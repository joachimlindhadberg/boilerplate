<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){ http_response_code(403);exit; }
$root=dirname(__DIR__);
require_once $root.'/core/classes/Autoload.php';
require_once $root.'/public_html/admin/incl/functions.php';
$pass=0;$fail=0;
$check=static function(bool $okay,string $label)use(&$pass,&$fail){ $okay?++$pass:++$fail;echo ($okay?'PASS':'FAIL').': '.$label."\n"; };
$view=(string)file_get_contents($root.'/modules/forms/admin/views/partials/form-editor.php');
$icons=(string)file_get_contents($root.'/core/classes/AdminIcons.php');
$css=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$js=$view;
$check(str_contains($icons,"'arrow-up'")&&str_contains($icons,"'arrow-down'"),'Core registry contains arrow up and down');
foreach(['arrow-up','arrow-down','chevron-down','delete','plus'] as $name){
 $check(str_contains(admin_icon($name),'viewBox="0 0 24 24"'),'Core SVG rendered: '.$name);
 $check(str_contains(admin_icon_action_button($name,'Handling'),'aria-label="Handling"'),'Core SVG action accessible: '.$name);
}
foreach(['data-forms-row-drag','data-forms-remove-row','data-forms-edit-field','data-forms-remove-field'] as $hook){
 $check(str_contains($view,$hook),'Forms behavior hook preserved: '.$hook);
}
$check(substr_count($view,'admin_icon_action_button(')===4,'Forms visible SVG actions are Core helpers');
$check(str_contains($view,"admin_icon_action_button('chevron-down'"),'Field expand is chevron-down');
$check(str_contains($view,"admin_icon_action_button('delete', t('forms:Fjern felt')") && str_contains($view,"admin_icon_action_button('delete', t('forms:Fjern række')"),'Destructive controls use Core danger');
$check(!str_contains($view,'toggle.textContent ='),'Expanding does not destroy SVG');
$check(str_contains($view,"toggle.setAttribute('aria-label'")&&str_contains($view,"toggle.setAttribute('title'"),'Expanded state changes accessible name');
$check(str_contains($css,'[data-forms-edit-field][aria-expanded="true"] .admin-icon-action__icon { transform:rotate(180deg); }'),'Chevron rotates on expanded state');
$check(str_contains($view,"admin_icon('plus', 'forms-column__add-icon')")&&str_contains($view,"admin_icon('plus', 'forms-builder__add-icon')"),'Both add buttons use Core SVG plus');
$check(!str_contains($view,'>+ <?=') && !str_contains($view,'>↑</button>'),'ASCII icons removed from Forms Builder');
$check(str_contains($js,"root.addEventListener('invalid'") && str_contains($js,"root.addEventListener('dragend'") && str_contains($js,"window.adminConfirm"),'Validation, drag/drop and confirmation retained');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss')===$css,'Admin SCSS and CSS match');
$catalog=json_decode((string)file_get_contents($root.'/modules/forms/lang/translations.json'),true);
foreach(['da','en','de','sv','nb'] as $locale){$check(!empty($catalog['Åbn redigering'][$locale]),'Expand label translated: '.$locale);}
$check(version_compare((string)(preg_match("/BOILERPLATE_VERSION', '([^']+)'/",(string)file_get_contents($root.'/public_html/incl/init.php'),$m)?$m[1]:''),'2.4.4.54','>='),'Version >=2.4.4.54');
$check(str_contains($view,'data-forms-row-template="50-50"')&&str_contains($view,'data-forms-field-template'),'Dynamic row/field templates preserved');
$check(str_contains($view,'data-forms-templates-dialog'),'Forms template chooser preserved');
$check(str_contains($view, "'id' => 'forms-delete-dialog'") && str_contains($view, 'AdminDeleteDialog::render('),'Form-level delete confirmation preserved via Core');
echo sprintf('Forms SVG actions V2.4.4z5: %d PASS / %d FAIL'."\n",$pass,$fail);
exit($fail?1:0);
