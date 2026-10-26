<?php

declare(strict_types=1);

require_once __DIR__ . '/incl/module-bootstrap.php';

$mode = strtolower(trim((string)($_GET['mode'] ?? '')));
$pageDb = new Db();
$pagesModel = new Pages($pageDb);
$pageBlocksModel = new PageBlocks($pageDb);
$revisionsModel = new PageRevisions($pageDb);
$schedulerModel = new PageScheduler($pageDb);

// Apply due schedules when an editor visits the pages admin, too.
try { $schedulerModel->runDue(10); } catch (Throwable $e) { error_log('PageScheduler (admin): ' . $e->getMessage()); }

if ($mode === '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/sider');
    }
    admin_render('pages/index', [
        'user' => $auth->user(),
        'activeNav' => 'pages',
        'pagesData' => admin_pages_data(),
        'notice' => admin_pull_notice(),
    ]);
    exit;
}

if ($mode === 'view') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/sider');
    }
    $id = max(0, (int)($_GET['id'] ?? 0));
    $page = $pagesModel->findAdmin($id);
    if (!$page) {
        http_response_code(404);
        admin_render('not-found', ['user' => $auth->user()]);
        exit;
    }
    $page['public_url'] = $pagesModel->buildPublicUrl($page);
    $blockRecords = $pageBlocksModel->getForPage($id);
    admin_render('pages/view', [
        'user' => $auth->user(),
        'activeNav' => 'pages',
        'pageRecord' => $page,
        'blocks' => $blockRecords,
        'childCount' => $pagesModel->childCount($id),
        'notice' => admin_pull_notice(),
        'revisionCount' => $revisionsModel->count($id),
        'schedule' => $schedulerModel->getForPage($id),
    ]);
    exit;
}

if ($mode === 'new') {
    $errors = [];
    $form = admin_page_form_from_record();
    $parents = $pagesModel->getParentOptions();
    $builderBlocks = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
            $form = admin_page_form_payload($_POST)['form'];
            $builderResult = admin_page_builder_payload($_POST);
            $builderBlocks = $builderResult['blocks'];
        } else {
            $payload = admin_page_form_payload($_POST);
            $builderResult = admin_page_builder_payload($_POST);
            $form = $payload['form'];
            $builderBlocks = $builderResult['blocks'];
            $errors = $payload['errors'];
            if (!empty($builderResult['errors'])) {
                $errors['builder'] = implode(' ', $builderResult['errors']);
            }
            if (($form['content_mode'] ?? 'legacy') === 'builder' && ($form['page_status'] ?? 'draft') === 'published') {
                $activeBlocks = array_filter($builderBlocks, static fn(array $block): bool => ($block['status'] ?? 'active') === 'active');
                if (!$activeBlocks) {
                    $errors['builder'] = trim(($errors['builder'] ?? '') . ' En publiceret Page Builder-side skal have mindst én aktiv sektion.');
                }
            }
            $scheduleUtc = [null, null];
            if (!$errors) {
                try {
                    $scheduleUtc = PageScheduler::validate($form['page_status'], $form['schedule_publish'], $form['schedule_unpublish'], $form['content_mode'], $builderBlocks);
                } catch (InvalidArgumentException $e) {
                    $errors['schedule_publish'] = $e->getMessage();
                }
            }
            if (!$errors) {
                $createdId = 0;
                try {
                    $pageDb->beginTransaction();
                    $createdId = $pagesModel->createAdmin($payload['data'], false, (int)($auth->user()['id'] ?? 0));
                    $pageBlocksModel->replaceForPage($createdId, $builderBlocks, false);
                    $schedulerModel->saveForPage($createdId, ...$scheduleUtc);
                    $revisionsModel->record($createdId, (int)($auth->user()['id'] ?? 0), 'create');
                    $pageDb->commit();
                    admin_log_activity('created', 'page', $createdId, (string)$form['page_title']);
                    admin_set_notice('Siden er oprettet.');
                    admin_redirect('/admin/sider?mode=edit&id=' . $createdId);
                } catch (Throwable $e) {
                    $pageDb->rollback();
                    error_log('Admin page create: ' . $e->getMessage());
                    $errors['form'] = 'Siden kunne ikke oprettes. Prøv igen.';
                }
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/sider');
    }

    admin_render('pages/new', [
        'user' => $auth->user(),
        'activeNav' => 'pages',
        'pageRecord' => null,
        'parents' => $parents,
        'form' => $form,
        'builderBlocks' => $builderBlocks,
        'errors' => $errors,
        'isEdit' => false,
        'isSystem' => false,
    ]);
    exit;
}

