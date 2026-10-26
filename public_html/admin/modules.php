<?php

declare(strict_types=1);

define('ADMIN_REQUIRED_CAPABILITY', 'modules.manage');
require_once __DIR__ . '/incl/module-bootstrap.php';

$manager = admin_module_manager();
$mode = strtolower(trim((string)($_GET['mode'] ?? '')));

if ($mode === '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/modules');
    }

    // Modules page is the explicit place for synchronizing installed manifests.
    $manager->syncRegistry();
    // Boot active modules once so runtime errors can be surfaced on this page.
    $manager->bootActiveModules();
    admin_render('modules/index', [
        'user' => $auth->user(),
        'activeNav' => 'modules',
        'modules' => $manager->allModules(),
        'notice' => admin_pull_notice(),
    ]);
    exit;
}

if (!in_array($mode, ['activate', 'deactivate', 'update'], true)) {
    http_response_code(404);
    admin_render('not-found', ['user' => $auth->user()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_redirect('/admin/modules');
}

if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
    admin_set_notice('Sessionen er udløbet. Ingen ændringer blev gemt.', 'error');
    admin_redirect('/admin/modules');
}

$slug = strtolower(trim((string)($_POST['slug'] ?? '')));

try {
    if ($mode === 'activate') {
        $manager->activate($slug);
        admin_log_activity('activated', 'module', null, $slug);
        admin_set_notice('Modulet er aktiveret.');
    } elseif ($mode === 'update') {
        $manager->update($slug);
        admin_log_activity('updated', 'module', null, $slug);
        admin_set_notice('plugin.updated_notice');
    } else {
        $manager->deactivate($slug);
        admin_log_activity('deactivated', 'module', null, $slug);
        admin_set_notice('Modulet er deaktiveret.');
    }
} catch (Throwable $e) {
    error_log('Module state change [' . $slug . ']: ' . $e->getMessage());
    $message = $e instanceof RuntimeException ? $e->getMessage() : 'Modulet kunne ikke ændres. Prøv igen.';
    admin_set_notice($message, 'error');
}

admin_redirect('/admin/modules');
