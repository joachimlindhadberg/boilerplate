<?php

declare(strict_types=1);
/** Isolated source/logic checks only. No DB, server writes, HTTP or .env access. */
$root = dirname(__DIR__);
require_once $root . '/core/bootstrap/php-requirement.php';
$tests = 0;
$check = static function (bool $ok, string $label) use (&$tests): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
    ++$tests;
    echo "PASS: {$label}\n";
};
$check(BOILERPLATE_MIN_PHP_VERSION_ID === 80300 && BOILERPLATE_MIN_PHP_VERSION === '8.3.0', 'minimum versions are coherent');
$check(!boilerplate_php_version_supported(80000), 'PHP 8.0 rejected');
$check(!boilerplate_php_version_supported(80199), 'PHP 8.1 rejected');
$check(!boilerplate_php_version_supported(80299), 'PHP 8.2 rejected');
$check(boilerplate_php_version_supported(80300), 'PHP 8.3 accepted at lower boundary');
$check(boilerplate_php_version_supported(80424), 'PHP 8.4 accepted');
$check(boilerplate_php_version_supported(80501), 'PHP 8.5 accepted');
$check(!boilerplate_php_version_supported('80300'), 'Noninteger versions cannot bypass the minimum');
$check(boilerplate_php_version_supported(PHP_VERSION_ID) === (PHP_VERSION_ID >= 80300), 'Real PHP version agrees with predicate');
$init = (string)file_get_contents($root . '/public_html/incl/init.php');
$install = (string)file_get_contents($root . '/public_html/install.php');
$check(strpos($init, "require_once ROOTPATH . '/core/bootstrap/php-requirement.php'") < strpos($init, "require_once COREPATH . '/classes/Autoload.php'") && strpos($init, 'boilerplate_require_php_runtime();') < strpos($init, "require_once COREPATH . '/classes/Autoload.php'"), 'Runtime guard precedes normal bootstrap');
$check(strpos($install, "require_once $" . "root . '/core/bootstrap/php-requirement.php'") < strpos($install, "require_once $" . "root . '/core/classes/Autoload.php'") && strpos($install, 'boilerplate_require_php_runtime();') < strpos($install, "require_once $" . "root . '/core/classes/Autoload.php'"), 'Standalone installer guard precedes autoload');
$siteHealth = (string)file_get_contents($root . '/core/classes/AdminTools.php');
$qa = (string)file_get_contents($root . '/core/cli/qa.php');
$check(str_contains($siteHealth, 'boilerplate_php_version_supported(PHP_VERSION_ID)') && str_contains($qa, 'boilerplate_php_version_supported(PHP_VERSION_ID)'), 'Site Health and CLI QA share same version predicate');
foreach (['modules/example/module.json', 'modules/forms/module.json', 'modules/gallery/module.json'] as $path) {
    $manifest = json_decode((string)file_get_contents($root . '/' . $path), true, 512, JSON_THROW_ON_ERROR);
    $check(($manifest['requires_php'] ?? null) === BOILERPLATE_MIN_PHP_VERSION, $path . ' agrees with runtime floor');
}
$check(str_contains((string)file_get_contents($root . '/module-starter/template/module.json.tpl'), '"requires_php": "8.3.0"'), 'Module starter declares same floor');
$check(str_contains((string)file_get_contents($root . '/docs/deployment/FIRST-TIME-INSTALL-RC2.md'), 'PHP 8.3+'), 'Fresh install docs updated');
$check(str_contains((string)file_get_contents($root . '/docs/deployment/PHP-RUNTIME-COMPATIBILITY.md'), 'PHP 8.3/8.4'), 'Compatibility document included');
echo "PHP Runtime Alignment 4A.6.9a: {$tests} PASS / 0 FAIL (source only)\n";

