<?php

declare(strict_types=1);

define('ADMIN_REQUIRED_CAPABILITY', 'settings.manage');

require_once __DIR__ . '/incl/module-bootstrap.php';

$settingsModel = new Settings();
$form = admin_settings_form_from_model($settingsModel);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = admin_settings_form_payload($_POST);
    $form = $payload['form'];

    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
    } else {
        $errors = $payload['errors'];
        if (!$errors) {
            try {
                foreach ($payload['data'] as $key => $value) {
                    $settingsModel->set((string)$key, $value, 1);
                }
                admin_log_activity('updated', 'settings', null, 'Site settings');
                admin_set_notice('Indstillingerne er gemt.');
                admin_redirect('/admin/indstillinger');
            } catch (Throwable $e) {
                error_log('Admin settings save: ' . $e->getMessage());
                $errors['form'] = 'Indstillingerne kunne ikke gemmes. Prøv igen.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    admin_redirect('/admin/indstillinger');
}

admin_render('settings/index', [
    'user' => $auth->user(),
    'activeNav' => 'settings',
    'form' => $form,
    'errors' => $errors,
    'notice' => admin_pull_notice(),
    'resolvedSiteUrl' => SiteUrl::resolve($settingsModel),
]);
