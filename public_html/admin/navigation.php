<?php

declare(strict_types=1);

require_once __DIR__ . '/incl/module-bootstrap.php';

$navigation = new Navigation();
$errors = [];
$mode = (string)($_GET['mode'] ?? 'list');
if (!in_array($mode, ['list', 'new', 'edit', 'delete'], true)) $mode = 'list';
$menuId = max(0, (int)($_GET['id'] ?? 0));
$selectedMenu = in_array($mode, ['edit', 'delete'], true) ? $navigation->menu($menuId) : null;
if (in_array($mode, ['edit', 'delete'], true) && !$selectedMenu) {
    http_response_code(404);
    $errors['form'] = 'Menuen findes ikke.';
    $mode = 'list';
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST', true, 405);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!admin_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        $errors['form'] = 'Sessionen er udløbet. Genindlæs siden og prøv igen.';
    } else {
        try {
            if ($mode === 'new' && $action === 'create_menu') {
                $newId = $navigation->createMenu((string)($_POST['name'] ?? ''));
                admin_log_activity('created', 'menu', $newId, (string)($_POST['name'] ?? ''));
                admin_set_notice('Menuen er oprettet. Tilføj nu dine menupunkter.');
                admin_redirect('/admin/navigation?mode=edit&id=' . $newId);
            } elseif ($mode === 'edit' && $selectedMenu && $action === 'save_editor') {
                // An empty menu has no items[] inputs in its HTML form, so the key is absent.
                // The model still rejects incomplete submissions for nonempty menus.
                $submittedItems = $_POST['items'] ?? [];
                if (!is_array($submittedItems)) throw new InvalidArgumentException('Ugyldige menupunkter.');
                $navigation->saveEditor($menuId, (string)($_POST['name'] ?? ''), (string)($_POST['placement'] ?? 'none'), $submittedItems);
                admin_log_activity('updated', 'menu', $menuId, (string)($_POST['name'] ?? ''));
                admin_set_notice('Alle ændringer til menuen er gemt.');
                admin_redirect('/admin/navigation?mode=edit&id=' . $menuId);
            } elseif ($mode === 'delete' && $selectedMenu && $action === 'delete_menu') {
                if (empty($_POST['confirmed'])) throw new InvalidArgumentException('Bekræft sletningen.');
                $navigation->deleteMenu($menuId);
                admin_log_activity('deleted', 'menu', $menuId, (string)($selectedMenu['name'] ?? ''));
                admin_set_notice('Menuen er slettet.');
                admin_redirect('/admin/navigation');
            } else {
                throw new InvalidArgumentException('Handlingen er ikke tilladt her.');
            }
        } catch (InvalidArgumentException $e) {
            $errors['form'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Navigation Manager: ' . $e->getMessage());
            $errors['form'] = 'Ændringerne kunne ikke gemmes. Prøv igen.';
        }
    }
}
$menus = $navigation->menus();
$items = $selectedMenu && $mode === 'edit' ? $navigation->items($menuId) : [];
$pages = $mode === 'edit' ? (new Pages())->all() : [];
$settings = new Settings();
$headerSlug = (string)$settings->get('navigation_header_menu', 'primary');
$footerSlug = (string)$settings->get('navigation_footer_menu', 'footer');
$placement = 'none';
if ($selectedMenu) {
    $inHeader = $selectedMenu['slug'] === $headerSlug;
    $inFooter = $selectedMenu['slug'] === $footerSlug;
    $placement = $inHeader && $inFooter ? 'both' : ($inHeader ? 'header' : ($inFooter ? 'footer' : 'none'));
}
// Resolve one concrete view for each existing Navigation mode.
$navigationView = match ($mode) {
    'new' => 'navigation/new',
    'edit' => 'navigation/edit',
    'delete' => 'navigation/delete',
    default => 'navigation/index',
};
admin_render($navigationView, [
    'user' => $auth->user(), 'activeNav' => 'navigation', 'mode' => $mode,
    'menus' => $menus, 'selectedMenu' => $selectedMenu, 'items' => $items,
    'pages' => $pages, 'placement' => $placement,
    'headerSlug' => $headerSlug, 'footerSlug' => $footerSlug,
    'errors' => $errors, 'notice' => admin_pull_notice(),
]);
