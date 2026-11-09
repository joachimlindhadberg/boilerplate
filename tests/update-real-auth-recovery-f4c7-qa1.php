<?php

declare(strict_types=1);

/** F4c.7 QA1: read-only, direct Core contract verification; never opens Db. */
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';

$passed = 0;
$check = static function (bool $value, string $label) use (&$passed): void {
    if (!$value) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    ++$passed;
    echo 'PASS: ' . $label . "\n";
};
$check(PHP_SAPI === 'cli', 'Static contract QA only permits CLI');
$check(is_file($root . '/core/classes/Auth.php'), 'Real Core Auth source exists');
$check(is_file($root . '/core/classes/SessionManager.php'), 'Real SQL-backed SessionManager source exists');
$check(is_file($root . '/core/classes/Db.php'), 'Real mysqli Db source exists');
$check(is_file($root . '/public_html/admin/index.php'), 'Real CMS login controller exists');
$check(is_file($root . '/public_html/admin/tools.php'), 'Real CMS Tools controller exists');
$check(is_file($root . '/public_html/admin/incl/functions.php'), 'Real Core CSRF helper exists');
$check(Auth::roleCan('admin', 'tools.manage'), 'Core grants admin tools.manage');
$check(!Auth::roleCan('editor', 'tools.manage'), 'Core denies editor tools.manage');
$check(!Auth::roleCan('user', 'tools.manage'), 'Core denies ordinary user tools.manage');
$check(!Auth::roleCan('admin', 'undefined.recovery'), 'Core denies invented recovery capability');
$check(method_exists(Auth::class, 'login'), 'Real login implementation is present');
$check(method_exists(Auth::class, 'validateSessionUser'), 'Auth verifies server-side session on every request');
$check(method_exists(SessionManager::class, 'revokeAll'), 'Real SQL-backed session revocation exists');
$check(AdminToolsRoute::resolve('/admin/tools/update', null) === 'update', 'Canonical update route resolves');
$check(AdminToolsRoute::resolve('/admin/tools/data', null) !== 'update', 'Other Tools route cannot impersonate Updates');
$check(AdminToolsRoute::resolve('/admin/tools/update', 'data') === null, 'Conflicting Tools mode rejected');
$check(class_exists(UpdateBrowserRecoveryAuthorizationQa::class), 'F4c.6 policy source retained');

$authCode = file_get_contents($root . '/core/classes/Auth.php');
$csrfCode = file_get_contents($root . '/public_html/admin/incl/functions.php');
$initCode = file_get_contents($root . '/public_html/incl/init.php');
$toolsCode = file_get_contents($root . '/public_html/admin/tools.php');
$installCode = file_get_contents($root . '/core/classes/UpdateOneClickInstaller.php');
$check(is_string($authCode) && str_contains($authCode, '$this->sessions->valid('), 'Real Auth verifies SQL-backed session token');
$check(is_string($csrfCode) && str_contains($csrfCode, 'hash_equals($stored, $token)'), 'Session CSRF uses constant-time comparison');
$check(is_string($initCode) && str_contains($initCode, 'UpdateDurableRecoveryGate::guardHttp(ROOTPATH)'), 'Durable gate executes before CMS bootstrap');
$check(is_string($toolsCode) && str_contains($toolsCode, "define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage')"), 'Tools requires Core tools.manage');
$check(is_string($toolsCode) && str_contains($toolsCode, "'restore_interrupted_update'"), 'File restore action stays explicit');
$check(is_string($installCode) && str_contains($installCode, "(\$review['new_migrations'] ?? -1) !== 0"), 'One-click still refuses new migrations');
$actualMigrations = array_merge(glob($root . '/database/updates/*.php') ?: [],
    glob($root . '/modules/*/migrations/*.php') ?: []);
$check(count($actualMigrations) === 8, 'Pinned Core tree exposes eight migration files');
$firstInstall = file_get_contents($root . '/core/classes/FirstInstall.php');
$check(is_string($firstInstall) && str_contains($firstInstall, "'core:20261019_200_dashboard_cards'")
    && str_contains($firstInstall, "'core:20261105_000_page_access'"),
    'FirstInstall migration inventory aligned with page_access in F4c.8');

$tmp = realpath('/tmp');
$check(is_string($tmp) && is_dir($tmp), 'System private QA parent available');
$path = $tmp . '/bp-f4c7-contract-' . bin2hex(random_bytes(8));
if (!mkdir($path, 0700)) throw new RuntimeException('Cannot create private fixture');
try {
    $gate = new UpdateDurableRecoveryGate($path);
    $check($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Empty fixture has clear recovery state');
    mkdir($path . '/storage', 0700);
    mkdir($path . '/storage/update-recovery', 0700);
    $marker = $path . '/storage/update-recovery/.db-update-pending-' . bin2hex(random_bytes(16));
    file_put_contents($marker, '{"fixture":"F4c.7 QA1"}', LOCK_EX);
    chmod($marker, 0600);
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Persistent DB marker blocks CMS');
    $script = $path . '/public_html/admin/tools.php';
    mkdir($path . '/public_html', 0700);
    mkdir($path . '/public_html/admin', 0700);
    file_put_contents($script, "<?php // private fixture\n");
    $check($gate->permitsHttp($script, '/admin/tools/update', 'GET'), 'Blocked state permits read-only update page');
    $check(!$gate->permitsHttp($script, '/admin/tools/update', 'POST', [], ['action'=>'install_official_update']), 'Blocked state rejects installation POST');
    $check(!$gate->permitsHttp($script, '/admin/tools/update', 'POST', [], ['action'=>'f4c7_qa_review_only']), 'Blocked state rejects even QA-looking POST');
    $check(!$gate->permitsHttp($script, '/admin/tools/data', 'GET'), 'Blocked state rejects non-update Tools route');
    $check(is_file($marker), 'QA policy has not cleared persistent DB marker');
} finally {
    @unlink($marker ?? '');
    @unlink($script ?? '');
    @rmdir($path . '/public_html/admin');
    @rmdir($path . '/public_html');
    @rmdir($path . '/storage/update-recovery');
    @rmdir($path . '/storage');
    @rmdir($path);
}
echo "F4c.7 Core contracts: {$passed} PASS / 0 FAIL (no MySQL, no production web routes)\n";
