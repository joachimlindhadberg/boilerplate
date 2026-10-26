<?php
/** Isolated new-form editor error response (no users or database required). */
function t(string $key):string {return str_starts_with($key,'forms:')?substr($key,6):$key;}
function admin_e(mixed $value):string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_csrf_token():string {return 'csrf-test';}
require_once dirname(__DIR__).'/core/classes/Autoload.php';
function admin_icon(string $name,string $class=''):string{return AdminIcons::render($name,$class);}
function admin_icon_action_button(string $name,string $label,array $attrs=[],string $tone='neutral'):string{return AdminIcons::actionButton($name,$label,$attrs,$tone);}
$root=dirname(__DIR__);$formId=0;$isEdit=false;
$formData=['name'=>'','status'=>'active','submit_label'=>'Send','save_submissions'=>1,'success_action'=>'redirect','success_redirect_page_id'=>4,'send_confirmation'=>1,'confirmation_email_field_key'=>'', 'confirmation_subject'=>''];
$errors=['confirmation_subject'=>'Angiv emne'];$fields=[];$builderRows=[];$fieldTypes=['text'=>'Tekst','email'=>'E-mail'];$formTemplates=[];$publishedPages=[['id'=>4,'title'=>'Tak','url'=>'/tak']];$notificationEmail='';$deleteDependencies=['submissions'=>0,'pages'=>0];
ob_start();require $root.'/modules/forms/admin/views/partials/form-editor.php';$html=(string)ob_get_clean();$form=explode('</form>',$html,2)[0];
$checks=[
 'New form keeps original POST endpoint'=>str_contains($form,'action="/admin/forms?screen=new"'),
 'No invented form ID when creating'=>!str_contains($form,'name="id"'),
 'No submissions tab in editor after global nav split'=>!str_contains($form,'admin-editor-tabs__link'),
 'Email server error opens Emails panel'=>str_contains($form,'data-admin-editor-panel="emails" role="tabpanel" aria-labelledby="forms-tab-emails" >'),
 'Error panel tab has selected ARIA'=>str_contains($form,'aria-selected="true" tabindex="0" data-admin-editor-tab="emails"'),
 'Fields hidden when errors in Emails'=>str_contains($form,'data-admin-editor-panel="fields" role="tabpanel" aria-labelledby="forms-tab-fields" hidden'),
 'Previous conditional email setting kept'=>str_contains($form,'name="send_confirmation" value="1"')&&str_contains($form,'Angiv emne'),
 'Previous redirect setting and selected Page retained'=>str_contains($form,'name="success_action"')&&str_contains($form,'value="4" selected'),
 'Only one Save button in new form'=>substr_count($form,'type="submit"')===1,
 'No delete UI for unsaved form'=>!str_contains($html,'forms-delete-dialog'),
 'No SQL/migration introduced in view'=>!str_contains($html,'ALTER TABLE') && !str_contains($html,'DROP TABLE'),
];
$fails=0;
foreach($checks as $name=>$ok){echo ($ok?'PASS: ':'FAIL: ').$name."\n";if(!$ok)$fails++;}
echo sprintf('Forms New Editor V2.4.4z20b: %d PASS / %d FAIL',count($checks)-$fails,$fails)."\n";
exit($fails?1:0);
