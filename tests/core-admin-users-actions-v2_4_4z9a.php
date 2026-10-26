<?php

declare(strict_types=1);

/** Regression contract for the z9a Users list actions, modal and toolbar order. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminIcons.php';
require_once $root . '/core/classes/AdminListUI.php';
require_once $root . '/core/classes/AdminDeleteDialog.php';
$dictionary = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$locale = 'da';
function t(string $key, array $params = []): string { global $dictionary, $locale; $result = (string)($dictionary[$key][$locale] ?? $key); foreach ($params as $k=>$v) $result=str_replace('{'.$k.'}',(string)$v,$result); return $result; }
function admin_e(mixed $v): string { return AdminEscaper::text($v); }
function admin_icon(string $name, string $class=''): string { return AdminIcons::render($name,$class); }
function admin_csrf_token(): string { return 'synthetic-z9a-csrf'; }
function admin_users_url(array $changes = []): string { $v=array_merge(['status'=>$_GET['status']??'','role'=>(int)($_GET['role']??0),'q'=>$_GET['q']??'','page'=>(int)($_GET['page']??1)],$changes); $v=array_filter($v,static fn($x)=>$x!=='' && $x!==null && $x!==0 && $x!==1); return '/admin/brugere'.($v?'?'.http_build_query($v):''); }
function admin_user_role_label(string $role): string { return $role; }
function admin_user_status_class(string $status): string { return $status; }
function admin_user_status_label(string $status): string { return $status; }
function admin_render_pagination(int $page, int $pages, callable $url, string $label): void { if ($pages>1) echo '<a class="test-pagination" href="'.admin_e($url(2)).'">'.admin_e($label).'</a>'; }
$valid = AdminListUI::actionGroup([
    ['icon'=>'eye', 'label'=>'Vis <bruger>', 'url'=>'/admin/brugere?mode=view&id=7'],
    ['icon'=>'edit', 'label'=>'Rediger', 'url'=>'/admin/brugere?mode=edit&id=7'],
    ['type'=>'button', 'icon'=>'delete', 'label'=>'Slet <bruger>', 'tone'=>'danger', 'attributes'=>['data-admin-open-delete'=>'user-list-delete-dialog', 'data-user-delete-id'=>'7', 'data-user-delete-name'=>'<bruger>', 'disabled'=>false]],
], 'Handlinger for <bruger>');
$check(substr_count($valid, 'admin-icon-action__icon')===3, 'View, Edit and Delete render as three Core SVG actions');
$check(str_contains($valid, 'mode=view&amp;id=7') && str_contains($valid, 'mode=edit&amp;id=7'), 'Both existing GET endpoints preserved');
$check(str_contains($valid, 'type="button"') && !str_contains($valid,'type="submit"'), 'Delete is a non-submitting Core button');
$check(str_contains($valid, 'admin-icon-action--danger'), 'Delete uses Core danger state');
$check(str_contains($valid, 'data-user-delete-name="&lt;bruger&gt;"') && !str_contains($valid,'<bruger>'), 'Names escaped in attributes, not injected as markup');
$blocked=AdminListUI::actionGroup([['type'=>'button','icon'=>'delete','label'=>'Blocked','tone'=>'danger','attributes'=>['disabled'=>true]]], 'Blocked');
$check(str_contains($blocked,' disabled'), 'Blocked Delete is a native disabled button');
$check(!str_contains($valid, ' disabled'), 'Allowed Delete remains clickable');
foreach ([
    ['type'=>'button','icon'=>'delete','label'=>'Bad','url'=>'/admin/brugere','attributes'=>[]],
    ['type'=>'button','icon'=>'delete','label'=>'Bad','attributes'=>['type'=>'submit']],
    ['type'=>'button','icon'=>'delete','label'=>'Bad','attributes'=>['form'=>'other-form']],
    ['type'=>'button','icon'=>'delete','label'=>'Bad','attributes'=>['onclick'=>'alert(1)']],
    ['type'=>'link','icon'=>'eye','label'=>'Bad','url'=>'https://example.com'],
    ['type'=>'link','icon'=>'eye','label'=>'Bad','url'=>'/admin/brugere','attributes'=>['onclick'=>'alert(1)']],
    ['type'=>'unexpected','icon'=>'eye','label'=>'Bad','url'=>'/admin/brugere'],
    ['type'=>'button','icon'=>'missing','label'=>'Bad','attributes'=>[]],
] as $index=>$bad) {
    try { AdminListUI::actionGroup([$bad], 'Bad'); $check(false, 'Invalid action specification #'.($index+1)); }
    catch (InvalidArgumentException) { $check(true, 'Invalid action specification #'.($index+1)); }
}
$check(str_contains(AdminListUI::actionLinks([['icon'=>'eye','label'=>'View','url'=>'/admin/brugere?mode=view&id=4']], 'Users'),'mode=view'), 'Backward-compatible navigation-only actionLinks retained');
try { AdminListUI::actionLinks([['icon'=>'delete','label'=>'Bad','type'=>'button','attributes'=>[]]], 'Bad'); $check(false, 'Legacy links reject buttons'); }
catch (InvalidArgumentException) { $check(true, 'Legacy links reject buttons'); }

$source=(string)file_get_contents($root.'/public_html/admin/views/users/index.php');
$toolbarStart=strpos($source, '<form class="admin-list-search');
$toolbarEnd=strpos($source, '</form>', $toolbarStart ?: 0);
$toolbar=substr($source, $toolbarStart ?: 0, ($toolbarEnd ?: 0)-($toolbarStart ?: 0));
$check(strpos($toolbar, '<select name="role"') < strpos($toolbar,'<input type="search" name="q"'), 'Role select comes before search input in DOM and keyboard order');
$check(str_contains($source, 'AdminListUI::actionGroup('), 'Users uses shared Core action group');
$check(str_contains($source,'data-user-delete-id') && str_contains($source,'data-user-delete-name'), 'Row buttons carry safe User modal data');
$check(substr_count($source, "'id' => 'user-list-delete-dialog'")===1 && str_contains($source,'AdminDeleteDialog::render('), 'Exactly one shared Core delete dialog');
$check(strpos($source, "'id' => 'user-list-delete-dialog'") > strpos($source,'</section>'), 'Delete modal is outside live-list replacement region');
$check(str_contains($source, "'csrfToken' => admin_csrf_token()") && str_contains($source, "'confirm_delete' => 'permanent'"), 'Modal submits CSRF token and explicit permanent confirmation');
$check(str_contains($source, "'action' => '/admin/brugere?mode=delete'") && str_contains((string)file_get_contents($root.'/core/classes/AdminDeleteDialog.php'), 'method="post"'), 'Delete is POST to existing Users controller');
$check(str_contains($source, "'disabled' => \$deleteBlocked"), 'Disabled state computed server-side');
$fn=(string)file_get_contents($root.'/public_html/admin/incl/functions.php');
$check(str_contains($fn,"'activeAdminCount' => \$users->countActiveAdminsExcluding()"), 'Admin count is read once per listing, not once per row');
$check(str_contains($source,'$uid===$currentUserId') && str_contains($source,'$activeAdminCount < 2'), 'Self and last-active-admin deletion controls disabled');
$controller=(string)file_get_contents($root.'/public_html/admin/users.php');
$check(str_contains($controller,"if (\$mode === 'view')") && str_contains($controller,"if (\$mode === 'delete')"), 'Existing Users View and Delete endpoints are present');
$check(str_contains($controller, 'admin_verify_csrf(') && str_contains($controller,"(\$_POST['confirm_delete'] ?? '') !== 'permanent'"), 'Delete endpoint retains CSRF and explicit confirmation');
$check(str_contains($controller, '\$isSelf || \$isLastActiveAdmin') || str_contains($controller,'$isSelf || $isLastActiveAdmin'), 'Delete endpoint retains self/last-admin safety');
$js=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$check(str_contains($js, "dialog.id === 'user-list-delete-dialog'") && str_contains($js,'[data-user-list-delete-id]'), 'Delegated Core modal handler supports Users');
$check(str_contains($js,'name.textContent = button.dataset.userDeleteName'), 'Dialog name assigned through textContent, not innerHTML');
$check(str_contains($js,"event.target.closest('[data-admin-open-delete]')"), 'Delegation remains compatible with live-search rows');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Core SCSS and CSS remain synchronized');
foreach (['users.cannot_delete_self','users.cannot_delete_last_admin'] as $key) {
    $check(isset($dictionary[$key]) && count(array_intersect(['da','en','de','sv','nb'],array_keys($dictionary[$key])))===5, "All five translations for $key");
}
// Runtime template checks for both disabled and allowed rows, without a database.
$end = "require __DIR__ . '/../partials/layout.php';";
$temp=tempnam(sys_get_temp_dir(),'users-z9a-');
try {
    file_put_contents($temp,str_replace($end,'echo $bodyHtml;',$source));
    $render=static function (array $records, int $adminCount) use ($temp): string {
        $_GET=['status'=>'','q'=>'','role'=>0,'page'=>1];
        $usersData=['items'=>$records,'counts'=>['all'=>count($records),'active'=>count($records),'inactive'=>0,'banned'=>0], 'roles'=>[['id'=>1,'role_name'=>'admin']], 'search'=>'','status'=>'','roleId'=>0,'page'=>1,'pages'=>1,'total'=>count($records),'from'=>1,'to'=>count($records),'activeAdminCount'=>$adminCount];
        $user=['id'=>1]; $notice=null; ob_start(); include $temp; return (string)ob_get_clean();
    };
    $users=[
        ['id'=>1,'firstname'=>'Current','lastname'=>'Admin','email'=>'self@example.test','status'=>'active','role_name'=>'admin','last_login'=>null],
        ['id'=>2,'firstname'=>'Ada','lastname'=>'<script>','email'=>'ada@example.test','status'=>'active','role_name'=>'editor','last_login'=>null],
    ];
    $html=$render($users,1);
    $check(substr_count($html,'admin-table-actions--icons')===2, 'Two user rows produce exactly two action groups');
    $check(substr_count($html,'aria-label="Vis:')===2 && substr_count($html,'aria-label="Rediger:')===2, 'Both rows expose View and Edit');
    $check(str_contains($html,'data-user-delete-id="1" disabled') || (str_contains($html,'data-user-delete-id="1"') && preg_match('/data-user-delete-id="1"[^>]*disabled/', $html)===1), 'Current user cannot click Delete');
    $check(str_contains($html,'data-user-delete-id="2"') && preg_match('/data-user-delete-id="2"[^>]*disabled/', $html)!==1, 'Another user can request Delete confirmation');
    $check(!str_contains($html,'<script>') && str_contains($html,'Ada &lt;script&gt;'), 'User-supplied names cannot inject HTML');
    $check(str_contains($html, 'name="csrf_token" value="synthetic-z9a-csrf"'), 'Modal includes real CSRF helper output');
    $htmlLastAdmin=$render([['id'=>3,'firstname'=>'Last','lastname'=>'Admin','email'=>'last@example.test','status'=>'active','role_name'=>'admin','last_login'=>null]],1);
    $check(preg_match('/data-user-delete-id="3"[^>]*disabled/', $htmlLastAdmin)===1, 'Last active admin Delete is disabled even if not the current account');
    $empty=$render([],1);
    $check(substr_count($empty,'user-list-delete-dialog')>=1 && !str_contains($empty,'data-user-delete-id="'), 'Empty listing retains modal but no fake row action');
} finally { @unlink($temp); }
$versionSource = (string)file_get_contents($root.'/public_html/incl/init.php');
$check((bool)preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/", $versionSource, $versionMatch) && version_compare($versionMatch[1], '2.4.4.62', '>='), 'Internal release retains z9a compatibility (>=2.4.4.62)');
echo "Users Actions V2.4.4z9a: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