if ($mode === 'edit') {
    $id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
    $page = $pagesModel->findAdmin($id);
    if (!$page) {
        http_response_code(404);
        admin_render('not-found', ['user' => $auth->user()]);
        exit;
    }
    $isSystem = Pages::isSystemSlug((string)$page['page_slug']);
    $parents = $pagesModel->getParentOptions($id);
    $errors = [];
    $form = admin_page_form_from_record(array_merge($page, $schedulerModel->getForPage($id)));
    $builderBlocks = admin_page_builder_blocks_from_records($pageBlocksModel->getForPage($id));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
            $form = admin_page_form_payload($_POST)['form'];
            $builderResult = admin_page_builder_payload($_POST);
            $builderBlocks = $builderResult['blocks'];
        } else {
            $payload = admin_page_form_payload($_POST);
            $builderResult = admin_page_builder_payload($_POST);
            $form = $payload['form'];
            $builderBlocks = $builderResult['blocks'];
            $errors = $payload['errors'];
            if (!empty($builderResult['errors'])) {
                $errors['builder'] = implode(' ', $builderResult['errors']);
            }
            if (($form['content_mode'] ?? 'legacy') === 'builder' && ($form['page_status'] ?? 'draft') === 'published') {
                $activeBlocks = array_filter($builderBlocks, static fn(array $block): bool => ($block['status'] ?? 'active') === 'active');
                if (!$activeBlocks) {
                    $errors['builder'] = trim(($errors['builder'] ?? '') . ' En publiceret Page Builder-side skal have mindst én aktiv sektion.');
                }
            }
            if ($isSystem) {
                $payload['data']['page_slug'] = (string)$page['page_slug'];
                $payload['data']['parent_id'] = $page['parent_id'] ?? null;
                $form['page_slug'] = (string)$page['page_slug'];
                $form['parent_id'] = !empty($page['parent_id']) ? (string)$page['parent_id'] : '';
            }
            $scheduleUtc = [null, null];
            if (!$errors) {
                try {
                    $scheduleUtc = PageScheduler::validate($form['page_status'], $form['schedule_publish'], $form['schedule_unpublish'], $form['content_mode'], $builderBlocks);
                } catch (InvalidArgumentException $e) {
                    $errors['schedule_publish'] = $e->getMessage();
                }
            }
            if (!$errors) {
                try {
                    $pageDb->beginTransaction();
                    $locked = $pageDb->fetchArray('SELECT id FROM pages WHERE id = ? FOR UPDATE', [$id]);
                    if (!$locked) throw new RuntimeException('Siden findes ikke længere.');
                    if ($revisionsModel->count($id) === 0) {
                        $revisionsModel->record($id, (int)($auth->user()['id'] ?? 0), 'baseline');
                    }
                    if (!$pagesModel->updateAdmin($id, $payload['data'], false, (int)($auth->user()['id'] ?? 0))) {
                        throw new RuntimeException('Siden findes ikke længere.');
                    }
                    $pageBlocksModel->replaceForPage($id, $builderBlocks, false);
                    $schedulerModel->saveForPage($id, ...$scheduleUtc);
                    $revisionsModel->record($id, (int)($auth->user()['id'] ?? 0), 'save');
                    $pageDb->commit();
                    admin_log_activity('updated', 'page', $id, (string)$form['page_title']);
                    admin_set_notice('Siden er opdateret.');
                    admin_redirect('/admin/sider?mode=edit&id=' . $id);
                } catch (Throwable $e) {
                    $pageDb->rollback();
                    error_log('Admin page update: ' . $e->getMessage());
                    $errors['form'] = $e instanceof RuntimeException ? $e->getMessage() : 'Siden kunne ikke gemmes. Prøv igen.';
                }
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/sider');
    }

    admin_render('pages/edit', [
        'user' => $auth->user(),
        'activeNav' => 'pages',
        'pageRecord' => $page,
        'parents' => $parents,
        'form' => $form,
        'builderBlocks' => $builderBlocks,
        'errors' => $errors,
        'isEdit' => true,
        'isSystem' => $isSystem,
        'childCount' => $pagesModel->childCount($id),
        'notice' => admin_pull_notice(),
    ]);
    exit;
}

