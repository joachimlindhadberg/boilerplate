<?php

declare(strict_types=1);

define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage');
require_once __DIR__ . '/incl/module-bootstrap.php';

// Apache MultiViews may negotiate /admin/tools/<section> to tools.php
// without applying a mode query parameter. Resolve the original URL as well.
$mode = AdminToolsRoute::resolve((string)($_SERVER['REQUEST_URI'] ?? ''), $_GET['mode'] ?? null);
if ($mode === null) {
    http_response_code(404);
    exit;
}

if ($mode === 'update') {
    $channelSettings = new Settings();
    $updateChannel = UpdateReleaseVersion::channel((string)$channelSettings->get('update_channel', 'stable'));
    $lookupResult = null;
    $lookupAttempted = false;
    $prepareResult = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_for_updates') {
        $lookupAttempted = true;
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $lookupResult = ['status' => 'csrf', 'latest_version' => '', 'release_url' => ''];
        } else {
            $lookupResult = (new UpdateReleaseLookup())->check(
                defined('BOILERPLATE_VERSION') ? (string)BOILERPLATE_VERSION : '', $updateChannel
            );
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_update_channel') {
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            admin_set_notice('updates.session_expired', 'error');
        } elseif (!in_array(($_POST['channel'] ?? null), ['stable', 'beta'], true)) {
            admin_set_notice('updates.update_failed', 'error');
        } else {
            try {
                $channelSettings->set('update_channel', (string)$_POST['channel'], 0);
            } catch (Throwable $e) {
                error_log('Could not save update channel (details withheld).');
                admin_set_notice('updates.update_failed', 'error');
            }
        }
        admin_redirect('/admin/tools/update');
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'prepare_official_update') {
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $prepareResult = ['status' => 'csrf', 'install_ready' => false];
        } else {
            try {
                $prepareResult = (new UpdateOfficialReleasePackage())->prepareLatest($updateChannel);
            } catch (Throwable $e) {
                error_log('Official release preparation failed (details withheld).');
                $prepareResult = ['status' => 'download_or_review_failed', 'install_ready' => false];
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''),
        ['install_official_update', 'restore_interrupted_update'], true)) {
        // Bootstrap already verified login and tools.manage. Never accept a URL,
        // archive path or arbitrary repository supplied by a browser request.
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            admin_set_notice('updates.session_expired', 'error');
            admin_redirect('/admin/tools/update');
        }
        $action = (string)$_POST['action'];
        $install = new UpdateOneClickInstaller();
        $prepared = null;
        if ($action === 'install_official_update') {
            $expected = trim((string)($_POST['expected_version'] ?? ''));
            if (!UpdateReleaseVersion::valid($expected)
                || ($_POST['expected_channel'] ?? '') !== $updateChannel) {
                admin_set_notice('updates.update_failed', 'error');
                admin_redirect('/admin/tools/update');
            }
            try {
                $prepared = (new UpdateOfficialReleasePackage())->prepareLatest($updateChannel);
            } catch (Throwable $e) {
                $prepared = ['status' => 'download_or_review_failed'];
            }
            if (($prepared['status'] ?? '') !== 'prepared_for_review_only'
                || ($prepared['version'] ?? '') !== $expected) {
                admin_set_notice('updates.update_failed', 'error');
                admin_redirect('/admin/tools/update');
            }
        }
        // The current HTTP request holds the normal F1c reader lease.
        // Release only OUR reader, then acquire the existing exclusive lease;
        // a competing HTTP/CLI worker keeps the update blocked.
        UpdateMaintenanceGate::releaseCurrentHttpReadersForUpdate();
        $gate = new UpdateMaintenanceGate();
        try {
            if (!$gate->acquireExclusive()) {
                $status = 'busy';
            } elseif ($action === 'restore_interrupted_update') {
                $result = $install->restoreInterrupted();
                $status = (string)($result['status'] ?? 'recovery_required');
            } else {
                $result = $install->install((string)$prepared['version'], (string)$prepared['sha256'], $updateChannel);
                $status = (string)($result['status'] ?? 'recovery_required');
            }
        } catch (Throwable $e) {
            error_log('Official one-click update action failed (details withheld).');
            $status = 'failed_before_changes';
        } finally {
            $gate->release();
        }
        $notice = match ($status) {
            'updated' => 'updates.update_success',
            'restored', 'restored_after_failure' => 'updates.update_restored',
            'unsupported_release' => 'updates.update_migrations_blocked',
            'busy' => 'updates.update_busy',
            'recovery_required' => 'updates.update_recovery_required',
            default => 'updates.update_failed',
        };
        admin_set_notice($notice, in_array($status, ['updated', 'restored'], true) ? 'success' : 'error');
        admin_redirect('/admin/tools/update');
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Unknown actions are rejected. Manual ZIP upload is never accepted.
        http_response_code(400);
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST', true, 405);
        exit;
    }
    try {
        $preparedRelease = (new UpdateOfficialReleasePackage())->preparedStatus($updateChannel);
    } catch (Throwable $e) {
        $preparedRelease = ['status' => 'not_prepared', 'install_ready' => false];
    }
    $report = (new UpdateCompatibilityPreflight())->inspect();
    header('Cache-Control: private, no-store, max-age=0');
    admin_render('tools/update', [
        'user' => $auth->user(), 'activeNav' => 'tools', 'report' => $report,
        'lookupResult' => $lookupResult, 'lookupAttempted' => $lookupAttempted, 'updateChannel' => $updateChannel,
        'prepareResult' => $prepareResult, 'preparedRelease' => $preparedRelease,
        'pendingRecovery' => (new UpdateOneClickInstaller())->hasInterruptedUpdate(),
        'updateNotice' => admin_pull_notice(),
    ]);
    exit;
}

