<?php

declare(strict_types=1);

/** F4c.2: isolated fake-site tests. Never connects to a CMS database. */
require_once dirname(__DIR__) . '/core/classes/UpdateDurableRecoveryGate.php';
require_once dirname(__DIR__) . '/core/classes/UpdateMaintenanceGate.php';
require_once dirname(__DIR__) . '/core/classes/UpdateCliWriterAdmission.php';

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $title) use (&$pass, &$fail): void {
    if ($ok) { ++$pass; echo "PASS: $title\n"; }
    else { ++$fail; echo "FAIL: $title\n"; }
};
$base = sys_get_temp_dir() . '/bp-f4c2-' . bin2hex(random_bytes(12));
if (!mkdir($base, 0700)) throw new RuntimeException('Cannot create temporary QA root.');
$root = $base . '/site';
mkdir($root, 0700);
mkdir($root . '/public_html', 0700);
mkdir($root . '/public_html/admin', 0700);
file_put_contents($root . '/public_html/admin/tools.php', '<?php');
file_put_contents($root . '/public_html/admin/index.php', '<?php');
$tools = $root . '/public_html/admin/tools.php';
$index = $root . '/public_html/admin/index.php';
$vault = $root . '/storage/update-recovery';
$gate = new UpdateDurableRecoveryGate($root);
$purge = static function (string $path) use (&$purge): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        $purge($entry->getPathname());
    }
    rmdir($path);
};
try {
    $check($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Absent recovery vault: normal operation allowed');
    $check($gate->permitsHttp($tools, '/admin/tools/update', 'GET', ['mode' => 'update']), 'Clear state preserves normal update GET');
    $check($gate->permitsHttp($index, '/admin', 'POST', ['mode' => 'dashboard-cards']), 'Clear state preserves existing admin POST');

    mkdir($root . '/storage', 0700);
    mkdir($vault, 0700);
    $check($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Private empty recovery vault: no false block');
    $v1 = $vault . '/.one-click-pending-' . str_repeat('a', 32);
    mkdir($v1, 0700);
    $manifest = $v1 . '/manifest.json';
    file_put_contents($manifest, json_encode([
        'format' => 'boilerplate-one-click-journal-v1',
        'backup_id' => 'recovery-' . str_repeat('b', 32),
        'version' => '2.5.2', 'plan' => [],
    ], JSON_THROW_ON_ERROR));
    chmod($manifest, 0600);
    $check($gate->status() === UpdateDurableRecoveryGate::FILE, 'Existing on-disk v1 file journal recognized');
    $writerBlocked = false;
    try { $writer = UpdateCliWriterAdmission::acquire($root); $writer->release(); }
    catch (RuntimeException $e) { $writerBlocked = true; }
    $check($writerBlocked, 'Cooperative CLI writer is refused when recovery remains pending');
    $check(!$gate->permitsHttp($index, '/', 'GET'), 'Frontend blocked with file recovery');
    $check(!$gate->permitsHttp($index, '/admin/sider', 'GET'), 'Pages handler blocked with file recovery');
    $check(!$gate->permitsHttp($index, '/admin', 'POST', ['mode' => 'dashboard-cards']), 'Dashboard writes blocked while recovery pending');
    $check($gate->permitsHttp($index, '/admin', 'GET'), 'Login page remains reachable');
    $check($gate->permitsHttp($index, '/admin/index.php?login=ok', 'GET'), 'Login post-redirect can reach Core recovery entry');
    $check($gate->permitsHttp($index, '/admin/index.php?mode=authenticate', 'POST', ['mode' => 'authenticate']), 'Existing authentication POST remains reachable');
    $check(!$gate->permitsHttp($index, '/admin/index.php?mode=change-password', 'POST', ['mode' => 'change-password']), 'No unrestricted administrative POST bypass');
    $check(!$gate->permitsHttp($index, '/admin?mode=update-notice', 'GET', ['mode' => 'update-notice']), 'AJAX and other admin modes blocked');
    $check(!$gate->permitsHttp($index, '/admin/index.php?mode=logout', 'POST', ['mode' => 'logout']), 'Logout action is not a recovery bypass');
    $check($gate->permitsHttp($tools, '/admin/tools/update', 'GET', ['mode' => 'update']), 'Read-only protected update page reachable');
    $check($gate->permitsHttp($tools, '/admin/vaerktoejer/update', 'GET', ['mode' => 'update']), 'Legacy update URL admitted');
    $check($gate->permitsHttp($tools, '/admin/tools.php?mode=update', 'GET', ['mode' => 'update']), 'Direct tools.php update mode admitted');
    $check(!$gate->permitsHttp($tools, '/admin/tools/data', 'GET', ['mode' => 'data']), 'Tools data import page blocked');
    $check(!$gate->permitsHttp($tools, '/admin/tools/update?mode=data', 'GET', ['mode' => 'data']), 'Conflicting Tools mode blocked');
    $check(!$gate->permitsHttp($tools, '/admin/tools/update', 'POST', ['mode' => 'update'], ['action' => 'install_official_update']), 'New update POST blocked until recovery ends');
    $check(!$gate->permitsHttp($tools, '/admin/tools/update', 'POST', ['mode' => 'update'], ['action' => 'check_for_updates']), 'Update discovery POST blocked during recovery');
    $check($gate->permitsHttp($tools, '/admin/tools/update', 'POST', ['mode' => 'update'], ['action' => 'restore_interrupted_update']), 'Only file-restore action reaches existing auth/CSRF controller');
    $check(!$gate->permitsHttp($tools, '/admin/tools/update', 'POST', ['mode' => 'update'], ['action' => ['restore_interrupted_update']]), 'Array action cannot bypass POST rule');
    $check(!$gate->permitsHttp($index, '/admin/tools/update', 'POST', ['mode' => 'update'], ['action' => 'restore_interrupted_update']), 'Wrong PHP handler cannot impersonate recovery controller');
    $check(!$gate->permitsHttp($tools, '/admin/tools/update', 'DELETE', ['mode' => 'update']), 'Unsupported HTTP methods blocked');

    // Independent PHP processes prove the indicator is not an in-memory lock.
    $probe = 'require $argv[1]; echo (new UpdateDurableRecoveryGate($argv[2]))->status();';
    $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($probe)
        . ' ' . escapeshellarg(dirname(__DIR__) . '/core/classes/UpdateDurableRecoveryGate.php')
        . ' ' . escapeshellarg($root);
    $lines = []; $exit = 1;
    exec($cmd . ' 2>&1', $lines, $exit);
    $check($exit === 0 && trim(implode("\n", $lines)) === UpdateDurableRecoveryGate::FILE,
        'Separate PHP process sees persistent v1 recovery after parent exit');

    // A corrupt marker cannot authorize automatic file restore.
    file_put_contents($manifest, '{not-json');
    $check($gate->status() === UpdateDurableRecoveryGate::MANUAL, 'Invalid v1 manifest fails closed');
    $check(!$gate->permitsHttp($tools, '/admin/tools/update', 'POST', ['mode' => 'update'], ['action' => 'restore_interrupted_update']), 'Malformed file journal disables restore POST');
    unlink($manifest);
    $check($gate->status() === UpdateDurableRecoveryGate::MANUAL, 'Incomplete v1 pending directory fails closed');
    file_put_contents($manifest, json_encode(['format' => 'boilerplate-one-click-journal-v1', 'backup_id' => 'recovery-' . str_repeat('b', 32), 'plan' => []]));
    chmod($manifest, 0600);
    $check($gate->status() === UpdateDurableRecoveryGate::FILE, 'Valid file marker restored only in synthetic fixture');
    mkdir($vault . '/.one-click-pending-' . str_repeat('c', 32), 0700);
    $check($gate->status() === UpdateDurableRecoveryGate::MANUAL, 'Multiple pending journals fail closed');
    rmdir($vault . '/.one-click-pending-' . str_repeat('c', 32));

    $db = $vault . '/.db-update-pending-' . str_repeat('d', 32);
    file_put_contents($db, 'pending'); chmod($db, 0600);
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Reserved DB journal blocks even alongside valid file journal');
    $check(!$gate->permitsHttp($tools, '/admin/tools/update', 'POST', ['mode' => 'update'], ['action' => 'restore_interrupted_update']), 'Database journal never authorizes file-only restore');
    $check($gate->permitsHttp($tools, '/admin/tools/update', 'GET', ['mode' => 'update']), 'Admin may read protected manual-recovery status');
    unlink($db);
    $db = $vault . '/.db-update-blocked-' . str_repeat('e', 32);
    file_put_contents($db, 'blocked');
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Persistent DB blocked marker recognized');
    unlink($db);
    file_put_contents($vault . '/.db-update-unknown', 'invalid');
    $check($gate->status() === UpdateDurableRecoveryGate::MANUAL, 'Unknown DB journal cannot fail open');
    unlink($vault . '/.db-update-unknown');

    $check($gate->status() === UpdateDurableRecoveryGate::FILE, 'File recovery remains pending after DB marker removed');
    unlink($manifest);rmdir($v1);
    $check($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Removing verified synthetic pending marker clears request block');
    $writer = UpdateCliWriterAdmission::acquire($root);
    $check($writer instanceof UpdateMaintenanceGate, 'Cooperative CLI writer works normally without pending recovery');
    $writer->release();
    mkdir($vault . '/.one-click-pending-NOT-A-VALID-ID', 0700);
    $check($gate->status() === UpdateDurableRecoveryGate::MANUAL, 'Malformed pending marker naming fails closed');
    rmdir($vault . '/.one-click-pending-NOT-A-VALID-ID');

    chmod($vault, 0777);
    $check($gate->status() === UpdateDurableRecoveryGate::MANUAL, 'Insecure recovery vault fails closed');
    chmod($vault, 0700);
    $check($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Valid private vault returns clear');
    $alias = $base . '/alias';
    symlink($root, $alias);
    $bad = false;
    try { new UpdateDurableRecoveryGate($alias); } catch (RuntimeException $e) { $bad = true; }
    $check($bad, 'Root symlink refused');
    unlink($alias);

    $check(str_contains(file_get_contents(dirname(__DIR__) . '/public_html/incl/init.php'),
        'UpdateDurableRecoveryGate::guardHttp(ROOTPATH);'), 'Bootstrap checks persistent gate before CMS DB settings');
    $check(str_contains(file_get_contents(dirname(__DIR__) . '/public_html/admin/index.php'),
        "admin_redirect('/admin/tools/update');"), 'Authenticated login entry redirects to protected recovery page');
    $check(str_contains(file_get_contents(dirname(__DIR__) . '/public_html/admin/tools.php'),
        "new UpdateDurableRecoveryGate()"), 'Update controller rechecks recovery state under exclusive lock');
    $check(str_contains(file_get_contents(dirname(__DIR__) . '/public_html/admin/tools.php'),
        "'recoveryBlocked' => \$recoveryBlocked"), 'Core update UI receives persistent status');
    $check(str_contains(file_get_contents(dirname(__DIR__) . '/public_html/admin/views/tools/update.php'),
        'updates.recovery_manual_help'), 'Core UI has manual recovery message');
    $check(str_contains(file_get_contents(dirname(__DIR__) . '/core/classes/UpdateCliWriterAdmission.php'),
        'UpdateDurableRecoveryGate::CLEAR'), 'Cooperative CLI writers also respect persistent recovery state');

    $translations = json_decode((string)file_get_contents(dirname(__DIR__) . '/core/lang/translations.json'), true, 32, JSON_THROW_ON_ERROR);
    $labels = $translations['updates.recovery_manual_help'] ?? [];
    $check(is_array($labels) && count($labels) === 5
        && array_keys($labels) === ['en', 'da', 'de', 'sv', 'nb']
        && !in_array('', $labels, true), 'New recovery description translated into all five Core languages');

} finally {
    $purge($base);
}
echo "F4c.2 Durable Recovery Gate QA1: $pass PASS / $fail FAIL (isolated synthetic fixture; no CMS/SQL/Git/HTTP writes)\n";
exit($fail > 0 ? 1 : 0);
