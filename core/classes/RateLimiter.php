<?php

declare(strict_types=1);

/**
 * Shared, database-backed fixed-window throttle. Atomic per-key counters persist
 * across PHP sessions and workers. Never trusts client-supplied proxy headers.
 *
 * Migration 20261013_security_rate_limits_v2_3_7.sql is required before use.
 */
final class RateLimiter
{
    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    public static function clientIp(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }

    /** @return array{allowed: bool, retry_after: int} */
    public function hit(string $scope, string $identifier, int $limit, int $windowSeconds): array
    {
        $limit = max(1, min(1000, $limit));
        $windowSeconds = max(1, min(86400, $windowSeconds));
        $key = $this->hashKey($scope, $identifier);

        // MySQL serializes the INSERT/UPDATE of each unique key, preventing
        // concurrent requests from bypassing the configured threshold.
        $this->db->query(
            'INSERT INTO security_rate_limits (key_hash, hit_count, reset_at) '
            . 'VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND)) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'hit_count = IF(reset_at <= UTC_TIMESTAMP(), 1, hit_count + 1), '
            . 'reset_at = IF(reset_at <= UTC_TIMESTAMP(), '
            . 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), reset_at)',
            [$key, $windowSeconds, $windowSeconds]
        );
        $row = $this->db->fetchArray(
            'SELECT hit_count, GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), reset_at)) AS retry_after '
            . 'FROM security_rate_limits WHERE key_hash = ? LIMIT 1',
            [$key]
        )[0] ?? null;
        if (!is_array($row)) {
            throw new RuntimeException('Rate limiter state is unavailable.');
        }

        // Opportunistic cleanup prevents old identifiers accumulating forever.
        if (random_int(1, 100) === 1) {
            try {
                $this->db->query(
                    'DELETE FROM security_rate_limits WHERE reset_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) LIMIT 1000'
                );
            } catch (Throwable $e) {
                error_log('Rate-limit cleanup failed: ' . $e->getMessage());
            }
        }

        $allowed = (int)$row['hit_count'] <= $limit;
        return [
            'allowed' => $allowed,
            'retry_after' => $allowed ? 0 : max(1, (int)$row['retry_after']),
        ];
    }

    private function hashKey(string $scope, string $identifier): string
    {
        $secret = trim((string)AppConfig::get('RATE_LIMIT_SECRET', ''));
        if ($secret === '') {
            // Stable across sessions; a dedicated RATE_LIMIT_SECRET is strongly
            // recommended for production. Do not store raw IPs or emails.
            $secret = hash('sha256', DbConf::pass() . '|' . DbConf::name() . '|' . (string)AppConfig::appUrl());
        }
        return hash_hmac('sha256', $scope . "\0" . $identifier, $secret);
    }
}