if ($mode === 'site-health') {
    $tools = new AdminTools();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'site_health.session_expired';
        } elseif (($_POST['action'] ?? '') !== 'health_report') {
            http_response_code(400);
            $errors['form'] = 'site_health.invalid_action';
        } else {
            try {
                // Same tools.manage capability as Tools; read-only report download.
                $report = $tools->siteHealthReport();
                if ($report === '') throw new RuntimeException('Empty health report.');
                if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
                while (ob_get_level() > 0) ob_end_clean();
                header('Content-Type: text/plain; charset=UTF-8');
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: private, no-store, max-age=0');
                header('Content-Disposition: attachment; filename="boilerplate-site-health-' . gmdate('Y-m-d-His') . '.txt"');
                header('Content-Length: ' . strlen($report));
                echo $report;
                exit;
            } catch (Throwable $e) {
                error_log('Site health report generation failed (details withheld).');
                $errors['health_report'] = 'health_report.failed';
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST', true, 405);
        exit;
    }
    header('Cache-Control: private, no-store, max-age=0');
    admin_render('tools/site-health', [
        'user' => $auth->user(), 'activeNav' => 'tools',
        'health' => $tools->siteHealth(), 'errors' => $errors,
    ]);
    exit;
}

if ($mode === 'activity') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET', true, 405);
        exit;
    }
    $search = trim((string)($_GET['q'] ?? ''));
    $search = function_exists('mb_substr') ? mb_substr($search, 0, 120) : substr($search, 0, 120);
    $type = strtolower(trim((string)($_GET['type'] ?? '')));
    $action = strtolower(trim((string)($_GET['action'] ?? '')));
    if (!preg_match('/^[a-z0-9_]{0,64}$/D', $type)) $type = '';
    if (!preg_match('/^[a-z0-9_]{0,64}$/D', $action)) $action = '';
    $log = new ActivityLog();
    $error = null; $entries = []; $types = []; $actions = []; $total = 0;
    try {
        $types = $log->types(); $actions = $log->actions();
        $total = $log->count($search, $type, $action);
        $paging = admin_crud_pagination($total);
        $entries = $log->latest($paging['perPage'], $paging['offset'], $search, $type, $action);
    } catch (Throwable $e) {
        error_log('Activity Log list: ' . $e->getMessage());
        $error = ((int)$e->getCode() === 1146)
            ? t('activity.migration_missing')
            : t('activity.unavailable');
        $paging = admin_crud_pagination(0);
    }
    admin_render('tools/activity', [
        'user'=>$auth->user(), 'activeNav'=>'tools', 'entries'=>$entries,
        'types'=>$types, 'actions'=>$actions, 'search'=>$search,
        'type'=>$type, 'action'=>$action, 'paging'=>$paging, 'total'=>$total, 'error'=>$error,
    ]);
    exit;
}

