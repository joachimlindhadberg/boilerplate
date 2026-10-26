<?php

declare(strict_types=1);
/** z14: Forms permanent delete dialogs delegate markup to Core without changing endpoints. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
function t(string $key): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
function admin_e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'safe-z14-fixture-token'; }
function admin_icon(string $icon, string $class=''): string { return AdminIcons::render($icon, $class); }
function admin_icon_action_button(string $icon, string $label, array $attrs=[], string $tone='neutral'): string { return AdminIcons::actionButton($icon, $label, $attrs, $tone); }
function admin_crud_pagination(int $total): array { return ['page'=>1, 'pages'=>1,'from'=>1,'to'=>$total, 'perPage'=>10, 'offset'=>0]; }
function admin_render_pagination(int $page, int $pages, callable $url, string $heading): void {}
$editor = (string)file_get_contents($root . '/modules/forms/admin/views/partials/form-editor.php');
$list = (string)file_get_contents($root . '/modules/forms/admin/views/partials/submissions-list.php');
$detail = (string)file_get_contents($root . '/modules/forms/admin/views/partials/submission-detail.php');
$controller = (string)file_get_contents($root . '/modules/forms/admin/controllers/FormsAdminController.php');
$repository = (string)file_get_contents($root . '/modules/forms/FormsRepository.php');
foreach (['Editor'=>$editor, 'Submissions list'=>$list, 'Submission detail'=>$detail] as $name=>$code) {
    $check(substr_count($code, 'AdminDeleteDialog::render(')===1, "$name uses exactly one Core renderer");
    $check(!str_contains($code, '<dialog class="admin-modal admin-modal--sm" data-admin-modal data-forms-delete-dialog') && !str_contains($code, '<dialog class="admin-modal admin-modal--sm" data-admin-modal id="forms-delete-dialog"'), "$name no longer duplicates permanent-delete modal markup");
}
$check(str_contains($editor, "'id' => 'forms-delete-dialog'") && str_contains($editor, 'data-admin-open-delete="forms-delete-dialog"'), 'Form editor retains existing trigger and dialog ID');
$check(str_contains($editor, "(int)\$deleteDependencies['submissions'] === 0") && str_contains($editor, "(int)\$deleteDependencies['pages'] === 0"), 'Form editor requires zero submissions and no Pages dependencies before dialog exists');
$check(str_contains($editor, "'form_action' => 'delete'") && str_contains($editor, "'confirm_delete' => 'permanent'"), 'Form editor retains explicit permanent delete confirmation fields');
$check(str_contains($list, "'dialogAttributes' => ['data-forms-delete-dialog' => true]") && str_contains($detail, "'dialogAttributes' => ['data-forms-delete-dialog' => true]"), 'Both submission dialogs keep delegated JS hooks');
$check(str_contains($list, "'attributes' => ['data-forms-delete-id' => true]") && str_contains($list, "'subjectAttributes' => ['data-forms-delete-name' => true]"), 'Submissions list keeps dynamic id/text hooks');
$check(str_contains($list, "'return_url' => \$returnUrl"), 'Submissions list retains return-to-filtered-list field');
$check(str_contains($list, ".textContent=b.dataset.submissionName||'formularen'") && str_contains($list, 'event.target.closest(\'[data-forms-delete-submission]\')'), 'Delegated delete handler remains compatible with live search and uses safe textContent');
$check(str_contains($detail, "'action' => '/admin/forms/submission?id=' . \$id") && str_contains($list, "'action' => '/admin/forms/submissions'"), 'Original distinct submission POST endpoints retained');
$check(str_contains($controller, 'admin_verify_csrf(') && str_contains($controller, 'deleteForm($id)') && str_contains($controller, 'deleteSubmission($id)'), 'No changes to server CSRF/deletion flows');
$check(str_contains($repository, 'function formDeleteDependencies(') && str_contains($repository, 'FOR UPDATE'), 'Forms repository dependency/transaction locking unchanged');

// Test the real editor with an eligible test form and hostile display name.
$formData = ['name'=>'<img src=x onerror=alert(1)>', 'status'=>'active', 'submit_label'=>'Send'];
$fields=[]; $builderRows=[]; $errors=[]; $fieldTypes=['text'=>'Tekst']; $formTemplates=[]; $publishedPages=[];
$notificationEmail=''; $isEdit=true; $formId=42; $deleteDependencies=['submissions'=>0,'pages'=>0];
ob_start(); require $root . '/modules/forms/admin/views/partials/form-editor.php'; $html=(string)ob_get_clean();
$check(substr_count($html, 'id="forms-delete-dialog"')===1 && substr_count($html, '<dialog ')>=5, 'Eligible editor emits one Core delete dialog alongside unchanged builder dialogs');
$check(str_contains($html, 'method="post" action="/admin/forms?screen=edit&amp;id=42"'), 'Editor delete request remains POST to original endpoint');
$check(str_contains($html, 'name="csrf_token" value="safe-z14-fixture-token"') && str_contains($html, 'name="confirm_delete" value="permanent"'), 'Editor Core dialog carries CSRF and permanent confirmation');
$check(str_contains($html, 'name="form_action" value="delete"') && str_contains($html, 'name="id" value="42"'), 'Editor Core dialog carries exact Forms action and ID');
$check(!str_contains($html, '<img src=x') && str_contains($html, '&lt;img src=x onerror=alert(1)&gt;'), 'Untrusted form title cannot inject HTML');
$check(str_contains($html, 'data-modal-close') && str_contains($html, 'data-admin-open-delete="forms-delete-dialog"'), 'Editor dialog still supports cancel and existing open trigger');

// Run actual isolated submissions list render, with untrusted user-provided form name.
$submissions=[['id'=>9,'status'=>'new','form_name'=>'<svg onload=alert(1)>', 'source_url'=>'/', 'submitted_at'=>'2026-10-17 12:00:00']];
$forms=[]; $selectedFormId=0; $selectedStatus='new'; $search='t&d'; $total=1;
$paging=['page'=>1,'pages'=>1,'from'=>1,'to'=>1];
ob_start(); require $root . '/modules/forms/admin/views/partials/submissions-list.php'; $listHtml=(string)ob_get_clean();
$check(substr_count($listHtml, '<dialog ') === 1 && str_contains($listHtml, 'data-forms-delete-dialog'), 'Submissions list emits one Core dialog outside live table');
$check(strpos($listHtml, 'data-forms-delete-dialog') > strpos($listHtml, '</section>'), 'Shared list dialog remains outside dynamic live search region');
$check(str_contains($listHtml, 'name="id" value="" data-forms-delete-id') && str_contains($listHtml, 'data-forms-delete-name'), 'List JS can populate hidden ID and inert subject');
$check(str_contains($listHtml, 'name="submission_action" value="delete"') && str_contains($listHtml, 'name="return_url"'), 'List has correct POST deletion action and return URL');
$check(str_contains($listHtml, 'method="post" action="/admin/forms/submissions"'), 'Submissions list keeps POST endpoint');
$check(str_contains($listHtml, 'data-submission-name="&lt;svg onload=alert(1)&gt;"') && !str_contains($listHtml, '<svg onload='), 'User-supplied list name escaped in trigger');
$check(str_contains($listHtml, 'name="csrf_token" value="safe-z14-fixture-token"'), 'Submissions list CSRF stays active');
$check(str_contains($listHtml, 'status=new') && str_contains($listHtml, 'q=t%26d'), 'Filtered-list return state is retained');

$submission=['id'=>8,'form_id'=>17,'form_name'=>'<script>alert(1)</script>','submitted_at'=>'2026-10-17 12:00:00','source_url'=>'/', 'data'=>[]];
ob_start(); require $root . '/modules/forms/admin/views/partials/submission-detail.php'; $detailHtml=(string)ob_get_clean();
$check(substr_count($detailHtml, '<dialog ')===1 && str_contains($detailHtml, 'data-forms-delete-dialog'), 'Submission detail renders one Core dialog');
$check(str_contains($detailHtml, 'action="/admin/forms/submission?id=8"') && str_contains($detailHtml, 'name="id" value="8"'), 'Submission detail retains individual POST route and ID');
$check(str_contains($detailHtml, 'name="submission_action" value="delete"') && str_contains($detailHtml, 'name="csrf_token" value="safe-z14-fixture-token"'), 'Submission detail retains CSRF and delete action');
$check(!str_contains($detailHtml, '<script>alert(1)</script>') && str_contains($detailHtml, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Untrusted submission form name escaped');
$check(str_contains($detailHtml, '<p>Er du sikker på, at du vil slette denne indsendelse permanent?</p>'), 'Core renders natural text-only confirmation without empty subject');
$check(str_contains($detailHtml, 'document.querySelector(\'[data-forms-delete-dialog]\')'), 'Existing detail modal script still targets Core dialog');

$contract=[
  'id'=>'test-safe', 'title'=>'Slet', 'warning'=>'Permanent', 'action'=>'/admin/forms/submissions',
  'csrfToken'=>'token', 'prefix'=>'Vil du slette', 'subject'=>'', 'suffix'=>'?', 'confirmLabel'=>'Slet',
  'dialogAttributes'=>['data-forms-delete-dialog'=>true],
];
$rendered=AdminDeleteDialog::render($contract);
$check(str_contains($rendered,'id="test-safe" aria-labelledby="test-safe-title" data-forms-delete-dialog'), 'Core allows safe data hooks on the dialog element');
$check(!str_contains(AdminDeleteDialog::render(array_replace($contract,['suffix'=>'','prefix'=>'Bekræft sletning'])), '<strong>'), 'Text-only dialog has no empty emphasis element');
$check(str_contains(AdminDeleteDialog::render(array_replace($contract,['subjectAttributes'=>['data-forms-delete-name'=>true]])),'<strong data-forms-delete-name></strong>'), 'Dynamic empty subject still retains JS placeholder');
foreach ([['dialogAttributes'=>['onclick'=>'alert(1)']], ['dialogAttributes'=>['id'=>'other']], ['dialogAttributes'=>['data-hook'=>['x']]]] as $i=>$invalid) {
  try { AdminDeleteDialog::render(array_replace($contract,$invalid)); $check(false,'Unsafe dialog attribute rejected #' . ($i+1)); }
  catch (InvalidArgumentException) { $check(true,'Unsafe dialog attribute rejected #' . ($i+1)); }
}
$check(file_get_contents($root.'/public_html/admin/assets/css/admin.css') === file_get_contents($root.'/public_html/admin/assets/scss/admin.scss'), 'No Core style drift');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", (string)file_get_contents($root.'/public_html/incl/init.php'),$v)===1 && version_compare($v[1], '2.4.4.71', '>='), 'Internal z14 version');
echo "Forms Core Delete Dialog z14: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
