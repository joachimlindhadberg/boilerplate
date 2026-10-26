<?php

declare(strict_types=1);

/** Source + isolated scratch-directory tests; never accesses real MySQL or the user's sites. */
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$tests = 0;
$pass = static function (bool $condition, string $description) use (&$tests): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $description\n"); exit(1); }
    $tests++;
    echo "PASS: $description\n";
};
$installer = file_get_contents($root . '/public_html/install.php');
$preflight = file_get_contents($root . '/core/classes/InstallerPreflight.php');
$pass(is_string($preflight) && !str_contains($preflight, 'new mysqli') && !str_contains($preflight, '->query('), 'Preflight performs no database queries or connections');
$pass(!str_contains((string)$preflight, 'tempnam(') && !str_contains((string)$preflight, 'file_put_contents(')
    && !str_contains((string)$preflight, 'mkdir(') && !str_contains((string)$preflight, 'chmod('), 'Preflight performs no filesystem writes or permission mutations');
$pass(str_contains((string)$installer, "InstallerPreflight::requireReady(\$root, __DIR__")
    && substr_count((string)$installer, 'InstallerPreflight::requireReady(') === 2, 'Preflight checks on database test and finish');
$pass(strpos((string)$installer, '// Recheck immediately before any irreversible CREATE TABLE') < strpos((string)$installer, 'FirstInstall::install('), 'Finish preflight precedes schema DDL');
$pass(str_contains((string)$installer, 'aria-disabled="true"') && str_contains((string)$installer, 'install__checks'), 'Installer exposes actionable results and disables unsupported submission');
$pass(str_contains((string)$installer, 'No database changes were made by these checks'), 'Installer explains read-only preflight');
$css = file_get_contents($root . '/public_html/assets/install.css');
$scss = file_get_contents($root . '/public_html/assets/scss/install.scss');
$pass($css === $scss, 'Installer CSS and SCSS identical');
$pass(str_contains((string)$css, '.install__check-state.is-fail'), 'Required failures visibly distinguished');
$pass(str_contains((string)$preflight, "false,\n            'Cannot be verified from PHP alone."), 'Apache effective enforcement is not falsely reported PASS');
$pass(str_contains((string)$preflight, 'A connection test cannot safely guarantee all DDL/trigger rights'), 'Database TRIGGER permissions explicitly remain unverified');
$installClass = file_get_contents($root . '/core/classes/FirstInstall.php');
$pass(str_contains((string)$installer, 'FirstInstall::assertInnoDbSupported($db)'), 'Connection test checks InnoDB support before continuing');
$pass(strpos((string)$installClass, 'self::assertInnoDbSupported($handle);') < strpos((string)$installClass, 'self::createSchema($handle);'),
    'InnoDB capability test precedes all installation DDL');
$pass(str_contains((string)$installClass, 'INFORMATION_SCHEMA.ENGINES') && str_contains((string)$installClass, "['YES', 'DEFAULT']"),
    'Database engine capability checked read-only; YES and DEFAULT recognized');

