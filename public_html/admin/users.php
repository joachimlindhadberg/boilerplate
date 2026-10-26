<?php

declare(strict_types=1);

define('ADMIN_REQUIRED_CAPABILITY', 'users.manage');

require_once __DIR__ . '/incl/module-bootstrap.php';

$mode = strtolower(trim((string)($_GET['mode'] ?? '')));
$userModel = new Users();
$roleOptions = $userModel->roleOptions();
$actorId = (int)($auth->id() ?? 0);

if ($mode === '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/brugere');
    }
    admin_render('users/index', [
        'user' => $auth->user(),
        'activeNav' => 'users',
        'usersData' => admin_users_data(),
        'notice' => admin_pull_notice(),
    ]);
    exit;
}

if ($mode === 'view') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/brugere');
    }
    $id = max(0, (int)($_GET['id'] ?? 0));
    $managedUser = $userModel->find($id);
    if (!$managedUser) {
        http_response_code(404);
        admin_render('not-found', ['user' => $auth->user()]);
        exit;
    }
    $isSelf = $id === $actorId;
    $isLastActiveAdmin = (string)($managedUser['role_name'] ?? '') === 'admin'
        && (string)($managedUser['status'] ?? '') === 'active'
        && $userModel->countActiveAdminsExcluding($id) < 1;
    admin_render('users/view', [
        'user' => $auth->user(),
        'activeNav' => 'users',
        'managedUser' => $managedUser,
        'notice' => admin_pull_notice(),
        'actorId' => $actorId,
        'deleteBlocked' => $isSelf || $isLastActiveAdmin,
        'deleteReason' => $isSelf
            ? 'Du kan ikke slette den konto, du selv er logget ind med.'
            : ($isLastActiveAdmin ? 'Den sidste aktive administrator kan ikke slettes.' : ''),
    ]);
    exit;
}

if ($mode === 'new') {
    $errors = [];
    $form = admin_user_form_from_record(null);
    if ($form['role_id'] < 1 && $roleOptions) {
        foreach ($roleOptions as $role) {
            if (($role['role_name'] ?? '') === 'editor') {
                $form['role_id'] = (int)$role['id'];
                break;
            }
        }
        if ($form['role_id'] < 1) {
            $form['role_id'] = (int)($roleOptions[0]['id'] ?? 0);
        }
    }
    $form['must_change_password'] = '1';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = admin_user_form_payload($_POST, $roleOptions, null, $actorId);
        $form = $payload['form'];
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
        } else {
            $errors = $payload['errors'];
            if (!$errors) {
                try {
                    $data = $payload['data'];
                    $data['password'] = (string)$payload['newPassword'];
                    $id = $userModel->create($data);
                    admin_log_activity('created', 'user', $id, trim((string)($data['firstname'] ?? '') . ' ' . (string)($data['lastname'] ?? '')));
                    admin_set_notice('Brugeren er oprettet.');
                    admin_redirect('/admin/brugere?mode=view&id=' . $id);
                } catch (Throwable $e) {
                    error_log('Admin user create: ' . $e->getMessage());
                    $errors['form'] = str_contains(strtolower($e->getMessage()), 'duplicate')
                        ? 'E-mailadressen bruges allerede af en anden bruger.'
                        : 'Brugeren kunne ikke oprettes. Prøv igen.';
                }
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/brugere');
    }

    admin_render('users/new', [
        'user' => $auth->user(),
        'activeNav' => 'users',
        'managedUser' => null,
        'roles' => $roleOptions,
        'form' => $form,
        'errors' => $errors,
        'isEdit' => false,
        'actorId' => $actorId,
    ]);
    exit;
}

