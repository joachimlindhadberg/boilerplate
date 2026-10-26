<?php
/**
 * Minimum PHP version for this V2.5.0 candidate.
 * Deliberately PHP 7.x-parseable: called BEFORE loading any PHP 8-only CMS code.
 * This is a deployment floor, not a claim that all PHP versions have passed
 * real hosting integration tests.
 */
if (!defined('BOILERPLATE_MIN_PHP_VERSION_ID')) {
    define('BOILERPLATE_MIN_PHP_VERSION_ID', 80300);
    define('BOILERPLATE_MIN_PHP_VERSION', '8.3.0');
}

if (!function_exists('boilerplate_php_version_supported')) {
    function boilerplate_php_version_supported($versionId)
    {
        return is_int($versionId) && $versionId >= BOILERPLATE_MIN_PHP_VERSION_ID;
    }
}

if (!function_exists('boilerplate_require_php_runtime')) {
    function boilerplate_require_php_runtime()
    {
        if (boilerplate_php_version_supported(PHP_VERSION_ID)) {
            return;
        }
        $message = 'Boilerplate CMS requires PHP ' . BOILERPLATE_MIN_PHP_VERSION
            . ' or later. This server runs PHP ' . PHP_VERSION . '.';
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message . "\n");
            exit(1);
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unsupported PHP version · Boilerplate</title></head><body><main><h1>The server PHP version is too old.</h1><p>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></main></body></html>';
        exit;
    }
}
