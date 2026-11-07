<?php

declare(strict_types=1);

/**
 * Shared, session-throttled notification about an official
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
        $channel = $channel === 'all' ? 'all' : UpdateReleaseVersion::channel($channel);
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
            && is_string($cached['latest_version'] ?? null)
            && ($channel !== 'all' || is_string($cached['source_status'] ?? null))) {
            $version = $cached['latest_version'];
            if ($cached['status'] === 'available' && self::validVersion($version)
                && version_compare($version, $installedVersion, '>')) {
                return ['status' => 'available', 'latest_version' => $version];
            }
            if ($cached['status'] === 'not_available' && $version === '') return $none;
        }
        $status = $none;
        $ttl = self::FAILURE_TTL;
        $sourceStatus = 'error';
        try {
            if ($lookup !== null) {
                $result = $lookup($installedVersion);
            } elseif ($channel === 'all') {
                $choices = (new UpdateReleaseChoices())->find($installedVersion);
                $best = null;
                foreach ($choices['releases'] as $candidate) {
                    if ($best === null || version_compare($candidate['version'], $best, '>')) {
                        $best = $candidate['version'];
                    }
                }
                $result = $best !== null
                    ? ['status' => 'available', 'latest_version' => $best]
                    : ['status' => $choices['status'], 'latest_version' => ''];
            } else {
                $result = (new UpdateReleaseLookup())->check($installedVersion, $channel);
            }
            if (is_array($result)) {
                $sourceStatus = is_string($result['status'] ?? null) ? $result['status'] : 'error';
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
            'source_status' => $status['status'] === 'available' ? 'available'
                : (in_array($sourceStatus, ['current', 'no_release'], true) ? $sourceStatus : 'error'),
        ];
        return $status;
    }

    /**
     * Read-only summary shared by Dashboard, Tools navigation/card and Updates.
     * It reuses check()'s per-session TTL and never authorizes installation.
     * An unavailable GitHub lookup must not be displayed as "up to date".
     * @param array<string,mixed> $session
     * @return array{status:string,latest_version:string}
     */
    public static function overview(string $installedVersion, array &$session, ?int $now = null): array
    {
        $summary = self::check($installedVersion, $session, null, $now, 'all');
        if ($summary['status'] === 'available') return $summary;
        $cache = $session[self::SESSION_KEY] ?? null;
        $source = is_array($cache) && ($cache['installed'] ?? null) === $installedVersion
            && ($cache['channel'] ?? null) === 'all' ? ($cache['source_status'] ?? null) : null;
        return ['status' => in_array($source, ['current', 'no_release'], true) ? $source : 'error',
            'latest_version' => ''];
    }

    /**
     * Manual recheck primes the same session cache without another GitHub call.
     * Only an authenticated tools handler should pass verified discovery data.
     * @param array<string,mixed> $session
     * @param array<string,mixed> $choices
     */
    public static function rememberChoices(string $installedVersion, array &$session, array $choices, ?int $now = null): void
    {
        if (!self::validVersion($installedVersion)) return;
        $now ??= time();
        if ($now < 0) return;
        $source = (string)($choices['status'] ?? 'error');
        $best = '';
        if ($source === 'available' && is_array($choices['releases'] ?? null)) {
            foreach ($choices['releases'] as $candidate) {
                if (!is_array($candidate)) continue;
                $kind = $candidate['kind'] ?? null;
                $version = $candidate['version'] ?? null;
                if (!in_array($kind, ['stable', 'beta'], true) || !is_string($version)
                    || !self::validVersion($version)
                    || UpdateReleaseVersion::isPrerelease($version) !== ($kind === 'beta')
                    || version_compare($version, $installedVersion, '<=')) continue;
                if ($best === '' || version_compare($version, $best, '>')) $best = $version;
            }
        }
        if ($source === 'available' && $best === '') $source = 'error';
        if (!in_array($source, ['available', 'current', 'no_release'], true)) $source = 'error';
        $session[self::SESSION_KEY] = [
            'installed' => $installedVersion, 'channel' => 'all', 'checked_at' => $now,
            'ttl' => $source === 'error' ? self::FAILURE_TTL : self::SUCCESS_TTL,
            'status' => $best !== '' ? 'available' : 'not_available',
            'latest_version' => $best,
            'source_status' => $source,
        ];
    }

    private static function validVersion(string $value): bool
    {
        return UpdateReleaseVersion::valid($value);
    }
}
