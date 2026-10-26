<?php
/** V2.4.4z20b – Full editor DOM contract: main workflow and unchanged submission fields. */
function t(string $key): string { return str_starts_with($key,'forms:') ? substr($key,6) : $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'); }
function admin_csrf_token(): string {return 'csrf-abc';}
require_once dirname(__DIR__) . '/core/classes/Autoload.php';
function admin_icon(string $name, string $class=''): string { return AdminIcons::render($name, $class); }
function admin_icon_action_button(string $name,string $label,array $attributes=[],string $tone='neutral'):string{return AdminIcons::actionButton($name,$label,$attributes,$tone);}
$root=dirname(__DIR__);$fails=0;$passes=0;
function check(bool $ok,string $name):void {global $fails,$passes; $ok ? $passes++ : $fails++; echo ($ok?'PASS: ':'FAIL: ').$name."\n";}
$formId=17;$isEdit=true;
$formData=['name'=>'Besked <img src=x onerror=alert(1)>', 'status'=>'inactive','submit_label'=>'Kontakt os','save_submissions'=>1,'success_action'=>'message','success_message'=>'Tak','send_notification'=>1,'send_confirmation'=>1];
$fields=[];$builderRows=[['row_order'=>0,'layout'=>'100']];$fieldTypes=['text'=>'Tekst'];$formTemplates=[];$errors=[];$publishedPages=[];$notificationEmail='mail@example.com';$deleteDependencies=['submissions'=>0,'pages'=>0];
ob_start();require $root.'/modules/forms/admin/views/partials/form-editor.php';$html=(string)ob_get_clean();
$editorForm = explode('</form>', $html, 2)[0]; // Core delete dialog is a distinct second POST form.
// DOMDocument may be absent in minimal PHP runtimes: use strictly scoped markup checks.
$position=static fn(string $needle):int=>strpos($html,$needle)===false?PHP_INT_MAX:strpos($html,$needle);
check($position('data-admin-editor-tab="fields"')<$position('data-admin-editor-panel="fields"'),'Fields tab exists before Form Builder pane');
check($position('data-admin-editor-panel="fields"')<$position('data-admin-editor-panel="settings"') && $position('data-admin-editor-panel="settings"')<$position('data-admin-editor-panel="emails"'),'Fields, Settings, Emails panels are in that order');
check(str_contains($html,'id="forms-panel-fields"')&&str_contains($html,'role="tabpanel"'),'Accessible tab panels present');
check(str_contains($html,'aria-selected="true" tabindex="0" data-admin-editor-tab="fields"'),'Fields is default tab');
check(str_contains($html,'data-admin-editor-panel="settings" role="tabpanel" aria-labelledby="forms-tab-settings" hidden'),'Settings initially hidden');
check(str_contains($html,'data-admin-editor-panel="emails" role="tabpanel" aria-labelledby="forms-tab-emails" hidden'),'Emails initially hidden');
check($position('data-forms-rows')<$position('name="name"'),'Builder appears before settings controls');
foreach (['csrf_token','id','name','submit_label','status','save_submissions','success_action','success_message','send_notification','send_confirmation'] as $name) {
 check(substr_count($editorForm,'name="'.$name.'"')===1,"Exactly one POST field: $name");
}
check(str_contains($html, 'action="/admin/forms?screen=edit&amp;id=17"'),'Existing edit POST action remains');
check(!str_contains($html,'admin-editor-tabs__link') && !str_contains($html,'href="/admin/forms/submissions?form_id=17"'),'Submissions moved to independent global menu in z20d');
check(str_contains($html,'data-admin-track-changes'),'Unsaved changes guard preserved');
check(str_contains($html,'data-forms-open-templates')&&str_contains($html,'data-forms-add-element')&&str_contains($html,'data-forms-row-settings-dialog'),'Original Builder dialogs/hook retained');
check(str_contains($html,'data-admin-open-delete="forms-delete-dialog"')&&str_contains($html,'id="forms-delete-dialog"'),'Existing Core delete modal retained');
check(str_contains($html,'Besked &lt;img src=x onerror=alert(1)&gt;')&&!str_contains($html,'Besked <img src=x onerror=alert(1)>'),'Untrusted title is escaped');
check(!str_contains($html,'forms:Sikkerhed'),'Old standalone static security panel removed');
check(substr_count($editorForm,'type="submit"')===1 && $position('type="submit"')<$position('data-admin-editor-tabs'),'Exactly one Core primary Save in topbar');
check(str_contains($html,'invalidPanelRevealed')&&str_contains($html,'activateEditorTab(panel.dataset.adminEditorPanel)'),'Browser constraint-validation moves to the invalid field panel');
check(str_contains($html,'event.key === \'ArrowRight\'')&&str_contains($html,'event.key === \'Home\''),'Keyboard tab navigation in rendered JS');
check(str_contains($html,'data-forms-email-select')&&str_contains($html,'data-forms-notification-options'),'Existing email controls retained');
check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'),'Core styling SCSS/CSS byte-identical');
$translations=json_decode((string)file_get_contents($root.'/modules/forms/lang/translations.json'),true);
foreach (['Formulareditor','E-mails','Formularstatus','Gem formularen først for at se indsendelser.'] as $key) {
 check(count(array_filter(array_map(static fn($locale)=>trim((string)($translations[$key][$locale]??'')),['da','en','de','sv','nb'])))===5, "All five translations: $key");
}
$source=(string)file_get_contents($root.'/modules/forms/admin/views/partials/form-editor.php');
check(str_contains($source,"$".'initialEditorTab = \'settings\'')&&str_contains($source,"$".'initialEditorTab = \'emails\''),'Server errors can reopen Settings or Emails');
check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/",(string)file_get_contents($root.'/public_html/incl/init.php'),$v)===1 && version_compare($v[1],'2.4.4.79','>='),'z20b internal version >=2.4.4.79');
echo "Forms Editor Workspace V2.4.4z20b: $passes PASS / $fails FAIL\n";
exit($fails?1:0);