if ($mode === 'redirects') {
    $redirects = new Redirects();

    $errors = [];
    $editId = max(0, (int)($_GET['edit'] ?? $_POST['id'] ?? 0));
    $editRecord = $editId > 0 ? $redirects->find($editId) : null;
    if ($editId > 0 && !$editRecord && $_SERVER['REQUEST_METHOD'] === 'GET') {
        admin_set_notice('Redirectet findes ikke længere.', 'error');
        admin_redirect('/admin/tools/redirects');
    }

    $form = [
        'id' => $editRecord ? (int)$editRecord['id'] : 0,
        'source_path' => (string)($editRecord['source_path'] ?? ''),
        'target_url' => (string)($editRecord['target_url'] ?? ''),
        'redirect_type' => (int)($editRecord['redirect_type'] ?? 301),
        'is_active' => $editRecord ? !empty($editRecord['is_active']) : true,
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'tools.session_expired';
        } elseif ($action === 'save_redirect') {
            $form = [
                'id' => max(0, (int)($_POST['id'] ?? 0)),
                'source_path' => trim((string)($_POST['source_path'] ?? '')),
                'target_url' => trim((string)($_POST['target_url'] ?? '')),
                'redirect_type' => (int)($_POST['redirect_type'] ?? 301),
                'is_active' => !empty($_POST['is_active']),
            ];
            try {
                $savedId = $redirects->saveManual($form['id'] > 0 ? $form['id'] : null, $form);
                admin_log_activity($form['id'] > 0 ? 'updated' : 'created', 'redirect', $savedId, $form['source_path']);
                admin_set_notice($form['id'] > 0 ? 'tools.redirect_updated' : 'Redirectet er oprettet.');
                admin_redirect('/admin/tools/redirects?edit=' . $savedId);
            } catch (Throwable $e) {
                error_log('Redirect save: ' . $e->getMessage());
                $errors['form'] = $e instanceof RuntimeException ? $e->getMessage() : 'Redirectet kunne ikke gemmes. Prøv igen.';
            }
        } elseif ($action === 'delete_redirect') {
            $deleteId = max(0, (int)($_POST['id'] ?? 0));
            try {
                if (!$redirects->delete($deleteId)) {
                    admin_set_notice('Redirectet findes ikke længere.', 'error');
                } else {
                    admin_log_activity('deleted', 'redirect', $deleteId, (string)($editRecord['source_path'] ?? ''));
                    admin_set_notice('Redirectet er slettet.');
                }
            } catch (Throwable $e) {
                error_log('Redirect delete: ' . $e->getMessage());
                admin_set_notice('Redirectet kunne ikke slettes. Prøv igen.', 'error');
            }
            admin_redirect('/admin/tools/redirects');
        } else {
            admin_redirect('/admin/tools/redirects');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/tools/redirects');
    }

    admin_render('tools/redirects', [
        'user' => $auth->user(),
        'activeNav' => 'tools',
        'redirectsData' => admin_redirects_data(),
        'form' => $form,
        'errors' => $errors,
        'editRecord' => $editRecord,
        'notice' => admin_pull_notice(),
    ]);
    exit;
}

