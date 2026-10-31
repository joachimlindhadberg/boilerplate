<?php

declare(strict_types=1);

/**
 * Dashboard-only, session-throttled notification about an official stable
 * GitHub release. This is NOT release authentication, install authorization,
 * a background scheduler, or an installer. Never accepts an untrusted URL.
 */
final class UpdateAdminNotification
{
    private const SESSION_KEY = 'boilerplate_admin_update_notice_v1';
    private const SUCCESS_TTL = 3600;
    private const FAILURE_TTL = 300;

    /**
     * @param array<string,mixed> $session Only the authenticated admin session.
     * @param ?callable(string):array<string,mixed> $lookup Inject in isolated tests only.
     * @return array{status:string,latest_version:string}
     */
    public static function check(string $installedVersion, array &$session, ?callable $lookup = null, ?int $now = null, string $channel = 'stable'): array
    {
        $none = ['status' => 'not_available', 'latest_version' => ''];
        if (!self::validVersion($installedVersion)) return $none;
        $channel = UpdateReleaseVersion::channel($channel);
        $now ??= time();
        if ($now < 0) return $none;
        $cached = $session[self::SESSION_KEY] ?? null;
        if (is_array($cached)
            && ($cached['installed'] ?? null) === $installedVersion
            && ($cached['channel'] ?? 'stable') === $channel
            && is_int($cached['checked_at'] ?? null)
            && $cached['checked_at'] <= $now
            && is_int($cached['ttl'] ?? null)
            && in_array($cached['ttl'], [self::SUCCESS_TTL, self::FAILURE_TTL], true)
            && ($now - $cached['checked_at']) < $cached['ttl']
            && in_array($cached['status'] ?? null, ['available', 'not_available'], true)
            && is_string($cached['latest_version'] ?? null)) {
            $version = $cached['latest_version'];
            if ($cached['status'] === 'available' && self::validVersion($version)
                && version_compare($version, $installedVersion, '>')) {
                return ['status' => 'available', 'latest_version' => $version];
            }
            if ($cached['status'] === 'not_available' && $version === '') return $none;
        }
        $status = $none;
        $ttl = self::FAILURE_TTL;
        try {
            $result = $lookup === null
                ? (new UpdateReleaseLookup())->check($installedVersion, $channel)
                : $lookup($installedVersion);
            if (is_array($result)) {
                $candidate = $result['latest_version'] ?? null;
                $state = $result['status'] ?? null;
                if ($state === 'available' && is_string($candidate)
                    && self::validVersion($candidate)
                    && version_compare($candidate, $installedVersion, '>')) {
                    $status = ['status' => 'available', 'latest_version' => $candidate];
                    $ttl = self::SUCCESS_TTL;
                } elseif (in_array($state, ['current', 'no_release'], true)) {
                    $ttl = self::SUCCESS_TTL;
                }
            }
        } catch (Throwable $ignored) {
            // Fail silently: a GitHub outage must never block admin login.
        }
        $session[self::SESSION_KEY] = [
            'installed' => $installedVersion, 'channel' => $channel,
            'checked_at' => $now,
            'ttl' => $ttl,
            'status' => $status['status'],
            'latest_version' => $status['latest_version'],
        ];
        return $status;
    }

    private static function validVersion(string $value): bool
    {
        return UpdateReleaseVersion::valid($value);
    }
}
