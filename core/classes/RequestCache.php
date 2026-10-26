<?php

declare(strict_types=1);

/**
 * A small, per-request memoization store for non-sensitive, read-mostly CMS
 * data. No filesystem/APCu/Redis persistence: a normal HTTP request starts
 * with an empty store. Authorization and user-specific content are never
 * cached here.
 */
final class RequestCache
{
    private static array $values = [];
    private static array $versions = [];

    /** Caches even null/false/empty results. Failed lookups are never stored. */
    public static function remember(string $namespace, string $key, callable $load): mixed
    {
        // Protect callers that do not explicitly handle transaction bypass.
        if (class_exists('Db', false) && Db::transactionActive()) {
            return $load();
        }
        if (array_key_exists($key, self::$values[$namespace] ?? [])) {
            return self::$values[$namespace][$key];
        }
        $value = $load();
        self::$values[$namespace][$key] = $value;
        return $value;
    }

    /** Invalidation is versioned so objects created earlier can refresh. */
    public static function invalidate(string $namespace): void
    {
        unset(self::$values[$namespace]);
        self::$versions[$namespace] = (self::$versions[$namespace] ?? 0) + 1;
    }

    public static function generation(string $namespace): int
    {
        return self::$versions[$namespace] ?? 0;
    }

    /** For test/worker teardown. Not needed in PHP-FPM request execution. */
    public static function clear(): void
    {
        foreach (array_unique([...array_keys(self::$values), ...array_keys(self::$versions)]) as $namespace) {
            self::$versions[$namespace] = (self::$versions[$namespace] ?? 0) + 1;
        }
        self::$values = [];
    }
}
