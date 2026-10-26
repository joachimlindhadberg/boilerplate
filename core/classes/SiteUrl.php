<?php

/**
 * Public site origin resolution. This class does not open a DB connection:
 * the installer may use it before Settings (and its table) exist.
 * The optional Settings dependency is only read when explicitly supplied.
 */
final class SiteUrl
{
    /** Accept a complete HTTP(S) origin only; no credentials, path or query. */
    public static function normalizeOrigin(?string $input): ?string
    {
        $value = trim((string)$input);
        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)
            || !filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            return null;
        }
        $scheme = strtolower((string)$parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (isset($parts['port']) && (int)$parts['port'] < 1) {
            return null;
        }
        $host = strtolower((string)$parts['host']);
        if ($host === '') {
            return null;
        }
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        return $scheme . '://' . $host . $port;
    }

    /**
     * Pure selection function, usable by DB-free regression tests.
     * @return array{url:string,source:string}
     */
    public static function choose(
        ?string $databaseUrl,
        ?string $appUrl,
        bool $production,
        ?string $requestHost = null,
        bool $requestHttps = false
    ): array {
        $primary = self::normalizeOrigin($databaseUrl);
        if ($primary !== null) {
            return ['url' => $primary, 'source' => 'database'];
        }
        $fallback = self::normalizeOrigin($appUrl);
        if ($fallback !== null) {
            return ['url' => $fallback, 'source' => 'APP_URL'];
        }
        if ($production) {
            throw new RuntimeException('A valid Website URL or APP_URL is required in production.');
        }

        // Host headers are not trusted as a production URL. Development only.
        $scheme = $requestHttps ? 'https' : 'http';
        $host = trim((string)$requestHost);
        $url = self::normalizeOrigin($scheme . '://' . $host);
        return ['url' => $url ?? 'http://localhost', 'source' => 'request'];
    }

    /**
     * Resolve from an already initialized Settings instance when available.
     * Database errors in this optional lookup do not prevent APP_URL fallback;
     * normal application bootstrap may still require a working database.
     * @return array{url:string,source:string}
     */
    public static function resolve(?Settings $settings = null): array
    {
        $databaseUrl = null;
        if ($settings !== null) {
            try {
                $databaseUrl = (string)$settings->get('site_url', '');
            } catch (Throwable $error) {
                error_log('Website URL setting unavailable; using configured fallback.');
            }
        }
        return self::choose(
            $databaseUrl,
            AppConfig::appUrl(),
            AppConfig::isProduction(),
            (string)($_SERVER['HTTP_HOST'] ?? ''),
            AppConfig::requestIsHttps()
        );
    }

    public static function origin(?Settings $settings = null): string
    {
        return self::resolve($settings)['url'];
    }
}
