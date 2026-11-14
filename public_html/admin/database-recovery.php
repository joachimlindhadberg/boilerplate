<?php

declare(strict_types=1);

/**
 * Emergency SQL/code recovery. Deliberately does NOT include incl/init.php or
 * instantiate Auth/SessionManager/Settings: any of those can fail when MySQL's
 * account tables have been damaged by an interrupted migration.
 *
 * This endpoint cannot prepare an update, choose SQL, choose a target database,
 * upload files, or bypass the sealed pre-update journal. The HTTPS-only cookie
 * was issued to an authenticated tools.manage admin BEFORE Update now.
 */
$root = dirname(__DIR__, 2);
require_once $root . '/core/bootstrap/php-requirement.php';
boilerplate_require_php_runtime();
require_once $root . '/core/classes/Autoload.php';
header_remove('X-Powered-By');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'none\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'; style-src \'unsafe-inline\'');

/** Read exactly ONE well-formed, blocked transaction, or fail closed. */
function bp_s52_recovery_id(string $root): string
{
    $vault = $root . '/storage/update-recovery';
    if (is_link($vault) || !is_dir($vault) || (fileperms($vault) & 0077) !== 0) {
        throw new RuntimeException('Unsafe recovery vault.');
    }
    $ids = [];
    foreach (new DirectoryIterator($vault) as $item) {
        if ($item->isDot()) continue;
        if (str_starts_with($item->getFilename(), '.db-update-')) {
            if ($item->isLink() || !$item->isFile()
                || !preg_match('/\A\.db-update-pending-([a-f0-9]{32})\z/D', $item->getFilename(), $m)) {
                throw new RuntimeException('Unrecognized recovery marker.');
            }
            $ids[] = $m[1];
        }
    }
    if (count($ids) !== 1) throw new RuntimeException('Ambiguous recovery identity.');
    return $ids[0];
}

function bp_s52_fail(int $status = 403): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $status === 503 ? 'Recovery requires administrator attention.' : 'Recovery access denied.';
    exit;
}

try {
    if (!AppConfig::requestIsHttps()
        || (new UpdateDurableRecoveryGate($root))->status() !== UpdateDurableRecoveryGate::DATABASE) {
        bp_s52_fail();
    }
    $origin = AppConfig::appUrl(); // .env-independent of unavailable DB settings
    if (!is_string($origin) || !preg_match('~\Ahttps://[A-Za-z0-9.-]+(?::[0-9]{1,5})?\z~D', $origin)) {
        bp_s52_fail();
    }
    $id = bp_s52_recovery_id($root);
    $journal = new UpdateOneClickDatabaseJournal($root);
    $state = $journal->inspect($id);
    $cap = (string)($_COOKIE[UpdateOneClickDatabaseRecoveryAuthorization::COOKIE] ?? '');
    if (!preg_match('/\A[a-f0-9]{64}\z/D', $cap)
        || (int)($state['recovery_expires_at'] ?? 0) <= time()
        || !$journal->verifyRecoverySecret($id, $cap)) bp_s52_fail();
    $csrf = UpdateOneClickDatabaseRecoveryAuthorization::csrf($id, $cap);
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
    if ($method === 'GET') {
        header('Content-Type: text/html; charset=utf-8');
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Boilerplate CMS recovery</title></head><body><main style="max-width:42rem;margin:4rem auto;font:16px system-ui;padding:1rem">'
            .'<h1>Recover interrupted update</h1><p>The site is safely locked after an interrupted update. '
            .'Continue the verified forward-only page_access update without restoring an older database backup. If the new table is absent, only code files will be rolled back.</p>'
            .'<form method="post" action="/admin/database-recovery.php"><input type="hidden" name="csrf" value="'.$h($csrf).'">'
            .'<button type="submit">Safely reconcile update</button></form></main></body></html>';
        exit;
    }
    if ($method !== 'POST') {
        header('Allow: GET, POST');
        bp_s52_fail(405);
    }
    // Do not trust Host/Referer or any user-chosen backup/database/transaction.
    $posted = (string)($_POST['csrf'] ?? '');
    $requestOrigin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if (!hash_equals($csrf, $posted) || !hash_equals($origin, $requestOrigin)) bp_s52_fail();
    $gate = new UpdateMaintenanceGate($root);
    if (!$gate->acquireExclusive()) bp_s52_fail(503);
    try {
        // A crash before the pre-DDL checkpoint can leave 'prepared' or
        // 'files_written'. Authorization is checked before changing state.
        $state = $journal->inspect($id);
        if (!in_array($state['phase'], ['recovery_required','recovering','restored'], true)) {
            if (!in_array($state['phase'], ['prepared','files_written','database_running',
                'database_written','postflight_verified'],true)) bp_s52_fail();
            $journal->checkpoint($id,'recovery_required');
        }
        $auth = new UpdateOneClickDatabaseRecoveryAuthorization($journal);
        // Existing authenticator requires id.secret; the stored secure cookie
        // contains only the preissued 256-bit secret, never a client-selected ID.
        if ($journal->inspect($id)['phase'] !== 'restored'
            && !$auth->allowsRecoveryPost($id, $id.'.'.$cap, $posted, $method, $requestOrigin, $origin)) {
            bp_s52_fail();
        }
        $result = (new UpdateOneClickDatabaseCutover($root))->recover($id);
        if (($result['status'] ?? null) !== 'restored') throw new RuntimeException('Recovery incomplete.');
    } finally {
        $gate->release();
    }
    setcookie(UpdateOneClickDatabaseRecoveryAuthorization::COOKIE, '', [
        'expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict'
    ]);
    header('Location: /admin', true, 303);
    exit;
} catch (Throwable $error) {
    error_log('Database recovery failed closed: '.get_class($error));
    bp_s52_fail(503);
}
