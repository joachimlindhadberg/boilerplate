<?php
// Browser fixture made from the real Forms Builder PHP view.
declare(strict_types=1);
function t(string $key): string { return str_starts_with($key,'forms:') ? substr($key,6) : $key; }
function admin_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'test-token'; }
require_once dirname(__DIR__) . '/core/classes/Autoload.php';
function admin_icon(string $name, string $class=''): string {return AdminIcons::render($name, $class);}
function admin_icon_action_button(string $name, string $label, array $attributes=[], string $tone='neutral'): string {return AdminIcons::actionButton($name,$label,$attributes,$tone);}
$fieldTypes=['text'=>'Tekst','email'=>'E-mail','textarea'=>'Tekstområde','select'=>'Dropdown','radio'=>'Radio','checkbox'=>'Checkbox','tel'=>'Telefon'];
$formData=['name'=>'Test Form','status'=>'active','submit_label'=>'Send','success_message'=>'Tak','success_action'=>'message'];
$fields=[['row_order'=>0,'column_index'=>0,'row_layout'=>'50-50','label'=>'Fornavn','field_type'=>'text','field_key'=>'fornavn','is_required'=>1]];
$builderRows=[['row_order'=>0,'layout'=>'50-50'],['row_order'=>1,'layout'=>'100']];
$errors=[];$publishedPages=[];$notificationEmail='';$isEdit=true;$formId=7;$deleteDependencies=['submissions'=>0,'pages'=>0];$formTemplates=[];
ob_start();require dirname(__DIR__) . '/modules/forms/admin/views/partials/form-editor.php';$view=(string)ob_get_clean();
$css=file_get_contents(dirname(__DIR__) . '/public_html/admin/assets/css/admin.css');
// Preload a few needed window methods in the fixture; actual markup and JS are emitted above.
$html='<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'.$css.'</style><script>window._allowConfirm=true;window._confirmCount=0;window.adminConfirm=async()=>{window._confirmCount++;return window._allowConfirm;};window.cmsTranslate=s=>s;</script></head><body class="nf-admin"><main class="admin-content" style="max-width:1260px;margin:20px auto;padding:0 12px">'.$view.'</main></body></html>';
file_put_contents(dirname(__DIR__) . '/tests/fixtures/forms-svg-actions-browser.html',$html);
echo 'Fixture generated from live Forms Builder PHP view\n';
