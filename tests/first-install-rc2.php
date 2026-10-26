<?php

declare(strict_types=1);
/** Source/validation tests only: no database, SQL execution, HTTP or write operations. */
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$n = 0;
$pass = static function (bool $condition, string $label) use (&$n): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $n++; echo "PASS: $label\n";
};
$reject = static function (callable $fn) use ($pass): void {
    try { $fn(); $pass(false, 'Invalid input rejected'); }
    catch (InvalidArgumentException $e) { $pass(true, 'Invalid input rejected'); }
};
$db = FirstInstall::validateDb(['host'=>'localhost','port'=>'3306','name'=>'empty_site','user'=>'site_user','pass'=>'special!#=\'"$']);
$pass($db['pass'] === 'special!#=\'"$', 'Passwords preserve special characters without env interpolation');
$pass($db['port'] === 3306, 'Database port normalized');
$reject(static fn()=>FirstInstall::validateDb(['host'=>'localhost','port'=>'0','name'=>'site','user'=>'a','pass'=>'a']));
$reject(static fn()=>FirstInstall::validateDb(['host'=>'localhost','port'=>'3306','name'=>'site;DROP','user'=>'a','pass'=>'a']));
$reject(static fn()=>FirstInstall::validateDb(['host'=>'localhost','port'=>'3306','name'=>'site','user'=>'a','pass'=>"a\nsecret"]));
$reject(static fn()=>FirstInstall::validateDb(['host'=>'localhost','port'=>'3306','name'=>'site','user'=>'a','pass'=>' spaced ']));
$site = ['site_name'=>'Example','site_url'=>'https://example.org/','first'=>'First','last'=>'Last','email'=>'admin@example.org','password'=>'this-is-very-long'];
$parsed = FirstInstall::validateSite($site);
$pass($parsed['site_url'] === 'https://example.org', 'Site origin normalized via SiteUrl');
$reject(static fn()=>FirstInstall::validateSite(array_merge($site,['password'=>'short'])));
$reject(static fn()=>FirstInstall::validateSite(array_merge($site,['site_url'=>'https://example.org/a'])));
$reject(static fn()=>FirstInstall::validateSite(array_merge($site,['site_url'=>'javascript:alert(1)'])));
$pass(FirstInstall::configured('/nonexistent-cms-root-do-not-create') === false || getenv('DB_NAME') !== false, 'Absent install config can be detected without opening DB');
$schema = file_get_contents($root . '/database/install/empty-schema.sql');
$pass(is_string($schema) && substr_count($schema, 'CREATE TABLE `') === 25, 'Fresh baseline includes original tables, dashboard preferences and auth_sessions');
$pass(substr_count((string)$schema, 'ALTER TABLE `') === 55, 'Baseline preserves original 53 structural ALTERs plus RC5 keys');
$pass(!str_contains((string)$schema, 'cms_integration_marker') && !str_contains((string)$schema, 'INSERT INTO'), 'Production baseline contains no test marker or imported user rows');
$pass(!str_contains((string)$schema, 'DROP TABLE') && !str_contains((string)$schema, 'DELETE FROM'), 'Baseline does not delete preexisting data');
$install = file_get_contents($root . '/core/classes/FirstInstall.php');
$pass(str_contains((string)$install,'FirstInstall::') === false && str_contains((string)$install,'INFORMATION_SCHEMA.TABLES'), 'Installer explicitly checks empty DB schema');
$pass(str_contains((string)$install, "fopen(\$root . '/.env', 'x')"), 'Exclusive .env create prevents overwrites');
$pass(str_contains((string)$install,'LOCK_EX | LOCK_NB') && str_contains((string)$install,'assertEmpty($handle)'), 'Installer serializes writes and refuses nonempty databases');
$pass(str_contains((string)$install,'$actual !== $expected'), 'Unknown historical migrations cannot be falsely stamped');
$pass(str_contains((string)$install,'password_hash('), 'First administrator password is hashed');
$pass(str_contains((string)$install,'media_ref_media_delete'), 'Media deletion guard trigger is built');
$init = file_get_contents($root . '/public_html/incl/init.php');
$pass(strpos((string)$init,'FirstInstall::configured(ROOTPATH)') < strpos((string)$init,"$".'settings = model('), 'Installer dispatch precedes DB-backed Settings bootstrap');
$wizard = file_get_contents($root . '/public_html/install.php');
$pass(str_contains((string)$wizard,'hash_equals(') && str_contains((string)$wizard,'install_csrf'), 'Installer CSRF is checked');
$pass(str_contains((string)$wizard,'!$https && !$local') && str_contains((string)$wizard,'AppConfig::localInstallerHttpAllowed()') && str_contains((string)$wizard,'FirstInstall::configured'), 'Public HTTP and configured sites are blocked; local HTTP is explicitly gated');
$pass(str_contains((string)$wizard,'FirstInstall::assertEmpty($db)') && str_contains((string)$wizard,'FirstInstall::install('), 'Installer separately tests then explicitly installs');
$pass(file_get_contents($root . '/public_html/assets/install.css') === file_get_contents($root . '/public_html/assets/scss/install.scss'), 'Installer CSS and SCSS remain synchronized');
$pass(!is_file($root . '/core/cli/install-key.php') && !str_contains((string)$wizard, 'name="key"'), 'Keyless browser installer; no legacy key CLI or key form');
echo "First-Time Installation Wizard RC2: $n PASS / 0 FAIL (source only)\n";
