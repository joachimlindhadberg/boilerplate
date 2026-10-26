<?php

declare(strict_types=1);
/** z20: Forms list row actions share Core markup; state mutation stays POST+CSRF. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $description) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $description . "\n";
    $ok ? ++$pass : ++$fail;
};
function t(string $key, array $params=[]): string { return str_starts_with($key,'forms:') ? substr($key,6) : $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_icon(string $name, string $class=''): string { return AdminIcons::render($name, $class); }
function admin_csrf_token(): string { return 'z20-test-csrf'; }
function admin_render_pagination(int $page, int $pages, callable $url, string $label): void {}
function admin_crud_pagination(int $total): array { return ['page'=>1,'pages'=>1,'from'=>$total?1:0,'to'=>$total,'offset'=>0]; }
$formsSource = file_get_contents($root . '/modules/forms/admin/views/partials/forms-list.php');
$subsSource = file_get_contents($root . '/modules/forms/admin/views/partials/submissions-list.php');
$controller = file_get_contents($root . '/modules/forms/admin/controllers/FormsAdminController.php');
$check(str_contains($formsSource, 'AdminListUI::actionGroup('), 'Forms table delegates its actions to Core');
$check(str_contains($subsSource, 'AdminListUI::actionGroup('), 'Submissions table delegates all visible actions to Core');
$check(!str_contains($formsSource, '<div class="admin-table-actions">') && !str_contains($subsSource, '<div class="admin-table-actions">'), 'Legacy handwritten action groups removed');
$check(str_contains($subsSource, "'icon' => 'eye'") && str_contains($subsSource, "'icon' => \$isNew ? 'mail' : 'check'") && str_contains($subsSource, "'icon' => 'delete'"), 'Open, mark status and delete use Core SVG icons');
$check(str_contains($subsSource, "'data-forms-mark-submission' => '1'") && str_contains($subsSource, "'data-forms-delete-submission' => '1'"), 'Native Core buttons retain distinct action hooks');
$check(str_contains($subsSource, 'form.requestSubmit()') && !str_contains($subsSource, 'form.submit()'), 'Mark status uses validated native form submission, not GET');
$check(str_contains($subsSource, 'form[data-forms-status-post]') && str_contains($subsSource, "mark.closest('td')"), 'Status action targets the same table row, even after live search');
$check(str_contains($subsSource, "'submission_action' => 'delete'") && str_contains($subsSource, 'data-forms-delete-id'), 'Existing Core Delete Dialog and hidden action retained');
$check(str_contains($subsSource, "d.querySelector('[data-forms-delete-name]').textContent=b.dataset.submissionName||'formularen'"), 'Delete modal uses escaped source and inert display text');
$check(str_contains($controller, "admin_verify_csrf(") && str_contains($controller, "'mark_read'") && str_contains($controller, "'mark_unread'") && str_contains($controller, "'delete'"), 'Server enforces original POST CSRF and status/delete actions');
$renderForms = static function (array $records) use ($root): string {
    $forms=$records; $deleteDependenciesById=[12=>['submissions'=>1,'pages'=>0],13=>['submissions'=>0,'pages'=>0]]; $search='q&x'; $page=1; $pages=1; $total=count($forms); $from=$total?1:0; $to=$total; $notice=null;
    ob_start(); require $root . '/modules/forms/admin/views/partials/forms-list.php'; return (string)ob_get_clean();
};
$renderSubs = static function (array $records) use ($root): string {
    $submissions=$records; $forms=[]; $selectedFormId=4; $selectedStatus='new'; $search='a&b'; $total=count($submissions);
    $paging=['page'=>1,'pages'=>1,'from'=>$total?1:0,'to'=>$total,'offset'=>0]; $notice=null;
    ob_start(); require $root . '/modules/forms/admin/views/partials/submissions-list.php'; return (string)ob_get_clean();
};
$formHtml = $renderForms([
    ['id'=>12,'name'=>'Test <svg onload=alert(1)>', 'status'=>'active','field_count'=>2,'submission_count'=>1,'new_submission_count'=>1,'updated_at'=>'2026-10-17 12:00:00'],
    ['id'=>13,'name'=>'En anden', 'status'=>'inactive','field_count'=>0,'submission_count'=>0,'new_submission_count'=>0,'updated_at'=>null],
]);
$check(substr_count($formHtml, 'role="group"') === 2, 'Forms renders exactly one Core action group per record');
$check(str_contains($formHtml, 'href="/admin/forms?screen=edit&amp;id=12"') && str_contains($formHtml, 'href="/admin/forms?screen=edit&amp;id=13"'), 'Edit links retain their real safe GET destinations');
$check(substr_count($formHtml, 'admin-icon-action__icon') === 4 && substr_count($formHtml, 'admin-table-actions--icons')===2, 'Forms row actions use compact Core SVG and layout');
$check(str_contains($formHtml, 'aria-label="Rediger: Test &lt;svg onload=alert(1)&gt;"'), 'Core action has row-specific accessible label');
$check(!str_contains($formHtml, '<svg onload=') && str_contains($formHtml, '&lt;svg onload=alert(1)&gt;'), 'Hostile form names do not inject HTML');
$check(str_contains($formHtml, 'name="q" value="q&amp;x"') && str_contains($formHtml, 'data-admin-live-list'), 'Forms live search remains intact');
$emptyForms=$renderForms([]);
$check(!str_contains($emptyForms, 'role="group"') && str_contains($emptyForms, 'admin-empty-state'), 'Empty forms list contains no fake row actions');
$subHtml=$renderSubs([
    ['id'=>21,'status'=>'new','form_name'=>'Form <script>alert(1)</script>','source_url'=>'/f','submitted_at'=>'2026-10-17 12:00:00'],
    ['id'=>22,'status'=>'read','form_name'=>'Second', 'source_url'=>'/g','submitted_at'=>'2026-10-17 13:00:00'],
]);
$check(substr_count($subHtml, 'role="group"')===2 && substr_count($subHtml, 'admin-icon-action__icon')===6, 'Two submission rows render three Core actions each');
$check(str_contains($subHtml, 'href="/admin/forms/submission?id=21"') && str_contains($subHtml, 'href="/admin/forms/submission?id=22"'), 'Open actions navigate to correct submission records');
$check(substr_count($subHtml, 'data-forms-mark-submission="1"')===2, 'Each submission has one Core status button');
$check(str_contains($subHtml, 'aria-label="Markér læst: Form &lt;script&gt;alert(1)&lt;/script&gt;"') && str_contains($subHtml, 'aria-label="Markér ulæst: Second"'), 'Accessible status labels reflect record status');
$check(substr_count($subHtml, 'data-forms-status-post hidden')===2 && substr_count($subHtml, 'method="post" action="/admin/forms/submissions"')===3, 'One independent hidden status POST per row plus shared delete POST');
$check(str_contains($subHtml, 'name="submission_action" value="mark_read"') && str_contains($subHtml, 'name="submission_action" value="mark_unread"'), 'Existing read/unread server actions preserved');
$check(str_contains($subHtml, 'name="id" value="21"') && str_contains($subHtml, 'name="id" value="22"'), 'Status forms retain row-specific IDs');
$check(substr_count($subHtml, 'name="csrf_token" value="z20-test-csrf"')===3, 'Both state forms and delete form include CSRF tokens');
$check(substr_count($subHtml, 'name="return_url"')===3 && str_contains($subHtml, 'form_id=4') && str_contains($subHtml, 'q=a%26b') && str_contains($subHtml, 'status=new'), 'Filtered return URL preserved across both state and delete actions');
$check(substr_count($subHtml, 'data-forms-delete-submission="1"')===2 && substr_count($subHtml, 'id="forms-submissions-delete-dialog"')===1, 'Existing delete hooks and one Core modal remain');
$check(strpos($subHtml, 'data-forms-delete-dialog') > strpos($subHtml, '</section>'), 'Delete modal stays outside live-list replacement');
$check(!str_contains($subHtml, '<script>alert(1)</script>') && str_contains($subHtml, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Submission names are always safely escaped');
$check(str_contains($subHtml, 'data-admin-live-search') && str_contains($subHtml, 'name="status"') && str_contains($subHtml, 'name="form_id"'), 'Live search and both filters remain present');
$emptySubs=$renderSubs([]);
$check(!str_contains($emptySubs, 'data-forms-mark-submission="1"') && substr_count($emptySubs, 'id="forms-submissions-delete-dialog"')===1, 'Empty submissions list has no fake actions and retains inert delete modal');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss')===file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS remain byte-identical');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $v)===1 && version_compare($v[1], '2.4.4.77','>='), 'z20 release version 2.4.4.77');

printf("Forms List Actions V2.4.4z20: %d PASS / %d FAIL\n",$pass,$fail);
exit($fail?1:0);
