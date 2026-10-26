<?php

declare(strict_types=1);
/** z13: Pages list and editor reuse Core-owned delete confirmation without changing rules. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminDeleteDialog.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    if ($ok) ++$pass; else ++$fail;
};
function t(string $key): string { return $key; }
function admin_csrf_token(): string { return 'synthetic-z13-token'; }
$source = (string)file_get_contents($root . '/public_html/admin/views/pages/index.php');
$editor = (string)file_get_contents($root . '/public_html/admin/views/pages/partials/delete-modal.php');
$panel = (string)file_get_contents($root . '/public_html/admin/views/pages/partials/delete-panel.php');
$controller = (string)file_get_contents($root . '/public_html/admin/pages.php');
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$workflow = (string)file_get_contents($root . '/public_html/admin/assets/js/admin-page-workflow.js');
$check(substr_count($source, 'AdminDeleteDialog::render(') === 1, 'List delegates to exactly one Core renderer');
$check(substr_count($editor, 'AdminDeleteDialog::render(') === 1, 'Editor delegates to exactly one Core renderer');
$check(!str_contains($source, '<dialog class="admin-modal') && !str_contains($editor, '<dialog class="admin-modal'), 'No duplicated custom modal HTML in migrated views');
$check(str_contains($source, "'id' => 'page-list-delete-dialog'") && str_contains($editor, "'id' => 'page-delete-dialog'"), 'All existing dialog IDs retained');
$check(str_contains($source, "'subjectAttributes' => ['data-page-list-delete-title' => true]")
    && str_contains($source, "'id' => ['value' => '', 'attributes' => ['data-page-list-delete-id' => true]]"), 'Existing delegated JS fields preserved');
$check(str_contains($source, "'action' => '/admin/sider?mode=delete'")
    && str_contains($editor, "'action' => '/admin/sider?mode=delete&id=' . (int)\$id"), 'List/editor retain their precise server endpoints');
$check(str_contains($source, "'return_to' => 'list'")
    && str_contains($editor, "'return_to' => \$deleteReturnMode ?? 'view'"), 'Deletion failure return origins are preserved');
$check(str_contains($source, "'csrfToken' => admin_csrf_token()") && str_contains($editor, "'csrfToken' => admin_csrf_token()"), 'Both dialogs include server-issued CSRF token');
$check(str_contains($source, "'confirm_delete' => 'permanent'") && str_contains($editor, "'confirm_delete' => 'permanent'"), 'Existing explicit permanent confirmation preserved');
$check(str_contains($editor, "if (!\$isSystem && (int)(\$childCount ?? 0) === 0):"), 'Editor withholds delete dialog from locked system and parent pages');
$check(str_contains($source, '$canDelete=!$isSystem && !$hasChildren;') && str_contains($source, "'disabled' => !\$canDelete"), 'List blocks delete trigger for locked system and parent pages');
$check(str_contains($panel, '$isSystem || (int)($childCount ?? 0) > 0'), 'Editor danger panel retains protection');
$check(str_contains($panel, 'data-admin-open-delete="page-delete-dialog"'), 'Editor delete button still opens modal, not a new page');
$check(str_contains($controller, 'admin_verify_csrf(') && str_contains($controller, "(\$_POST['confirm_delete'] ?? '') !== 'permanent'"), 'Controller still rejects missing CSRF or explicit confirmation');
$check(str_contains($controller, "\$pagesModel->deleteAdmin(\$id)"), 'Server still owns real deletion and model integrity');
$check(str_contains($controller, "'list' => '/admin/sider'") && str_contains($controller, "'edit' => '/admin/sider?mode=edit&id=' . \$id"), 'Delete failure destinations remain allowlisted');
$check(str_contains($js, "if (dialog.id === 'page-list-delete-dialog')")
    && str_contains($js, "name.textContent = button.dataset.pageDeleteTitle || '';"), 'Live-search modal JS assigns inert text, not HTML');
$check(str_contains($js, "if (!/^[1-9][0-9]*$/.test(id)) return;"), 'Invalid selected IDs cannot open shared list modal');
$check(str_contains($workflow, "document.querySelector('#page-delete-dialog form')"), 'Editor draft-warning bypass still binds to the same form');
$partial = $root . '/public_html/admin/views/pages/partials/delete-modal.php';
$renderEditor = static function (bool $system, int $children, string $title, string $returnMode = 'edit') use ($partial): string {
    $isSystem = $system;
    $childCount = $children;
    $pageRecord = ['page_title' => $title];
    $id = 42;
    $deleteReturnMode = $returnMode;
    ob_start();
    try { require $partial; return (string)ob_get_clean(); }
    catch (Throwable $e) { ob_end_clean(); throw $e; }
};
$html = $renderEditor(false, 0, '<img src=x onerror=alert(1)>');
$check(substr_count($html, '<dialog ') === 1 && substr_count($html, '</dialog>') === 1, 'Eligible edit page renders precisely one native Core modal');
$check(str_contains($html, 'id="page-delete-dialog" aria-labelledby="page-delete-title"'), 'Core modal keeps accessible heading association');
$check(str_contains($html, 'method="post" action="/admin/sider?mode=delete&amp;id=42"'), 'Editor form still uses local POST and escapes action exactly once');
$check(str_contains($html, 'name="csrf_token" value="synthetic-z13-token"'), 'Editor Core modal contains current CSRF field');
$check(str_contains($html, 'name="id" value="42"') && str_contains($html, 'name="confirm_delete" value="permanent"'), 'Editor keeps exact user confirmation fields');
$check(str_contains($html, 'name="return_to" value="edit"'), 'Editor returns delete errors to editor');
$check(!str_contains($html, '<img ') && str_contains($html, '&lt;img src=x onerror=alert(1)&gt;'), 'Malicious page title cannot inject markup');
$check(str_contains($html, 'data-modal-close') && str_contains($html, 'type="submit"'), 'Close/cancel/submit actions are generated by Core');
$check($renderEditor(true, 0, 'Forside') === '', 'System page never renders delete dialog');
$check($renderEditor(false, 1, 'Overordnet') === '', 'Page with children never renders delete dialog');
$check($renderEditor(true, 2, 'Systemparent') === '', 'System page with children never renders delete dialog');
$check(str_contains($renderEditor(false, 0, 'Legal', 'view'), 'name="return_to" value="view"'), 'View-origin fallback remains supported');
$check(str_contains($renderEditor(false, 0, 'Legal', '<evil>'), 'name="return_to" value="&lt;evil&gt;"'), 'Dynamic return field is always escaped before server-side allowlisting');
$check((string)file_get_contents($root . '/public_html/admin/assets/scss/admin.scss')
    === (string)file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS unchanged and synchronized');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root . '/public_html/incl/init.php'), $versionMatch) === 1 && version_compare($versionMatch[1], '2.4.4.70', '>='), 'z13 compatibility retained in newer versions');
echo "Core Pages Delete Dialog z13: $pass PASS / $fail FAIL\n";
exit($fail > 0 ? 1 : 0);
