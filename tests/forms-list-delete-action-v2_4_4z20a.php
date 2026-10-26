<?php

declare(strict_types=1);
/** z20a: permanent Forms list deletion uses Core and preserves server protection. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
function t(string $key, array $params=[]): string { return str_starts_with($key,'forms:') ? substr($key,6) : $key; }
function admin_e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function admin_icon(string $name, string $class=''): string { return AdminIcons::render($name, $class); }
function admin_csrf_token(): string { return 'z20a-csrf'; }
function admin_render_pagination(int $page, int $pages, callable $url, string $heading): void {}
$view = (string)file_get_contents($root . '/modules/forms/admin/views/partials/forms-list.php');
$controller = (string)file_get_contents($root . '/modules/forms/admin/controllers/FormsAdminController.php');
$repo = (string)file_get_contents($root . '/modules/forms/FormsRepository.php');
$coreJs = (string)file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$check(str_contains($view, 'AdminListUI::actionGroup('), 'Existing Core list actions are retained');
$check(str_contains($view, "'icon' => 'edit'") && str_contains($view, "'icon' => 'delete'"), 'Forms list includes Edit and Delete icons');
$check(str_contains($view, "'tone' => 'danger'"), 'Delete uses Core danger tone');
$check(str_contains($view, "'disabled' => !\$canDelete"), 'Server-computed dependencies disable Delete');
$check(str_contains($view, "'data-admin-open-delete' => 'forms-list-delete-dialog'"), 'Native Core modal trigger used');
$check(str_contains($view, 'AdminDeleteDialog::render(['), 'Single shared Core renderer used');
$check(substr_count($view, 'AdminDeleteDialog::render([')===1, 'No duplicate list delete dialog markup');
$check(str_contains($view, 'data-forms-list-delete-target') && str_contains($view, 'data-forms-list-delete-subject'), 'Dedicated hidden target and text label hooks');
$check(str_contains($view, "'form_action' => 'delete'") && str_contains($view, "'confirm_delete' => 'permanent'"), 'Original explicit permanent delete acknowledgement retained');
$check(str_contains($view, "'action' => '/admin/forms?screen=edit'"), 'Existing editor delete handler used through POST');
$check(str_contains($controller, "\$_GET['id'] ?? \$_POST['id'] ?? 0"), 'Editor controller resolves validated POST ID if GET ID absent');
$check(str_contains($controller, "(int)(\$_POST['id'] ?? 0) !== \$id"), 'Controller rejects mismatched POST ID');
$check(str_contains($controller, "admin_verify_csrf(\$_POST['csrf_token'] ?? null)"), 'Controller still verifies CSRF');
$check(str_contains($controller, "(string)(\$_POST['confirm_delete'] ?? '') !== 'permanent'"), 'Controller requires permanent confirmation');
$check(str_contains($controller, '$repository->deleteForm($id)'), 'Controller delegates actual deletion to repository');
$check(str_contains($repo, "SELECT id FROM module_forms WHERE id = ? FOR UPDATE"), 'Repository serializes form deletion');
$check(str_contains($repo, "if (\$dependencies['submissions'] > 0)"), 'Server blocks forms with submissions');
$check(str_contains($repo, "if (\$dependencies['pages'] > 0)"), 'Server blocks forms used in Page Builder');
$check(str_contains($repo, 'ON DELETE RESTRICT'), 'Database foreign-key protection still documented');
$check(str_contains($controller, '$visibleForms = array_slice($allForms, $offset, 10);'), 'Dependency lookups limited to ten visible rows');
$check(str_contains($controller, '$repository->formDeleteDependencies($visibleId)'), 'List uses exactly the editor dependency checker');
$check(str_contains($controller, "'deleteDependenciesById' => \$deleteDependenciesById"), 'Controller supplies dependencies to view');
$check(str_contains($view, '$dependencies === null') && str_contains($view, '!$hasSubmissions && !$usedOnPage'), 'Missing dependency information fails closed');
$check(str_contains($coreJs, "dialog.id === 'forms-list-delete-dialog'"), 'Core delegated JS handles Forms list');
$check(str_contains($coreJs, '/^[1-9][0-9]*$/.test(id)'), 'Core rejects invalid or zero deletion IDs');
$check(str_contains($coreJs, "name.textContent = button.dataset.formsListDeleteName || ''"), 'Core assigns untrusted name as inert text');
$check(str_contains($view, 'data-admin-live-list') && str_contains($view, 'data-admin-live-search'), 'Live search hooks retained');

$render = static function (array $records, array $deps): string {
    $forms=$records; $deleteDependenciesById=$deps; $search='a&b';
    $page=1; $pages=1; $total=count($records); $from=$total?1:0; $to=$total; $notice=null;
    ob_start(); require dirname(__DIR__) . '/modules/forms/admin/views/partials/forms-list.php';
    return (string)ob_get_clean();
};
$fixture = [
 ['id'=>10, 'name'=>'Good <svg onload=alert(1)>', 'status'=>'active', 'field_count'=>0, 'submission_count'=>0],
 ['id'=>11, 'name'=>'With submissions', 'status'=>'active', 'field_count'=>1, 'submission_count'=>3],
 ['id'=>12, 'name'=>'Used in page', 'status'=>'inactive', 'field_count'=>0, 'submission_count'=>0],
 ['id'=>13, 'name'=>'Not checked', 'status'=>'active', 'field_count'=>0, 'submission_count'=>0],
];
$html = $render($fixture, [10=>['submissions'=>0,'pages'=>0], 11=>['submissions'=>3,'pages'=>0], 12=>['submissions'=>0,'pages'=>1]]);
$check(substr_count($html, 'role="group"')===4, 'One Core action group per form');
$check(substr_count($html, 'admin-icon-action__icon')===8, 'Each form has exactly two SVG actions');
$check(substr_count($html, 'data-admin-open-delete="forms-list-delete-dialog"')===4, 'Each row shows a Delete icon, even when blocked');
$check(substr_count($html, 'id="forms-list-delete-dialog"')===1, 'Exactly one shared modal for all rows');
$check(strpos($html, 'id="forms-list-delete-dialog"') > strrpos($html, '</section>'), 'Modal lives outside dynamic list and survives search refresh');
$check(str_contains($html, 'href="/admin/forms?screen=edit&amp;id=10"'), 'Edit still uses original GET endpoint');
$check(str_contains($html, 'data-forms-list-delete-id="10"') && str_contains($html, 'data-forms-list-delete-id="11"'), 'Every row carries its own ID');
$check(str_contains($html, 'name="id" value="" data-forms-list-delete-target'), 'Shared POST ID is initially empty');
$check(str_contains($html, 'name="form_action" value="delete"') && str_contains($html, 'name="confirm_delete" value="permanent"'), 'POST retains original action/confirmation fields');
$check(str_contains($html, 'method="post" action="/admin/forms?screen=edit"'), 'Core modal submits POST to existing edit handler');
$check(substr_count($html, 'name="csrf_token" value="z20a-csrf"')===1, 'Exactly one server-issued CSRF field in shared form');
$check(str_contains($html, 'Good &lt;svg onload=alert(1)&gt;') && !str_contains($html, '<svg onload='), 'Untrusted form names are safely escaped');
$check(str_contains($html, 'name="q" value="a&amp;b"'), 'Existing live-search value is escaped');
$check(str_contains($html, 'data-forms-list-delete-name="Good &lt;svg onload=alert(1)&gt;"'), 'Unsafe text is also escaped in data attribute');
foreach ([10=>false,11=>true,12=>true,13=>true] as $id=>$blocked) {
    preg_match('~<button(?=[^>]*data-forms-list-delete-id="' . $id . '")[^>]*>~', $html, $m);
    $check((bool)$m && (str_contains($m[0], ' disabled') === $blocked), 'Form #' . $id . ($blocked ? ' has disabled Delete' : ' has enabled Delete'));
}
$check(str_contains($html, 'Formularen har gemte indsendelser.') && str_contains($html, 'Formularen bruges i Page Builder.'), 'Blocked actions explain why deletion is unavailable');
$check(str_contains($html, 'data-modal-close') && str_contains($html, 'type="submit"'), 'Modal supports Cancel, Close and explicit Submit');
$empty = $render([], []);
$check(substr_count($empty,'role="group"')===0 && substr_count($empty,'data-admin-open-delete="forms-list-delete-dialog"')===0, 'Empty list has no fake row actions');
$check(substr_count($empty,'id="forms-list-delete-dialog"')===1, 'Empty list keeps one inert shared modal');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'SCSS/CSS unchanged and synchronized');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $v)===1 && version_compare($v[1],'2.4.4.78','>='), 'Internal version advanced to 2.4.4.78');
printf("Forms List Delete z20a: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
