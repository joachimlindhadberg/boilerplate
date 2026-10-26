<?php
/** V2.4.4z20i – Forms vertically-stacked field controls and shared Core visibility switches. */
declare(strict_types=1);
$root = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
if (!function_exists('t')) {
    function t(string $key): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
}
require_once $root . '/public_html/admin/incl/page-builder-functions.php';
$pass=0;$fail=0;
$check=static function (bool $condition,string $message) use (&$pass,&$fail):void {
    $condition ? ++$pass : ++$fail;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $message . "\n";
};
$fieldTypes=['text'=>'Tekst','email'=>'E-mail','tel'=>'Telefon','textarea'=>'Tekstområde','select'=>'Dropdown','radio'=>'Radio','checkbox'=>'Checkbox'];
$renderForm=static function (string $status) use ($root,$fieldTypes):string {
    $formData=['name'=>'Test & Kontakt','status'=>$status,'submit_label'=>'Send','success_action'=>'message', 'save_submissions'=>1];
    $fields=[]; $builderRows=[]; $formTemplates=[];$errors=[];$publishedPages=[];$notificationEmail='';$isEdit=false;$formId=0;
    ob_start();require $root . '/modules/forms/admin/views/partials/form-editor.php';return (string)ob_get_clean();
};
foreach (['inactive'=>false] as $status=>$enabled) {
    $html=$renderForm($status);
    $form=strstr($html,'</form>',true);
    $check($form!==false,"{$status}: editor form renders");
    $check(str_contains($form,'name="status" value="'.$status.'" data-forms-status-value'),"{$status}: original status POST value retained");
    $check(substr_count($form,'name="status"')===1,"{$status}: exactly one status POST field");
    $check(str_contains($form,'id="forms-visibility" type="checkbox" role="switch"'),"{$status}: shared native Core switch rendered");
    $check(str_contains($form,'data-forms-visibility-switch="1"'),"{$status}: status change hook present");
    $check((bool)preg_match('/id="forms-visibility" type="checkbox" role="switch"'.($enabled?' checked':'').'/',$form),"{$status}: switch initial state matches DB");
    $check(!str_contains($form,'<select id="forms-status"'),"{$status}: old select removed");
    $check(str_contains($form,'for="forms-visibility"')&&str_contains($form,'data-admin-toggle-state'),"{$status}: labelled switch and live state");
    $check(str_contains($form,'name="save_submissions"'),"{$status}: independent saved-submissions control retained");
    $check(str_contains($form,'data-admin-editor-tab="fields"')&&str_contains($form,'data-admin-editor-tab="emails"'),"{$status}: existing editor tabs retained");
}
$check(str_contains(AdminToggle::render('forms-test-active', t('Synlighed'), true),'checked'), 'Active state is checked through Core control');
$check(!str_contains(AdminToggle::render('forms-test-inactive', t('Synlighed'), false),'checked'), 'Inactive state is unchecked through Core control');
foreach ($fieldTypes as $type=>$label) {
    $field = forms_admin_field_card(['label'=>'Firma & Navn','field_key'=>'stable_id','field_type'=>$type,'placeholder'=>'Dit navn','help_text'=>'Hjælp','is_required'=>1,'options'=>"A\nB"],'0','0',$fieldTypes);
    $check(substr_count($field,'class="forms-field__editor-stack"')===1,"{$type}: one vertical editor stack");
    $check(!str_contains($field,'class="admin-form-row"'),"{$type}: no two-up field pair");
    $check(str_contains($field,'[field_key]" value="stable_id"'),"{$type}: persistent technical field key");
    $check(str_contains($field,'[is_required]" value="1"'),"{$type}: required control preserved");
    $check(str_contains($field,'[placeholder]"')&&str_contains($field,'[help_text]"'),"{$type}: existing input fields retained");
    $check(str_contains($field,'[options]"'),"{$type}: select/radio options field kept without changing payload");
    $check(!str_contains($field,'Firma & Navn')&&str_contains($field,'Firma &amp; Navn'),"{$type}: user-supplied labels escaped");
}
foreach (['active'=>true,'hidden'=>false] as $status=>$enabled) {
    foreach ([true,false] as $v2) {
        $prefix='blocks[0][data][columns][1][blocks][0]';
        $child=admin_page_builder_grid_child_editor(['type'=>'text','status'=>$status,'data'=>['title'=>'Demo']],$prefix,$v2);
        $suffix=$v2?'v2':'legacy';
        $check(str_contains($child,'name="'.$prefix.'[status]" value="'.$status.'" data-page-child-status'),"{$suffix}/{$status}: original Page child storage retained");
        $check(substr_count($child,'data-page-child-status')===1&&substr_count($child,'name="'.$prefix.'[status]"')===1,"{$suffix}/{$status}: one status field");
        $check(str_contains($child,'data-page-child-visibility="1"')&&str_contains($child,'role="switch"'),"{$suffix}/{$status}: Core visibility toggle");
        $check((bool)preg_match('/data-page-child-visibility="1"'.($enabled?' checked':'').'/',$child) || (bool)preg_match('/role="switch"'.($enabled?' checked':'').' data-page-child-visibility/',$child),"{$suffix}/{$status}: saved switch position retained");
        $check(!preg_match('/<select[^>]+\[status\]/',$child),"{$suffix}/{$status}: visibility dropdown removed");
        $check(str_contains($child,'data-grid-child-drag')&&str_contains($child,'data-grid-child-remove'),"{$suffix}/{$status}: builder controls retained");
    }
}
$pageJs=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$formsSource=(string)file_get_contents($root.'/modules/forms/admin/views/partials/form-editor.php');
$check(str_contains($pageJs,"status.value = event.target.checked ? 'active' : 'hidden'"),'Page block UI maps toggle to active/hidden');
$check(str_contains($pageJs,'const id = `page-child-visibility-${topIndex}-${columnIndex}-${childIndex}`'),'Page toggle ids follow current block positions');
$check(str_contains($pageJs,'label.htmlFor = id'),'Page toggle labels update after drag/clone');
$check(str_contains($formsSource,"formsStatus.value = formsVisibility.checked ? 'active' : 'inactive'"),'Forms maps Core toggle to existing active/inactive');
$check(str_contains($formsSource,"root.addEventListener('submit', syncFormsVisibility)"),'Form POST syncs latest toggle state');
$scss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$css=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$check($scss===$css,'Core admin SCSS and CSS byte-identical');
$check(str_contains($css,'.forms-field__editor-stack { display:grid; grid-template-columns:minmax(0,1fr);'), 'Core forces one field per line at all screen widths');
$check(str_contains($css,'.admin-toggle__input:checked'),'Existing Core Toggle appearance reused');
$check(!str_contains((string)file_get_contents($root.'/public_html/assets/css/style.css'),'forms-field__editor-stack'),'No admin rules leak to Theme');
$check(version_compare((string) (preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $m) ? $m[1] : '0'), '2.4.4.86', '>='),'Candidate internal version 2.4.4.86');
echo "Forms Vertical Visibility V2.4.4z20i: {$pass} PASS / {$fail} FAIL\n";
exit($fail?1:0);
