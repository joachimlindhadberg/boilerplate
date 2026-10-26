<?php

declare(strict_types=1);

/** Core delete dialog contract: HTML, security boundary and z11b backwards compatibility. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminDeleteDialog.php';
$pass = 0; $fail = 0;
$check = static function (bool $valid, string $name) use (&$pass, &$fail): void {
    echo ($valid ? 'PASS' : 'FAIL') . ': ' . $name . "\n";
    if ($valid) ++$pass; else ++$fail;
};
$navigation = [
    'id' => 'nav-list-delete-dialog', 'titleId' => 'nav-list-delete-title',
    'title' => 'Slet menu', 'warning' => 'Handlingen kan ikke fortrydes.',
    'action' => '/admin/navigation?mode=delete&id=0', 'csrfToken' => 'csrf-demo',
    'formAttributes' => ['data-nav-list-delete-form' => true],
    'fields' => ['action' => 'delete_menu', 'confirmed' => '1'],
    'prefix' => 'Vil du slette menuen', 'subject' => '',
    'subjectAttributes' => ['data-nav-list-delete-name' => true],
    'suffix' => 'og alle dens menupunkter? Siderne slettes ikke.',
    'confirmLabel' => 'Slet menu', 'closeLabel' => 'Luk', 'cancelLabel' => 'Annuller',
];
$html = AdminDeleteDialog::render($navigation);
$check(str_starts_with($html, '<dialog class="admin-modal admin-modal--sm" data-admin-modal'), 'Uses Core native modal surface');
$check(substr_count($html, '<dialog')===1 && substr_count($html, '</dialog>')===1, 'Exactly one well-delimited dialog');
$check(str_contains($html, 'id="nav-list-delete-dialog" aria-labelledby="nav-list-delete-title"'), 'Accessible title association');
$check(str_contains($html, '<h2 id="nav-list-delete-title">Slet menu</h2>'), 'Dialog heading is escaped text');
$check(str_contains($html, 'method="post"'), 'Delete submission always POST');
$check(str_contains($html, 'action="/admin/navigation?mode=delete&amp;id=0"'), 'Local endpoint is escaped exactly once');
$check(str_contains($html, 'data-nav-list-delete-form'), 'Legacy Navigation form hook preserved');
$check(str_contains($html, 'name="csrf_token" value="csrf-demo"'), 'CSRF input present');
$check(str_contains($html, 'name="action" value="delete_menu"'), 'Existing server action token preserved');
$check(str_contains($html, 'name="confirmed" value="1"'), 'Explicit server confirmation preserved');
$check(str_contains($html, '<strong data-nav-list-delete-name></strong>'), 'Dynamic target rendered as inert text placeholder');
$check(str_contains($html, 'data-modal-close') && str_contains($html, '>Annuller</button>'), 'Core modal cancel and close controls');
$check(str_contains($html, 'class="admin-button admin-button--danger" type="submit"'), 'Danger confirmation explicitly submits form');
$check(!str_contains($html, 'onclick=') && !str_contains($html, '<script'), 'No inline script or click event handlers');
$user = $navigation;
$user['id'] = 'user-list-delete-dialog'; $user['titleId'] = 'user-list-delete-title';
$user['action'] = '/admin/brugere?mode=delete';
$user['fields'] = ['id'=>['value'=>'','attributes'=>['data-user-list-delete-id'=>true]], 'confirm_delete'=>'permanent'];
$user['subjectAttributes'] = ['data-user-list-delete-name'=>true];
unset($user['formAttributes']);
$userHtml = AdminDeleteDialog::render($user);
$check(str_contains($userHtml, 'name="id" value="" data-user-list-delete-id'), 'Dynamic user ID hidden input compatible with JS');
$check(str_contains($userHtml, 'name="confirm_delete" value="permanent"'), 'Users permanent-delete confirmation preserved');
$check(str_contains($userHtml, 'data-user-list-delete-name'), 'Users target text compatible with JS');
$check(!str_contains($userHtml, 'data-nav-list-delete-form'), 'Module-specific hook does not leak into other dialogs');
$dangerous = $navigation;
$dangerous['title'] = '<script>alert(1)</script>';
$dangerous['csrfToken'] = '" onfocus="alert(1)';
$dangerous['subject'] = '<img src=x onerror=alert(1)>';
$dangerous['fields'] = ['confirmed' => '" onclick="alert(1)'];
$dangerous['subjectAttributes'] = ['data-nav-list-delete-name' => '" onclick="alert(1)'];
$escaped = AdminDeleteDialog::render($dangerous);
$check(!str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'), 'Untrusted heading cannot inject HTML');
$check(!str_contains($escaped, '<img ') && str_contains($escaped, '&lt;img '), 'Untrusted subject cannot inject HTML');
$check(!str_contains($escaped, '" onclick="alert(1)') && str_contains($escaped, '&quot; onclick=&quot;alert(1)'), 'Attribute injection escaped');
$check(str_contains($escaped,'value="&quot; onfocus=&quot;alert(1)"'), 'CSRF token safely escaped');
$check(substr_count($escaped, 'name="csrf_token"')===1, 'Core never duplicates CSRF field');
$invalid = [
    ['id' => '../broken'], ['id'=>''], ['titleId'=>'foo" aria-hidden="true'],
    ['title'=>''], ['confirmLabel'=>''], ['csrfToken'=>''],
    ['action'=>'https://example.com'], ['action'=>'//evil.test'],
    ['action'=>'javascript:alert(1)'], ['action'=>'/admin\\navigation'],
    ['action'=>'/admin/brugere\nX-Test:bad'], ['action'=>'/admin-other'],
    ['fields'=>['csrf_token'=>'override']], ['fields'=>['id"'=>'1']],
    ['fields'=>['id'=>['value'=>[]]]], ['fields'=>['id'=>(object)[]]],
    ['fields'=>['id'=>['value'=>'7','attributes'=>['onclick'=>'evil()']]]],
    ['formAttributes'=>['style'=>'display:none']],
    ['subjectAttributes'=>['id'=>'hijack']],
    ['subjectAttributes'=>['data-safe'=>[]]],
    ['cancelLabel'=>''], ['closeLabel'=>''],
];
foreach ($invalid as $i => $override) {
    try { AdminDeleteDialog::render(array_replace($navigation, $override)); $check(false, 'Unsafe spec rejected #' . ($i+1)); }
    catch (InvalidArgumentException) { $check(true, 'Unsafe spec rejected #' . ($i+1)); }
}
foreach (['navigation/index.php','navigation/edit.php','users/index.php'] as $view) {
    $source = (string)file_get_contents($root . '/public_html/admin/views/' . $view);
    $check(str_contains($source, 'AdminDeleteDialog::render('), 'Core component consumed by ' . $view);
    $check(!str_contains($source, '<dialog class="admin-modal'), 'No duplicate delete markup in ' . $view);
}
$navigationSource = (string)file_get_contents($root . '/public_html/admin/views/navigation/index.php');
$editorSource = (string)file_get_contents($root . '/public_html/admin/views/navigation/edit.php');
$usersSource = (string)file_get_contents($root . '/public_html/admin/views/users/index.php');
$check(str_contains($navigationSource, "'formAttributes' => ['data-nav-list-delete-form' => true]"), 'Navigation list delegates dynamic action to unchanged JS hook');
$check(str_contains($editorSource, "if (\$placement === 'none')"), 'Navigation editor still withholds modal for protected menus');
$check(str_contains($usersSource, "'csrfToken' => admin_csrf_token()"), 'Users obtains current CSRF token server-side');
$check(str_contains($usersSource, "'confirm_delete' => 'permanent'"), 'Users keeps permanent deletion acknowledgement');
$check(str_contains($usersSource, 'data-admin-live-list') && str_contains($usersSource, 'AdminListUI::actionGroup('), 'Users live-search and action buttons retained');
$check(str_contains($navigationSource, 'AdminListUI::actionGroup('), 'Navigation action buttons retained');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS still synchronized');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $v)===1 && version_compare($v[1], '2.4.4.67', '>='), 'Internal version 2.4.4.67');
echo "Core Admin Delete Dialog V2.4.4z12: {$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
