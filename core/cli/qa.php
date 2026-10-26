<?php

declare(strict_types=1);

/**
 * V2.4.0 read-only deployment diagnostics.
 *   php core/cli/qa.php
 *   php core/cli/qa.php --production
 *   php core/cli/qa.php --database
 * All checks are read-only. Database mode NEVER applies migrations.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ROOTPATH', dirname(__DIR__, 2));
require_once ROOTPATH . '/core/bootstrap/php-requirement.php';
require_once ROOTPATH . '/core/classes/Autoload.php';

$arguments = array_slice($argv, 1);
$allowed = ['--production', '--database', '--help'];
foreach ($arguments as $argument) {
    if (!in_array($argument, $allowed, true)) {
        fwrite(STDERR, "Unknown option: {$argument}\nUsage: php core/cli/qa.php [--production] [--database]\n");
        exit(2);
    }
}
if (in_array('--help', $arguments, true)) {
    echo "Boilerplate read-only QA: php core/cli/qa.php [--production] [--database]\n";
    exit(0);
}
$production = in_array('--production', $arguments, true);
$database = in_array('--database', $arguments, true);
$failures = 0;
$warnings = 0;
$passes = 0;

$report = static function (string $level, string $subject, string $detail = '') use (&$failures, &$warnings, &$passes): void {
    if ($level === 'FAIL') ++$failures;
    elseif ($level === 'WARN') ++$warnings;
    else ++$passes;
    echo sprintf("[%-4s] %s%s\n", $level, $subject, $detail === '' ? '' : ' — ' . $detail);
};

$report(boilerplate_php_version_supported(PHP_VERSION_ID) ? 'PASS' : 'FAIL', 'PHP version', PHP_VERSION . ' (minimum ' . BOILERPLATE_MIN_PHP_VERSION . ')');
foreach (['json', 'fileinfo', 'session', 'filter', 'hash'] as $extension) {
    $report(extension_loaded($extension) ? 'PASS' : 'FAIL', 'PHP extension: ' . $extension);
}
foreach (['mysqli', 'mbstring', 'openssl', 'phar', 'zlib'] as $extension) {
    $installed = extension_loaded($extension);
    $required = ($extension === 'mysqli' && $database);
    $report($installed ? 'PASS' : ($required ? 'FAIL' : 'WARN'), 'PHP extension: ' . $extension,
        !$installed ? ($extension === 'mysqli' ? 'Required for database operations; CLI PHP may differ from web PHP' : 'Some functionality may be unavailable') : '');
}

$paths = [
    'Frontend entry' => 'public_html/index.php',
    'Admin entry' => 'public_html/admin/index.php',
    'Core autoloader' => 'core/classes/Autoload.php',
    'Core migrations' => 'core/cli/migrate.php',
    'Media integrity CLI' => 'core/cli/media-index.php',
    'Admin stylesheet' => 'public_html/admin/assets/css/admin.css',
    'Admin SCSS' => 'public_html/admin/assets/scss/admin.scss',
    'Public stylesheet' => 'public_html/assets/css/style.css',
    'Public SCSS' => 'public_html/assets/scss/style.scss',
    'Webroot protection' => 'public_html/.htaccess',
    'Admin protection' => 'public_html/admin/.htaccess',
];
// Development-only tools are intentionally not shipped in the slim production candidate.
// This remains a read-only check: the packaged app has no tests directory.
if (is_dir(ROOTPATH . '/tests')) {
    $paths['Module developer tool'] = 'core/cli/module.php';
    $paths['Translation QA tool'] = 'core/cli/translations.php';
}
foreach ($paths as $name => $relative) {
    $report(is_file(ROOTPATH . '/' . $relative) ? 'PASS' : 'FAIL', $name);
}

$adminCss = @file_get_contents(ROOTPATH . '/public_html/admin/assets/css/admin.css');
$adminScss = @file_get_contents(ROOTPATH . '/public_html/admin/assets/scss/admin.scss');
$report($adminCss !== false && $adminCss === $adminScss ? 'PASS' : 'FAIL',
    'Admin SCSS / compiled CSS sync', 'Identical sources are required by this CMS build');

$webRules = (string)@file_get_contents(ROOTPATH . '/public_html/.htaccess');
foreach (['uploads|assets/uploads', 'incl(?:/|$)', 'admin/(?:incl|views)'] as $pattern) {
    $report(str_contains($webRules, $pattern) ? 'PASS' : 'FAIL', 'Apache access restriction: ' . $pattern);
}

// Read the release number from source without bootstrapping the web application.
$bootstrapSource = (string)@file_get_contents(ROOTPATH . '/public_html/incl/init.php');
$versionMatch = [];
$coreVersion = preg_match("~BOILERPLATE_VERSION', '([0-9]+\\.[0-9]+\\.[0-9]+(?:\\.[0-9]+)?)'~", $bootstrapSource, $versionMatch)
    ? $versionMatch[1] : '';
$report($coreVersion !== '' ? 'PASS' : 'FAIL', 'CMS version metadata', $coreVersion);
$moduleDirectories = glob(ROOTPATH . '/modules/*', GLOB_ONLYDIR) ?: [];
sort($moduleDirectories, SORT_STRING);
$report(count($moduleDirectories) > 0 ? 'PASS' : 'WARN', 'Discovered modules', (string)count($moduleDirectories));
foreach ($moduleDirectories as $folder) {
    try {
        $meta = ModuleManifestValidator::load($folder);
        $report('PASS', 'Module manifest: ' . $meta['slug'], (string)$meta['version']);
        $requires = (string)($meta['requires'] ?? '');
        if ($requires !== '' && $coreVersion !== '' && version_compare($coreVersion, $requires, '<')) {
            $report('FAIL', 'Module CMS compatibility: ' . $meta['slug']);
        }
        $requiresPhp = (string)($meta['requires_php'] ?? '');
        if ($requiresPhp !== '' && version_compare(PHP_VERSION, $requiresPhp, '<')) {
            $report('FAIL', 'Module PHP compatibility: ' . $meta['slug']);
        }
    } catch (Throwable $error) {
        $report('FAIL', 'Module manifest: ' . basename($folder), $error->getMessage());
    }
}
// Validate each JSON localization bundle without booting the web application.
$localeFiles = ['core' => ROOTPATH . '/core/lang/translations.json'];
foreach ($moduleDirectories as $directory) {
    if (is_file($directory . '/module.json')) {
        $localeFiles['module ' . basename($directory)] = $directory . '/lang/translations.json';
    }
}
foreach ($localeFiles as $name => $file) {
    $audit = TranslationCatalogValidator::inspect($file);
    $report($audit['errors'] === [] ? 'PASS' : 'FAIL', 'Admin translations: ' . $name,
        $audit['errors'] === [] ? $audit['keys'] . ' keys / 5 locales' : implode('; ', array_slice($audit['errors'], 0, 5)));
}

try {
    $refs = ModuleMediaReferences::all();
    $report('PASS', 'Module media declarations', count($refs) . ' validated reference(s)');
} catch (Throwable $error) {
    $report('FAIL', 'Module media declarations', $error->getMessage());
}
try {
    $migrations = (new MigrationRunner(new class extends Db {
        public function __construct() {} // discover() does not use a database connection.
    }))->discover();
    $report('PASS', 'Migration file discovery', count($migrations) . ' migration file(s) found');
} catch (Throwable $error) {
    $report('FAIL', 'Migration file discovery', $error->getMessage());
}

if ($production) {
    $report(AppConfig::isProduction() ? 'PASS' : 'FAIL', 'APP_ENV=production');
    // Without --database the authoritative URL cannot be verified. Do not
    // claim production HTTPS compliance solely from APP_URL.
    if (!$database) {
        $report('WARN', 'Effective HTTPS origin', 'Cannot verify database Website URL without the read-only --database option');
    }
    $report(AppConfig::bool('CANONICAL_REDIRECT', true) ? 'PASS' : 'WARN', 'Canonical redirect configuration');
    $secret = (string)AppConfig::get('RATE_LIMIT_SECRET', '');
    $report(strlen($secret) >= 32 ? 'PASS' : 'WARN', 'RATE_LIMIT_SECRET',
        strlen($secret) < 32 ? 'Set a distinct stable secret of at least 32 characters' : 'Configured');
    $report(is_dir(ROOTPATH . '/storage/logs') && is_writable(ROOTPATH . '/storage/logs') ? 'PASS' : 'WARN',
        'Private error-log storage', 'Ensure logs remain outside public_html');
    $report('WARN', 'Production-only checks', 'Verify cron, backups/restores, TLS, headers, upload permissions and real browser workflows manually');
}

if ($database) {
    if (!extension_loaded('mysqli')) {
        $report('FAIL', 'Database connectivity', 'Current CLI PHP lacks mysqli; use the PHP binary configured by the server/MAMP');
    } else {
        try {
            $db = new Db();
            if ($production) {
                $effective = SiteUrl::resolve(new Settings($db));
                $report(str_starts_with($effective['url'], 'https://') ? 'PASS' : 'FAIL',
                    'Effective HTTPS origin', $effective['source'] . ': ' . $effective['url']);
            }
            $status = (new MigrationRunner($db))->status();
            $issues = array_values(array_filter($status, static fn(array $row): bool => $row['state'] !== 'applied'));
            $report(count($issues) === 0 ? 'PASS' : 'FAIL', 'Read-only migration status',
                count($issues) . ' migration(s) require attention');
            foreach ($issues as $issue) {
                $report('WARN', 'Migration: ' . $issue['id'], $issue['state']);
            }
            if (count($issues) === 0) {
                try {
                    $mediaState = (new MediaReferenceIndex($db))->status();
                    $report($mediaState['dirty'] ? 'WARN' : 'PASS', 'Media reference index',
                        $mediaState['dirty'] ? 'Pending refresh; use media-index.php --rebuild' : $mediaState['references'] . ' indexed reference(s)');
                } catch (Throwable $error) {
                    $report('FAIL', 'Media reference index', 'Unavailable; inspect migration status and logs');
                }
            }
            $db->close();
        } catch (Throwable $error) {
            // Avoid outputting raw DB exception details that might expose credentials/paths.
            $report('FAIL', 'Database connectivity / migrations', 'Unavailable; inspect private PHP logs and server configuration');
        }
    }
}

printf("\nSummary: %d passed, %d warning(s), %d failure(s). No changes were made.\n", $passes, $warnings, $failures);
exit($failures > 0 ? 1 : 0);
