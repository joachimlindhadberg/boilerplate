<?php
/**
 * ======================================================
 * INIT BOOTSTRAP
 * ======================================================
 *
 * - Path definitions
 * - Class autoloader
 * - Production/runtime configuration
 * - Global helper API
 * - Settings runtime
 * - Timezone + locale setup
 * - Secure session defaults
 *
 * ======================================================
 */

define('PUBLICPATH', dirname(__DIR__));
define('ROOTPATH', dirname(PUBLICPATH));
define('COREPATH', ROOTPATH . '/core');
define('INCLPATH', PUBLICPATH . '/incl');
define('BOILERPLATE_VERSION', '2.5.0');

/*
|--------------------------------------------------------------------------
| PHP RUNTIME REQUIREMENT (shared with browser installer and QA)
|--------------------------------------------------------------------------
| This guard runs before loading autoloaders, modules or database code.
*/
require_once ROOTPATH . '/core/bootstrap/php-requirement.php';
boilerplate_require_php_runtime();

// Fresh installation is dispatched BEFORE database-backed CMS bootstrap.
// An existing .env or DB_* environment configuration never enters the wizard,
// even if the configured database is offline or incomplete.
require_once COREPATH . '/classes/Autoload.php';
header_remove('X-Powered-By');
if (PHP_SAPI !== 'cli' && !FirstInstall::configured(ROOTPATH)) {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['GET', 'HEAD'], true)) {
        header('Location: /install.php', true, 302);
    } else {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Installation is not complete yet. Open /install.php.';
    }
    exit;
}


/*
|--------------------------------------------------------------------------
| ENVIRONMENT / ERROR HANDLING
|--------------------------------------------------------------------------
*/
error_reporting(E_ALL);

if (AppConfig::isProduction()) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');

    $configuredLog = trim((string)AppConfig::get('APP_LOG_PATH', 'storage/logs/php-error.log'));
    $logPath = str_starts_with($configuredLog, '/')
        ? $configuredLog
        : ROOTPATH . '/' . ltrim($configuredLog, '/');
    $logDir = dirname($logPath);

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', $logPath);
    }

    set_exception_handler(static function (Throwable $e): void {
        error_log(sprintf(
            "Uncaught %s: %s in %s:%d\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>An error occurred · Boilerplate</title></head><body><main><h1>An error occurred.</h1><p>Please try again shortly.</p></main></body></html>';
    });
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

header_remove('X-Powered-By');

/*
|--------------------------------------------------------------------------
| SECURE SESSION DEFAULTS
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    // Cookie security must follow the request that is actually being served.
    // APP_URL can point at the future HTTPS production origin while a local
    // development environment still runs over HTTP. Marking that local cookie
    // Secure would make the browser discard it on the next request and create
    // an apparent login loop.
    $secureCookie = AppConfig::requestIsHttps();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/*
|--------------------------------------------------------------------------
| GLOBAL FUNCTIONS API
|--------------------------------------------------------------------------
*/
require_once INCLPATH . '/functions.php';

/*
|--------------------------------------------------------------------------
| SETTINGS RUNTIME
|--------------------------------------------------------------------------
*/
$settings = model('settings');

// The database Website URL is authoritative when present. Resolve canonical
// origin only after settings are available, but before sending output/headers.
// SiteUrl itself remains usable without a database by the future installer.
if (AppConfig::bool('CANONICAL_REDIRECT', AppConfig::isProduction()) && PHP_SAPI !== 'cli') {
    AppConfig::enforceCanonicalRequest(SiteUrl::origin($settings));
}


// Admin localization is independent of the public website's locale setting.
Translator::boot((string)$settings->get('admin_language', 'en'));

/** Translate an admin UI key; escape at the HTML output boundary. */
function t(string $key, array $parameters = []): string
{
    return Translator::get($key, $parameters);
}


/*
|--------------------------------------------------------------------------
| TIMEZONE + LOCALE
|--------------------------------------------------------------------------
*/
date_default_timezone_set(
    (string)$settings->get('timezone', 'Europe/Copenhagen')
);

setlocale(
    LC_ALL,
    (string)$settings->get('language', 'en_US')
);