if ($mode === 'history' || $mode === 'revision' || $mode === 'restore') {
    $id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
    $page = $pagesModel->findAdmin($id);
    if (!$page) {
        http_response_code(404);
        admin_render('not-found', ['user' => $auth->user()]);
        exit;
    }
    if ($mode === 'restore') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            admin_redirect('/admin/sider?mode=history&id=' . $id);
        }
        if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
            admin_set_notice('Sessionen er udløbet. Versionen er ikke gendannet.', 'error');
            admin_redirect('/admin/sider?mode=history&id=' . $id);
        }
        $revisionId = max(0, (int)($_POST['revision_id'] ?? 0));
        try {
            $revisionsModel->restore($id, $revisionId, (int)($auth->user()['id'] ?? 0));
            admin_log_activity('restored', 'page', $id, (string)$page['page_title']);
            admin_set_notice('Versionen er gendannet. Den tidligere aktuelle version er bevaret i historikken.');
            admin_redirect('/admin/sider?mode=history&id=' . $id);
        } catch (Throwable $e) {
            error_log('Admin page restore: ' . $e->getMessage());
            admin_set_notice($e instanceof RuntimeException ? $e->getMessage() : 'Versionen kunne ikke gendannes.', 'error');
            admin_redirect('/admin/sider?mode=history&id=' . $id);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        admin_redirect('/admin/sider?mode=history&id=' . $id);
    }
    if ($mode === 'history') {
        $pageNo = max(1, min(100000, (int)($_GET['page'] ?? 1)));
        $count = $revisionsModel->count($id);
        $totalPages = max(1, (int)ceil($count / 10));
        $pageNo = min($pageNo, $totalPages);
        admin_render('pages/history', [
            'user' => $auth->user(), 'activeNav' => 'pages',
            'pageRecord' => $page, 'revisions' => $revisionsModel->listForPage($id, 10, ($pageNo - 1) * 10),
            'pageNo' => $pageNo, 'totalPages' => $totalPages,
            'revisionCount' => $count, 'notice' => admin_pull_notice(),
        ]);
        exit;
    }
    $revisionId = max(0, (int)($_GET['revision_id'] ?? 0));
    $revision = $revisionsModel->getForPage($id, $revisionId);
    if (!$revision) {
        http_response_code(404);
        admin_render('not-found', ['user' => $auth->user()]);
        exit;
    }
    admin_render('pages/revision', [
        'user' => $auth->user(), 'activeNav' => 'pages',
        'pageRecord' => $page, 'revision' => $revision,
        'currentSnapshot' => $revisionsModel->snapshot($id),
    ]);
    exit;
}

if ($mode === 'delete') {
    $id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
    $page = $pagesModel->findAdmin($id);
    if (!$page) {
        http_response_code(404);
        admin_render('not-found', ['user' => $auth->user()]);
        exit;
    }

    $isSystem = Pages::isSystemSlug((string)$page['page_slug']);
    $children = $pagesModel->childCount($id);
    $blocked = $isSystem || $children > 0;
    $blockedReason = $isSystem
        ? 'Denne side er en del af hjemmesidens faste struktur og kan ikke slettes permanent.'
        : ($children > 0 ? 'Siden har undersider. Flyt eller slet undersiderne først.' : '');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        admin_render('delete-confirm', [
            'user' => $auth->user(),
            'activeNav' => 'pages',
            'pageTitle' => 'Slet side',
            'entityLabel' => 'side',
            'entityName' => (string)$page['page_title'],
            'entityContext' => '/' . trim((string)$page['page_slug'], '/'),
            'warningText' => $blocked ? $blockedReason : 'Siden og dens Page Builder-sektioner slettes permanent fra databasen. Handlingen kan ikke fortrydes.',
            'postAction' => '/admin/sider?mode=delete&id=' . $id,
            'cancelUrl' => '/admin/sider?mode=view&id=' . $id,
            'id' => $id,
            'blocked' => $blocked,
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_redirect('/admin/sider');
    }
    // A failed deletion returns to its original fixed admin screen; never trust a URL.
    $returnMode = (string)($_POST['return_to'] ?? '');
    $failureUrl = match ($returnMode) {
        'edit' => '/admin/sider?mode=edit&id=' . $id,
        'list' => '/admin/sider',
        default => '/admin/sider?mode=view&id=' . $id,
    };
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_set_notice('Sessionen er udløbet. Siden er ikke slettet.', 'error');
        admin_redirect($failureUrl);
    }
    if (($_POST['confirm_delete'] ?? '') !== 'permanent') {
        admin_set_notice('Sletningen blev ikke bekræftet.', 'error');
        admin_redirect($failureUrl);
    }

    try {
        $result = $pagesModel->deleteAdmin($id);
        if (empty($result['ok'])) {
            admin_set_notice((string)($result['message'] ?? 'Siden kunne ikke slettes.'), 'error');
            admin_redirect($failureUrl);
        }
        admin_log_activity('deleted', 'page', $id, (string)($result['page']['page_title'] ?? ''));
        admin_set_notice('Siden er slettet permanent.');
        admin_redirect('/admin/sider');
    } catch (Throwable $e) {
        error_log('Admin page delete: ' . $e->getMessage());
        admin_set_notice('Siden kunne ikke slettes. Prøv igen.', 'error');
        admin_redirect($failureUrl);
    }
}

http_response_code(404);
admin_render('not-found', ['user' => $auth->user()]);
