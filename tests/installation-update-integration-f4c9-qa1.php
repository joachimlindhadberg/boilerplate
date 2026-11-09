<?php

declare(strict_types=1);

/**
 * F4c.9: source/contract integration regression only, executed in CLI.
 * Never connects to the CMS database, runs an installer or writes to repo.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$pass = 0;
$check = static function (bool $ok, string $label) use (&$pass): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    ++$pass;
    echo 'PASS: ' . $label . "\n";
};

$paths = [
    'install' => 'core/classes/FirstInstall.php',
    'schema' => 'database/install/empty-schema.sql',
    'migration' => 'database/updates/20261105_000_page_access.php',
    'runner' => 'core/classes/MigrationRunner.php',
    'gate' => 'core/classes/UpdateDurableRecoveryGate.php',
    'update' => 'core/classes/UpdateOneClickInstaller.php',
    'auth' => 'core/classes/Auth.php',
    'sessions' => 'core/classes/SessionManager.php',
    'bootstrap' => 'public_html/incl/init.php',
    'tools' => 'public_html/admin/tools.php',
];
$sources = [];
foreach ($paths as $label => $relative) {
    $raw = file_get_contents($root . '/' . $relative);
    $check(is_string($raw) && $raw !== '', "Core {$label} source readable");
    $sources[$label] = $raw;
}
$check(class_exists(FirstInstall::class) && method_exists(FirstInstall::class, 'install'), 'Real fresh-site install API exists');
$check(class_exists(MigrationRunner::class) && method_exists(MigrationRunner::class, 'status'), 'Real read-only migration API exists');
$check(class_exists(UpdateDurableRecoveryGate::class), 'Durable recovery gate loads');
$check(class_exists(Auth::class) && class_exists(SessionManager::class), 'Real session-backed Auth classes load');
$check(Auth::roleCan('admin', 'tools.manage'), 'Admin Tools capability present');
$check(!Auth::roleCan('editor', 'tools.manage'), 'Editor cannot manage Tools');
$check(!Auth::roleCan('user', 'tools.manage'), 'Ordinary user cannot manage Tools');
$check(!Auth::roleCan('admin', 'undefined.recovery'), 'Invented recovery authorization remains denied');

$dummy = new class extends Db { public function __construct() {} };
$discovered = (new MigrationRunner($dummy, $root))->discover();
$check(count($discovered) === 8, 'Eight migration files discovered by real Core');
$check(isset($discovered['core:20261105_000_page_access']), 'Page access migration is in discovered inventory');
foreach ($discovered as $id => $migration) {
    $check(is_file($migration['path']) && !is_link($migration['path'])
        && hash_file('sha256', $migration['path']) === $migration['sha256'],
        'Migration source SHA-256 valid: ' . $id);
}
$check(str_contains($sources['schema'], 'CREATE TABLE `page_access` ('), 'Fresh schema includes actual page_access table');
$check(str_contains($sources['install'], "'core:20261105_000_page_access'"), 'Fresh installer stamps page_access migration');
$check(str_contains($sources['install'], 'if ($actual !== $expected) throw new RuntimeException('), 'Unknown migrations still fail closed');
$check(str_contains($sources['install'], 'self::assertEmpty($handle);'), 'Fresh installer rejects nonempty DB');
$check(str_contains($sources['install'], 'self::stampMigrations($handle, $root);'), 'Fresh installer still registers migration checksums');
$check(str_contains($sources['bootstrap'], 'UpdateDurableRecoveryGate::guardHttp(ROOTPATH)'), 'Recovery gate precedes web bootstrap');
$check(str_contains($sources['tools'], "define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage')"), 'Tools enforces admin capability');
$check(str_contains($sources['tools'], "'restore_interrupted_update'"), 'File-only recovery action remains explicit');
$check(str_contains($sources['update'], "(\$review['new_migrations'] ?? -1) !== 0"), 'One-click refuses migration-bearing release');
$check(str_contains($sources['auth'], '$this->sessions->valid('), 'Authenticated session verified against SQL-backed store');
$check(method_exists(SessionManager::class, 'revokeAll'), 'Server-side auth-session revocation available');

// This test's recovery journal exists only inside a fresh private /tmp fixture.
$tmp = realpath('/tmp');
$check(is_string($tmp) && is_dir($tmp), 'Private temporary QA parent exists');
$fixture = $tmp . '/bp-f4c9-contract-' . bin2hex(random_bytes(8));
if (!mkdir($fixture, 0700)) throw new RuntimeException('Unable to create isolated QA fixture');
$marker = null;
try {
    $gate = new UpdateDurableRecoveryGate($fixture);
    $check($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Fresh private recovery state clear');
    if (!mkdir($fixture . '/storage', 0700) || !mkdir($fixture . '/storage/update-recovery', 0700)) {
        throw new RuntimeException('Unable to create private recovery vault');
    }
    $marker = $fixture . '/storage/update-recovery/.db-update-pending-' . bin2hex(random_bytes(16));
    $bytes = '{"format":"f4c9-private-test","blocked":true}';
    if (file_put_contents($marker, $bytes, LOCK_EX) !== strlen($bytes)) {
        throw new RuntimeException('Unable to write isolated QA marker');
    }
    chmod($marker, 0600);
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Persistent private database marker blocks CMS');
    $entry = $fixture . '/public_html/admin/tools.php';
    if (!mkdir($fixture . '/public_html', 0700) || !mkdir($fixture . '/public_html/admin', 0700)
        || file_put_contents($entry, "<?php // private test\n") === false) {
        throw new RuntimeException('Private Tools stub unavailable');
    }
    $check($gate->permitsHttp($entry, '/admin/tools/update', 'GET'), 'Recovery permits protected Updates GET');
    $check(!$gate->permitsHttp($entry, '/admin/tools/update', 'POST', [], ['action'=>'install_official_update']), 'Recovery rejects update POST');
    $check(!$gate->permitsHttp($entry, '/admin/tools/update', 'POST', [], ['action'=>'restore_interrupted_update']), 'Database recovery never authorizes file-only restore');
    $check(!$gate->permitsHttp($entry, '/admin/tools/data', 'GET'), 'Recovery rejects other Tools routes');
    $check(file_get_contents($marker) === $bytes, 'Recovery checks leave marker byte-identical');
} finally {
    if ($marker !== null && is_file($marker)) unlink($marker);
    $entry = $fixture . '/public_html/admin/tools.php';
    if (is_file($entry)) unlink($entry);
    @rmdir($fixture . '/public_html/admin');
    @rmdir($fixture . '/public_html');
    @rmdir($fixture . '/storage/update-recovery');
    @rmdir($fixture . '/storage');
    @rmdir($fixture);
}
echo "F4c.9 Integration Contracts: {$pass} PASS / 0 FAIL (private synthetic fixture; no MySQL)\n";