$tools = new AdminTools();
$errors = [];

if (!in_array($mode, ['', 'data'], true)) {
    http_response_code(404);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === '') {
    // Legacy data forms remain supported for compatibility, but overview itself is read-only.
    $action = (string)($_POST['action'] ?? '');
    if (!in_array($action, ['site_backup','export','import'], true)) {
        http_response_code(405); header('Allow: GET'); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'tools.session_expired';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'site_backup') {
            $temporaryBackup = null;
            try {
                // Auth and tools.manage are verified by module-bootstrap; POST
                // is protected by the common CSRF check above.
                $temporaryBackup = (new SiteBackup())->create();
                // PHP exit does not run finally blocks. The shutdown handler
                // also cleans up if the client disconnects during streaming.
                register_shutdown_function(static function () use ($temporaryBackup): void {
                    if (is_file($temporaryBackup)) @unlink($temporaryBackup);
                });
                $size = filesize($temporaryBackup);
                if ($size === false || $size < 1) throw new RuntimeException('Empty site backup.');
                if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
                while (ob_get_level() > 0) ob_end_clean();
                header('Content-Type: application/gzip');
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: private, no-store, max-age=0');
                header('Content-Disposition: attachment; filename="boilerplate-site-backup-' . gmdate('Y-m-d-His') . '.tar.gz"');
                header('Content-Length: ' . $size);
                $stream = fopen($temporaryBackup, 'rb');
                if ($stream === false) throw new RuntimeException('Backup file could not be read.');
                try { fpassthru($stream); } finally { fclose($stream); }
                @unlink($temporaryBackup);
                $temporaryBackup = null;
                exit;
            } catch (Throwable $e) {
                error_log('Site backup: ' . $e->getMessage());
                $errors['backup'] = 'notice.site_backup_failed';
            } finally {
                if ($temporaryBackup !== null && is_file($temporaryBackup)) @unlink($temporaryBackup);
            }
        } elseif ($action === 'export') {
            try {
                $profile = (string)($_POST['profile'] ?? 'content');
                $package = $tools->export($profile);
                $json = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $filename = 'boilerplate-cms-indhold-' . date('Y-m-d-His') . '.json';
                header('Content-Type: application/json; charset=UTF-8');
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: private, no-store, max-age=0');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($json));
                echo $json;
                exit;
            } catch (Throwable $e) {
                error_log('Admin tools export: ' . $e->getMessage());
                $errors['export'] = 'tools.export_failed';
            }
        } elseif ($action === 'import') {
            if (empty($_POST['confirm_import'])) {
                $errors['import'] = 'tools.import_confirm_required';
            } else {
                try {
                    $result = $tools->importUpload($_FILES['import_file'] ?? []);
                    admin_set_notice('notice.import_complete', 'success', ['rows' => (int)$result['rows'], 'tables' => (int)$result['tables']]);
                    admin_redirect('/admin/tools/data');
                } catch (JsonException $e) {
                    $errors['import'] = 'tools.import_invalid_json';
                } catch (Throwable $e) {
                    error_log('Admin tools import: ' . $e->getMessage());
                    $errors['import'] = t('notice.import_aborted', ['detail' => AppConfig::isProduction() ? t('notice.import_aborted_safe_detail') : $e->getMessage()]);
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    admin_redirect('/admin/tools');
}

admin_render(($mode === 'data' || $_SERVER['REQUEST_METHOD'] === 'POST') ? 'tools/data' : 'tools/index', [
    'user' => $auth->user(),
    'activeNav' => 'tools',
    'health' => ($mode === 'data' || $_SERVER['REQUEST_METHOD'] === 'POST') ? [] : $tools->siteHealth(),
    'errors' => $errors,
    'notice' => admin_pull_notice(),
]);