if ($mode === 'edit') {
    $id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
    $managedUser = $userModel->find($id);
    if (!$managedUser) {
        http_response_code(404);
        admin_render('not-found', ['user' => $auth->user()]);
        exit;
    }

    $errors = [];
    $form = admin_user_form_from_record($managedUser);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = admin_user_form_payload($_POST, $roleOptions, $managedUser, $actorId);
        $form = $payload['form'];
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
        } else {
            $errors = $payload['errors'];
            if (!$errors) {
                try {
                    if (!$userModel->updateAdmin($id, $payload['data'], $payload['newPassword'], $payload['forcePasswordChange'])) {
                        $errors['form'] = 'Brugeren findes ikke længere.';
                    } else {
                        if ($id === $actorId) {
                            $auth->refresh();
                        }
                        admin_log_activity('updated', 'user', $id, trim((string)($payload['data']['firstname'] ?? '') . ' ' . (string)($payload['data']['lastname'] ?? '')));
                        admin_set_notice('Brugeren er opdateret.');
                        admin_redirect('/admin/brugere?mode=view&id=' . $id);
                    }
                } catch (Throwable $e) {
                    error_log('Admin user update: ' . $e->getMessage());
                    $errors['form'] = str_contains(strtolower($e->getMessage()), 'duplicate')
                        ? 'E-mailadressen bruges allerede af en anden bruger.'
                        : 'Brugeren kunne ikke gemmes. Prøv igen.';
                }
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/brugere');
    }

    admin_render('users/edit', [
        'user' => $auth->user(),
        'activeNav' => 'users',
        'managedUser' => $managedUser,
        'roles' => $roleOptions,
        'form' => $form,
        'errors' => $errors,
        'isEdit' => true,
        'actorId' => $actorId,
    ]);
    exit;
}

if ($mode === 'delete') {
    $id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
    $managedUser = $userModel->find($id);
    if (!$managedUser) {
        http_response_code(404);
        admin_render('not-found', ['user' => $auth->user()]);
        exit;
    }

    $isSelf = $id === $actorId;
    $isLastActiveAdmin = (string)($managedUser['role_name'] ?? '') === 'admin'
        && (string)($managedUser['status'] ?? '') === 'active'
        && $userModel->countActiveAdminsExcluding($id) < 1;
    $blocked = $isSelf || $isLastActiveAdmin;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($isSelf) {
            $warning = 'Du kan ikke slette den konto, du selv er logget ind med.';
        } elseif ($isLastActiveAdmin) {
            $warning = 'Brugeren er den sidste aktive administrator og kan derfor ikke slettes. Opret eller aktivér en anden administrator først.';
        } else {
            $warning = 'Brugerkontoen slettes permanent. Handlingen kan ikke fortrydes.';
        }
        admin_render('delete-confirm', [
            'user' => $auth->user(),
            'activeNav' => 'users',
            'pageTitle' => 'Slet bruger',
            'entityLabel' => 'bruger',
            'entityName' => trim((string)$managedUser['firstname'] . ' ' . (string)$managedUser['lastname']),
            'entityContext' => (string)($managedUser['email'] ?? ''),
            'warningText' => $warning,
            'postAction' => '/admin/brugere?mode=delete&id=' . $id,
            'cancelUrl' => '/admin/brugere?mode=view&id=' . $id,
            'id' => $id,
            'blocked' => $blocked,
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_redirect('/admin/brugere');
    }
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_set_notice('Sessionen er udløbet. Brugeren er ikke slettet.', 'error');
        admin_redirect('/admin/brugere?mode=view&id=' . $id);
    }
    if (($_POST['confirm_delete'] ?? '') !== 'permanent') {
        admin_set_notice('Sletningen blev ikke bekræftet.', 'error');
        admin_redirect('/admin/brugere?mode=view&id=' . $id);
    }

    try {
        $result = $userModel->deleteAdmin($id, $actorId);
        if (empty($result['ok'])) {
            admin_set_notice((string)($result['message'] ?? 'Brugeren kunne ikke slettes.'), 'error');
            admin_redirect('/admin/brugere?mode=view&id=' . $id);
        }
        admin_log_activity('deleted', 'user', $id, trim((string)($managedUser['firstname'] ?? '') . ' ' . (string)($managedUser['lastname'] ?? '')));
        admin_set_notice('Brugeren er slettet permanent.');
        admin_redirect('/admin/brugere');
    } catch (Throwable $e) {
        error_log('Admin user delete: ' . $e->getMessage());
        admin_set_notice('Brugeren kunne ikke slettes. Prøv igen.', 'error');
        admin_redirect('/admin/brugere?mode=view&id=' . $id);
    }
}

http_response_code(404);
admin_render('not-found', ['user' => $auth->user()]);
