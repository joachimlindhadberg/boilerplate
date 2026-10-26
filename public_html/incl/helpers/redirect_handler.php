<?php

/**
 * Apply core redirects before normal page rendering.
 *
 * Only safe, idempotent frontend requests are redirected. Query parameters
 * from the incoming request are preserved when the configured destination
 * does not already define its own query string.
 */
function handle_core_redirect(): bool
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return false;
    }

    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = Redirects::normalizeRequestPath($requestUri);

    try {
        $redirects = new Redirects();
        $redirect = $redirects->matchActive($path);
        if (!$redirect) {
            return false;
        }

        $target = trim((string)($redirect['target_url'] ?? ''));
        $type = (int)($redirect['redirect_type'] ?? 301);
        if ($target === '' || !in_array($type, Redirects::validTypes(), true)) {
            return false;
        }
        // Stored/imported rules can predate today's validation. Never emit an
        // unsafe Location header just because a database row already exists.
        try {
            Redirects::normalizeTargetUrl($target);
        } catch (Throwable $e) {
            error_log('Rejected invalid stored redirect: ' . $path);
            return false;
        }

        $targetPath = str_starts_with($target, '/')
            ? Redirects::normalizeRequestPath((string)(parse_url($target, PHP_URL_PATH) ?? '/'))
            : null;
        if ($targetPath !== null && $targetPath === $path) {
            error_log('Redirect loop prevented for source: ' . $path);
            return false;
        }

        // Preserve tracking/query parameters unless the configured target has
        // an explicit query string of its own.
        $requestQuery = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
        $targetQuery = (string)(parse_url($target, PHP_URL_QUERY) ?? '');
        if ($requestQuery !== '' && $targetQuery === '') {
            $fragment = '';
            $fragmentPos = strpos($target, '#');
            if ($fragmentPos !== false) {
                $fragment = substr($target, $fragmentPos);
                $target = substr($target, 0, $fragmentPos);
            }
            $target .= '?' . $requestQuery . $fragment;
        }

        try {
            $redirects->registerHit((int)($redirect['id'] ?? 0));
        } catch (Throwable $e) {
            error_log('Redirect hit counter: ' . $e->getMessage());
        }

        header('Location: ' . $target, true, $type);
        return true;
    } catch (Throwable $e) {
        // A redirect subsystem failure must not take the public site down.
        error_log('Core redirect handler: ' . $e->getMessage());
        return false;
    }
}