$base = sys_get_temp_dir() . '/cms-preflight-test-' . bin2hex(random_bytes(8));
@mkdir($base, 0700);
$private = $base . '/private';
$public = $private . '/public_html';
foreach ([$private, $public, $public . '/admin', $public . '/uploads'] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0700)) throw new RuntimeException('Could not create isolated test fixture.');
}
foreach (['.htaccess','admin/.htaccess','uploads/.htaccess'] as $relative) {
    file_put_contents($public . '/' . $relative, '# fixture - never deployed');
}
try {
    $a = InstallerPreflight::checks($private, $public, $public);
    $byId = [];
    foreach ($a as $check) { $byId[$check['id']] = $check; }
    $pass($byId['webroot']['ok'] === true, 'Exact isolated webroot accepted');
    $pass($byId['root-write']['ok'] === true && $byId['storage']['ok'] === true, 'Writable project root permits absent storage folder');
    $pass($byId['upload-uploads']['ok'] === true && !isset($byId['upload-assets-uploads']), 'Only current writable uploads are required');
    $pass(!isset($byId['legacy-upload-security']), 'No legacy directory is required on a new site');
    $pass($byId['apache-.htaccess']['ok'] === true && $byId['apache-admin-.htaccess']['ok'] === true
        && $byId['apache-uploads-.htaccess']['ok'] === true && !isset($byId['apache-assets-uploads-.htaccess']),
        'Only the three current Apache files are required on a new site');
    // Existing legacy tree remains optional, but if present its policy is required.
    mkdir($public . '/assets/uploads', 0700, true);
    $legacy = InstallerPreflight::checks($private, $public, $public);
    $legacyById = array_column($legacy, null, 'id');
    $pass($legacyById['legacy-upload-security']['ok'] === false && !InstallerPreflight::ready($legacy),
        'Existing legacy tree without policy fails closed');
    file_put_contents($public . '/assets/uploads/.htaccess', '# fixture - not secure');
    $legacy = InstallerPreflight::checks($private, $public, $public);
    $legacyById = array_column($legacy, null, 'id');
    $pass($legacyById['legacy-upload-security']['ok'] === false, 'Mismatched legacy security policy rejected');
    copy($public . '/uploads/.htaccess', $public . '/assets/uploads/.htaccess');
    $legacy = InstallerPreflight::checks($private, $public, $public);
    $legacyById = array_column($legacy, null, 'id');
    $pass($legacyById['legacy-upload-security']['ok'] === true, 'Existing protected legacy media is accepted');
    $pass($byId['apache-effective']['required'] === false && $byId['apache-effective']['ok'] === false, 'Cannot assert runtime Apache enforcement from PHP');
    $pass($byId['database-privileges']['required'] === false && $byId['database-privileges']['ok'] === false, 'No made-up database privilege result');
    foreach (['mysqli', 'mbstring', 'fileinfo', 'openssl', 'json', 'session', 'filter', 'hash'] as $name) {
        $pass($byId['ext-' . $name]['ok'] === extension_loaded($name), $name . ' extension status matches actual CLI runtime');
    }
    $pass($byId['php']['ok'] === (PHP_VERSION_ID >= 80300), 'Minimum PHP runtime aligned');
    $pass(InstallerPreflight::ready([['id'=>'fake','label'=>'fake','ok'=>true,'detail'=>'','required'=>true]]), 'Ready with passed required controls');
    $pass(!InstallerPreflight::ready([['id'=>'fake','label'=>'fake','ok'=>false,'detail'=>'','required'=>true]]), 'Fails with missing required control');
    $pass(InstallerPreflight::ready([['id'=>'fake','label'=>'fake','ok'=>false,'detail'=>'','required'=>false]]), 'Advice-only checks do not falsely block installation');
    $pass(!file_exists($private . '/.env') && !file_exists($private . '/storage'), 'Preflight has not created config or storage');
    $pass(InstallerPreflight::checks($private, $public, $base)[array_search('webroot', array_column($a, 'id'), true)]['ok'] === false,
        'Rejects webroot pointed at private parent');
    unlink($public . '/uploads/.htaccess');
    $b = InstallerPreflight::checks($private, $public, $public);
    $c = [];
    foreach ($b as $check) $c[$check['id']] = $check;
    $pass($c['apache-uploads-.htaccess']['ok'] === false && $c['apache-uploads-.htaccess']['required'] === true,
        'Missing upload defense blocks readiness');
    $pass(!InstallerPreflight::ready($b), 'Missing file fails host readiness regardless of PHP extensions');
    try {
        InstallerPreflight::requireReady($private, $public, $public);
        $pass(false, 'Missing required preflight must throw before DDL');
    } catch (DomainException $e) {
        $pass(str_contains($e->getMessage(), 'No database changes'), 'Blocked request tells owner no database changes made');
    }
    $pass(!is_file($private . '/.env'), 'Failed preflight does not create .env');
} finally {
    foreach (['.htaccess','admin/.htaccess','uploads/.htaccess','assets/uploads/.htaccess'] as $relative) @unlink($public . '/' . $relative);
    foreach ([$public . '/assets/uploads', $public . '/assets', $public . '/uploads', $public . '/admin', $public, $private, $base] as $dir) @rmdir($dir);
}
$pass(!is_dir($base), 'Isolated test fixture cleaned up');
echo "4A.6.9d1 Hosting Installer Preflight: $tests PASS / 0 FAIL\n";
