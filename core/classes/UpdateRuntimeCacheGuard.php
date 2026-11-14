<?php

declare(strict_types=1);

/**
 * S5.2 Stage4d3a private candidate. Invalidate changed PHP bytecode after
 * an atomic replacement, retirement, or rollback, before the update commits.
 * OPcache is not a cross-server / cross-pool cache fencing guarantee.
 */
final class UpdateRuntimeCacheGuard
{
    public static function afterMutation(string $absolutePath): void
    {
        if (!str_ends_with(strtolower($absolutePath), '.php')) return;
        if ($absolutePath === '' || $absolutePath[0] !== '/' || str_contains($absolutePath, "\0")) {
            throw new RuntimeException('Invalid PHP cache target.');
        }
        clearstatcache(true, $absolutePath);
        if (!extension_loaded('Zend OPcache')) return;
        if (!function_exists('opcache_get_status')) {
            throw new RuntimeException('Unable to inspect PHP opcode cache.');
        }
        // A disabled cache is safe; an inaccessible but enabled cache is not.
        $status = @opcache_get_status(false);
        if (!is_array($status)) {
            if (PHP_SAPI === 'cli' && !(bool)ini_get('opcache.enable_cli')) return;
            if (!(bool)ini_get('opcache.enable')) return;
            throw new RuntimeException('Cannot verify opcode cache state.');
        }
        if (($status['opcache_enabled'] ?? null) !== true) return;
        if (!function_exists('opcache_invalidate') || !@opcache_invalidate($absolutePath, true)) {
            throw new RuntimeException('Cannot invalidate replaced PHP source.');
        }
    }
}
