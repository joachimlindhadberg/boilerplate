<?php

declare(strict_types=1);

/** Isolated read-only QA: fake DB, no HTTP, no database, no session writes. */
$root = dirname(__DIR__);
require_once $root . '/core/classes/Db.php';
require_once $root . '/core/classes/RateLimiter.php';
require_once $root . '/core/classes/PageAccess.php';

// A fixed secret avoids environment-specific key derivation; never accesses real credentials.
if (!class_exists('AppConfig', false)) {
    final class AppConfig {
        public static function get(string $key, mixed $fallback = null): mixed {
            return $key === 'RATE_LIMIT_SECRET' ? 'isolated-qa-secret-not-for-production' : $fallback;
        }
    }
}

final class RateLimiterQA1aDb extends Db
{
    public array $entries = [];
    public int $clock = 100000;
    public bool $failReads = false;

    public function __construct() {} // No real mysqli connection.

    public function query(string $sql, array $params = []): void
    {
        if (str_starts_with($sql, 'INSERT INTO security_rate_limits')) {
            [$key, $window] = $params;
            $row = $this->entries[$key] ?? null;
            if ($row === null || $row['reset'] <= $this->clock) {
                $this->entries[$key] = ['hits' => 1, 'reset' => $this->clock + (int)$window];
            } else {
                $this->entries[$key]['hits']++;
            }
            return;
        }
        if ($sql === 'DELETE FROM security_rate_limits WHERE key_hash = ?') {
            unset($this->entries[$params[0]]);
            return;
        }
        if (str_starts_with($sql, 'DELETE FROM security_rate_limits WHERE reset_at <')) return;
        throw new RuntimeException('Unexpected QA query');
    }

    public function fetchArray(string $sql, array $params = []): array
    {
        if ($this->failReads) throw new RuntimeException('Simulated DB outage');
        if (!str_starts_with($sql, 'SELECT hit_count, GREATEST(')) {
            throw new RuntimeException('Unexpected QA read');
        }
        $row = $this->entries[$params[0]] ?? null;
        return $row === null ? [] : [[
            'hit_count' => $row['hits'],
            'retry_after' => max(0, $row['reset'] - $this->clock),
        ]];
    }
}

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $name) use (&$passed, &$failed): void {
    if ($condition) { $passed++; echo 'PASS: ' . $name . "\n"; }
    else { $failed++; fwrite(STDERR, 'FAIL: ' . $name . "\n"); }
};
$db = new RateLimiterQA1aDb();
$limiter = new RateLimiter($db);
$scope = 'page_password:17:' . hash('sha256', password_hash('demo-password', PASSWORD_DEFAULT));
$ip = '192.0.2.10';
$assert($limiter->status($scope, $ip, 5)['allowed'], 'No attempts: allowed');
$assert(count($db->entries) === 0, 'Reading limiter does not create an attempt');
for ($i=1; $i<=4; $i++) {
    $limiter->hit($scope, $ip, 5, 900);
    $assert($limiter->status($scope, $ip, 5)['allowed'], "$i wrong attempts remain below threshold");
}
$limiter->hit($scope, $ip, 5, 900);
$blocked = $limiter->status($scope, $ip, 5);
$assert(!$blocked['allowed'], 'Fifth wrong attempt triggers lockout');
$assert($blocked['retry_after'] === 900, 'Lockout reports remaining 15 minutes');
$assert($limiter->status($scope, '192.0.2.11', 5)['allowed'], 'Another IP is unaffected');
$assert($limiter->status('page_password:18:other', $ip, 5)['allowed'], 'Another page is unaffected');
$rotated = 'page_password:17:' . hash('sha256', password_hash('new-password', PASSWORD_DEFAULT));
$assert($limiter->status($rotated, $ip, 5)['allowed'], 'Password rotation starts a fresh bucket');
$assert(!$limiter->status($scope, $ip, 5)['allowed'], 'Old password bucket stays blocked');
$db->clock += 899;
$assert(!$limiter->status($scope, $ip, 5)['allowed'], 'Lockout lasts until the window expires');
$db->clock += 1;
$assert($limiter->status($scope, $ip, 5)['allowed'], 'Expired window releases lockout');
$limiter->hit($rotated, $ip, 5, 900);
$assert(count($db->entries) >= 2, 'A failed attempt is recorded in the new bucket');
$limiter->clear($rotated, $ip);
$assert($limiter->status($rotated, $ip, 5)['allowed'], 'Correct password can clear prior failed attempts');
$db->failReads = true;
$failedClosed = false;
try { $limiter->status($scope, $ip, 5); } catch (RuntimeException) { $failedClosed = true; }
$assert($failedClosed, 'Database read failure never grants access');

$access = (string)file_get_contents($root . '/core/classes/PageAccess.php');
$limiterSource = (string)file_get_contents($root . '/core/classes/RateLimiter.php');
$posCheck = strpos($access, '$limiter->status($scope, $identifier, 5)');
$posVerify = strpos($access, 'password_verify($value, $hash)');
$posHit = strpos($access, '$limiter->hit($scope, $identifier, 5, 900)');
$assert($posCheck !== false && $posVerify !== false && $posHit !== false && $posCheck < $posVerify && $posVerify < $posHit,
    'Limit checked before password verification; only wrong passwords hit counter');
$assert(str_contains($access, '$scope = \'page_password:'), 'Each password version has an isolated limit scope');
$assert(str_contains($access, '$limiter->clear($scope, $identifier)'), 'Success clears own failed attempts');
$assert(str_contains($access, 'hash(\'sha256\', $hash)'), 'Password version is derived from stored hash');
$assert(str_contains($access, "header('Retry-After: '"), 'Lockouts preserve Retry-After HTTP header');
$assert(str_contains($limiterSource, 'public function status(') && str_contains($limiterSource, 'public function clear('),
    'RateLimiter additive API preserves existing hit() contract');
$assert(str_contains($access, 'hash_equals($_SESSION[self::CSRF_KEY], $token)'), 'Existing session CSRF remains');
$assert(str_contains($access, 'session_regenerate_id(true)'), 'Existing session rotation remains');
$assert(str_contains($access, '$_SESSION[self::GRANTS_KEY][(string)$page[\'id\']]'), 'Access gate preserves page-scoped grants');

printf("Pages Access Rate Limit QA1a: %d PASS / %d FAIL (fake DB, no writes)\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
