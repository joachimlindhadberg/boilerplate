<?php

declare(strict_types=1);

/**
 * Resolve the public Tools URL independently of Apache MultiViews.
 *
 * Some Apache installations negotiate /admin/tools/data to tools.php with
 * PATH_INFO=/data before mod_rewrite sees it. In that case the expected
 * mode=data query parameter is never produced. Interpret the original,
 * unmodified request path and refuse conflicts rather than defaulting to
 * the overview or trusting a conflicting query parameter.
 */
final class AdminToolsRoute
{
    private const MODES = ['data', 'site-health', 'activity', 'redirects', 'update'];

    public static function resolve(string $requestUri, mixed $queryMode): ?string
    {
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = substr($path, 0, -1);
        }

        // Existing direct PHP and historical URLs may use query-string modes.
        // GET aliases are redirected by .htaccess whenever rewrite is available.
        if (in_array($path, ['/admin/tools.php', '/admin/tools', '/admin/vaerktoejer'], true)) {
            if ($queryMode === null) {
                return '';
            }
            if (!is_string($queryMode)) {
                return null;
            }
            $mode = strtolower(trim($queryMode));
            return $mode === '' || in_array($mode, self::MODES, true) ? $mode : null;
        }

        foreach (self::MODES as $mode) {
            if ($path !== '/admin/tools/' . $mode && $path !== '/admin/vaerktoejer/' . $mode) {
                continue;
            }
            if ($queryMode !== null && (!is_string($queryMode) || strtolower(trim($queryMode)) !== $mode)) {
                return null;
            }
            return $mode;
        }

        // Never silently display the overview for an unrecognised nested URL.
        return null;
    }
}
