<?php

declare(strict_types=1);

define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage');
require_once __DIR__ . '/incl/module-bootstrap.php';

$mode = strtolower(trim((string)($_GET['mode'] ?? '')));

if ($mode === 'update') {
    $packageReport = null;
    $packageError = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $packageError = 'update_package.error.csrf';
        } elseif (($_POST['action'] ?? '') !== 'review_update_package') {
            $packageError = 'update_package.error.invalid';
        } else {
            $upload = $_FILES['update_package'] ?? null;
            if (!is_array($upload) || !isset($upload['error'], $upload['size'], $upload['tmp_name'])
                || !is_int($upload['error']) || $upload['error'] !== UPLOAD_ERR_OK
                || !is_int($upload['size']) || $upload['size'] < 1
                || $upload['size'] > UpdatePackageReview::MAX_ARCHIVE_BYTES
                || !is_string($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
                $packageError = 'update_package.error.upload';
            } else {
                try {
                    // PHP upload tempfile is short-lived. Never move/extract the package,
                    // execute its content, or store its contents in the CMS/database.
                    $packageReport = (new UpdatePackageReview())->inspect($upload['tmp_name'], $upload['size']);
                } catch (Throwable $e) {
                    error_log('Update package inspection failed (details withheld from admin UI).');
                    $packageError = 'update_package.error.inspect';
                }
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST', true, 405);
        exit;
    }
    $report = (new UpdateCompatibilityPreflight())->inspect();
    header('Cache-Control: private, no-store, max-age=0');
    admin_render('tools/update', [
        'user' => $auth->user(), 'activeNav' => 'tools', 'report' => $report,
        'packageReport' => $packageReport, 'packageError' => $packageError,
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
        admin_redirect('/admin/vaerktoejer/redirects');
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
            $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
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
                admin_set_notice($form['id'] > 0 ? 'Redirectet er opdateret.' : 'Redirectet er oprettet.');
                admin_redirect('/admin/vaerktoejer/redirects?edit=' . $savedId);
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
            admin_redirect('/admin/vaerktoejer/redirects');
        } else {
            admin_redirect('/admin/vaerktoejer/redirects');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/vaerktoejer/redirects');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'health_report') {
            try {
                // Same tools.manage authorization and CSRF protection as Site Backup.
                // The report is read-only, excludes database rows and never exposes secrets.
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
                error_log('Site health report: ' . $e->getMessage());
                $errors['health_report'] = 'health_report.failed';
            }
        } elseif ($action === 'site_backup') {
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
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($json));
                echo $json;
                exit;
            } catch (Throwable $e) {
                error_log('Admin tools export: ' . $e->getMessage());
                $errors['export'] = 'Eksporten kunne ikke oprettes. Prøv igen.';
            }
        } elseif ($action === 'import') {
            if (empty($_POST['confirm_import'])) {
                $errors['import'] = 'Bekræft, at du forstår hvordan importen fletter data ind i databasen.';
            } else {
                try {
                    $result = $tools->importUpload($_FILES['import_file'] ?? []);
                    admin_set_notice('notice.import_complete', 'success', ['rows' => (int)$result['rows'], 'tables' => (int)$result['tables']]);
                    admin_redirect('/admin/vaerktoejer');
                } catch (JsonException $e) {
                    $errors['import'] = 'JSON-filen er ugyldig eller beskadiget.';
                } catch (Throwable $e) {
                    error_log('Admin tools import: ' . $e->getMessage());
                    $errors['import'] = t('notice.import_aborted', ['detail' => AppConfig::isProduction() ? t('notice.import_aborted_safe_detail') : $e->getMessage()]);
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    admin_redirect('/admin/vaerktoejer');
}

admin_render('tools/index', [
    'user' => $auth->user(),
    'activeNav' => 'tools',
    'health' => $tools->siteHealth(),
    'errors' => $errors,
    'notice' => admin_pull_notice(),
]);
