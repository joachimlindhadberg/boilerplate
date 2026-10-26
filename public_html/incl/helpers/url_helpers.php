<?php
/**
 * ======================================================
 * URL HELPERS
 * ======================================================
 */

function get_current_url(): string {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string)(parse_url($requestUri, PHP_URL_PATH) ?: '/');
    $path = '/' . ltrim($path, '/');

    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
    return get_site_origin() . $path;
}

function get_url_segments(): array {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = trim((string)(parse_url($requestUri, PHP_URL_PATH) ?? ''), '/');
    return $path === '' ? ['forside'] : explode('/', $path);
}

function get_site_origin(): string {
    global $settings;
    return SiteUrl::origin(isset($settings) && $settings instanceof Settings ? $settings : null);
}


/** Append file modification time to local static assets for cache busting. */
function asset_url(string $path): string {
    $path = '/' . ltrim($path, '/');
    $file = defined('PUBLICPATH') ? PUBLICPATH . $path : null;

    if ($file !== null && is_file($file)) {
        $mtime = filemtime($file);
        if ($mtime !== false) {
            return $path . '?v=' . $mtime;
        }
    }

    return $path;
}
