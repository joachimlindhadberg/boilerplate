<?php

/**
 * Runtime application configuration.
 *
 * Production secrets belong in environment variables or an optional
 * .env file in the project root (outside public_html). Never commit .env.
 */
final class AppConfig
{
    private static bool $loaded = false;
    private static array $fileValues = [];

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        $root = defined('ROOTPATH') ? ROOTPATH : dirname(__DIR__, 2);
        $file = $root . '/.env';

        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));
            if ($key === '' || !preg_match('/^[A-Z0-9_]+$/i', $key)) {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$fileValues[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();

        $value = getenv($key);
        if ($value !== false) {
            return (string)$value;
        }

        if (array_key_exists($key, $_ENV)) {
            return (string)$_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER) && is_scalar($_SERVER[$key])) {
            return (string)$_SERVER[$key];
        }

        return self::$fileValues[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }

    public static function environment(): string
    {
        return strtolower(trim((string)self::get('APP_ENV', 'development')));
    }

    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    /** Return configured public origin without trailing slash, or null. */
    public static function appUrl(): ?string
    {
        return SiteUrl::normalizeOrigin(self::get('APP_URL', ''));
    }

    /**
     * The frontend proxy must overwrite X-Forwarded-Proto on EVERY request.
     * A forwarded scheme is authoritative only if the immediate network peer
     * matches TRUSTED_PROXY_IPS. Malformed/missing forwarded values fail closed,
     * including when TLS is also active on the backend connection.
     */
    public static function requestIsHttps(): bool
    {
        if (self::bool('TRUST_PROXY_HEADERS', false)
            && self::isTrustedProxy((string)($_SERVER['REMOTE_ADDR'] ?? ''))) {
            $forwarded = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
            return $forwarded === 'https'; // "http", missing, lists and malformed values are NOT HTTPS.
        }

        $native = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
        return in_array($native, ['on', '1', 'true', 'https'], true);
    }

    /**
     * Match the immediate REMOTE_ADDR against exact IPs or explicit CIDR ranges.
     * An empty list and catch-all CIDRs never create a trusted proxy. Neither
     * forwarded client IPs nor hostname-based DNS are used for trust decisions.
     */
    private static function isTrustedProxy(string $peer): bool
    {
        $peerIp = @inet_pton(trim($peer));
        $configured = trim((string)self::get('TRUSTED_PROXY_IPS', ''));
        if ($peerIp === false || $configured === '') {
            return false;
        }
        $entries = preg_split('/[\s,;]+/', $configured, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($entries)) {
            return false;
        }
        foreach ($entries as $entry) {
            $parts = explode('/', $entry, 2);
            if (count($parts) > 1 && ($parts[1] === '' || !ctype_digit($parts[1]))) {
                continue;
            }
            $rangeIp = @inet_pton($parts[0]);
            if ($rangeIp === false || strlen($rangeIp) !== strlen($peerIp)) {
                continue;
            }
            $maxBits = strlen($rangeIp) * 8;
            $prefix = count($parts) > 1 ? (int)$parts[1] : $maxBits;
            if ($prefix <= 0 || $prefix > $maxBits) {
                continue;
            }
            $whole = intdiv($prefix, 8);
            $remaining = $prefix % 8;
            if (substr($peerIp, 0, $whole) !== substr($rangeIp, 0, $whole)) {
                continue;
            }
            if ($remaining !== 0) {
                $mask = (0xff << (8 - $remaining)) & 0xff;
                if ((ord($peerIp[$whole]) & $mask) !== (ord($rangeIp[$whole]) & $mask)) {
                    continue;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Plain HTTP may be explicitly enabled ONLY for an isolated loopback-bound
     * development virtual host using a reserved local hostname. A loopback
     * REMOTE_ADDR alone is never evidence that the public client is local:
     * a reverse proxy can connect to PHP over 127.0.0.1.
     *
     * Set INSTALL_ALLOW_LOCAL_HTTP=true in local Apache/PHP host configuration.
     * Public hosting must use HTTPS (natively or through a trusted TLS proxy).
     */
    public static function localInstallerHttpAllowed(): bool
    {
        if (!self::bool('INSTALL_ALLOW_LOCAL_HTTP', false)) {
            return false;
        }
        if (!in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true)
            || !in_array((string)($_SERVER['SERVER_ADDR'] ?? ''), ['127.0.0.1', '::1'], true)) {
            return false;
        }
        // Any proxy-related headers make the request ineligible for local HTTP.
        foreach (['HTTP_FORWARDED', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_FOR',
                  'HTTP_X_FORWARDED_HOST', 'HTTP_X_REAL_IP'] as $header) {
            if (array_key_exists($header, $_SERVER)) {
                return false;
            }
        }
        $authority = (string)($_SERVER['HTTP_HOST'] ?? '');
        $host = parse_url('//' . $authority, PHP_URL_HOST);
        $hostname = strtolower((string)$host);
        $serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));
        if ($hostname === '' || $hostname !== $serverName) {
            return false;
        }
        return $hostname === 'localhost' || str_ends_with($hostname, '.localhost')
            || str_ends_with($hostname, '.test') || $hostname === '127.0.0.1'
            || $hostname === '::1';
    }

    /**
     * Redirect to the resolved canonical origin when canonical redirects are enabled.
     * This handles both HTTPS and preferred host without trusting Host headers.
     */
    public static function enforceCanonicalRequest(?string $origin = null): void
    {
        if (PHP_SAPI === 'cli' || !self::bool('CANONICAL_REDIRECT', self::isProduction())) {
            return;
        }

        // Existing callers that omit the argument remain compatible. The
        // database-backed resolver is still used once Settings is initialized.
        if ($origin === null) {
            $settings = $GLOBALS['settings'] ?? null;
            $origin = SiteUrl::origin($settings instanceof Settings ? $settings : null);
        }
        $origin = SiteUrl::normalizeOrigin($origin);
        if ($origin === null) {
            throw new RuntimeException('A valid canonical Website URL is required.');
        }

        $target = parse_url($origin);
        if (!is_array($target)) {
            return;
        }

        $targetHost = strtolower((string)($target['host'] ?? ''));
        $targetScheme = strtolower((string)($target['scheme'] ?? 'http'));
        $requestScheme = self::requestIsHttps() ? 'https' : 'http';
        // Compare the full origin, including non-standard ports. Ignoring the
        // port previously let a request to host:8080 bypass an origin set to
        // host:8443 (or vice versa). The Host header supplies the incoming
        // public port; never use it to construct a redirect destination.
        $hostParts = parse_url('//' . (string)($_SERVER['HTTP_HOST'] ?? ''));
        $requestHost = is_array($hostParts) ? strtolower((string)($hostParts['host'] ?? '')) : '';
        $targetPort = (int)($target['port'] ?? ($targetScheme === 'https' ? 443 : 80));
        $requestPort = (int)((is_array($hostParts) ? ($hostParts['port'] ?? null) : null)
            ?? ($requestScheme === 'https' ? 443 : 80));

        if ($requestHost === $targetHost && $requestScheme === $targetScheme
            && $requestPort === $targetPort) {
            return;
        }

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if ($uri === '' || $uri[0] !== '/' || strpbrk($uri, "\r\n") !== false) {
            $uri = '/';
        }

        // Preserve request method/body when a non-GET request needs to move
        // to the canonical origin (e.g. a POST arriving on the wrong port).
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $status = in_array($method, ['GET', 'HEAD'], true) ? 301 : 308;
        header('Location: ' . $origin . $uri, true, $status);
        exit;
    }
}
