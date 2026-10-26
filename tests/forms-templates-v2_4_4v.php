<?php

declare(strict_types=1);
/** Non-DB contract & normalization regression for the Forms template catalog. */
interface ModuleInterface {}
interface ModuleComponentProviderInterface {}
interface ModuleFrontendActionInterface {}
class Settings { public function get(string $key, mixed $default=null): mixed { return $default; } }
function t(string $key): string { return str_starts_with($key,'forms:') ? substr($key,6) : $key; }
function admin_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'fixture'; }
require_once dirname(__DIR__) . '/core/classes/Autoload.php';
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_icon_action_button(string $name, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($name, $label, $attributes, $tone); }
require dirname(__DIR__) . '/modules/forms/FormsModule.php';
$root = dirname(__DIR__);
$failures = 0;
function check(bool $yes, string $name): void {
  global $failures;
  echo ($yes ? 'PASS' : 'FAIL') . ': ' . $name . "\n";
  if (!$yes) $failures++;
}
$fieldTypes = ['text'=>'Tekst','email'=>'E-mail','tel'=>'Telefon','textarea'=>'Tekstområde','select'=>'Dropdown','radio'=>'Radio-knapper','checkbox'=>'Checkbox'];
$templates = FormsTemplateCatalog::all(array_keys($fieldTypes));
check(count($templates) === 4, 'Four built-in templates load from module JSON');
check(array_column($templates,'id') === ['contact','registration','feedback','inquiry'], 'Stable template ids and ordering');
$contact = $templates[0];
check($contact['rows'][0]['layout'] === '50-50' && count($contact['rows'][0]['fields']) === 2, 'Contact has 50/50 first/last name');
check($contact['rows'][0]['fields'][0]['label'] === 'Fornavn' && $contact['rows'][0]['fields'][1]['label'] === 'Efternavn', 'Contact column order');
check(count($contact['rows']) === 3 && $contact['rows'][1]['layout'] === '100' && $contact['rows'][2]['layout'] === '100', 'Contact email and message full width');
check($templates[2]['rows'][1]['fields'][0]['field_type'] === 'radio' && count($templates[2]['rows'][1]['fields'][0]['options']) === 3, 'Feedback options use existing radio field model');
check($templates[3]['rows'][2]['fields'][0]['field_type'] === 'select' && count($templates[3]['rows'][2]['fields'][0]['options']) === 3, 'Inquiry select has valid options');
$module = (new ReflectionClass(FormsModule::class))->newInstanceWithoutConstructor();
$normalize = new ReflectionMethod(FormsModule::class, 'normalizeAdminSubmission');
foreach ($templates as $template) {
  $postRows=[];
  foreach ($template['rows'] as $rowIndex=>$row) {
    $cols=[];
    foreach ($row['fields'] as $column=>$field) {
      $cols[$column] = $field + ['field_key'=>'', 'options'=>[]];
      $cols[$column]['options'] = implode("\n", $field['options']);
      if (!$field['is_required']) unset($cols[$column]['is_required']);
    }
    $postRows[]=['layout'=>$row['layout'], 'fields'=>$cols];
  }
  $source=['name'=>'Sample', 'status'=>'active', 'submit_label'=>'Send', 'success_message'=>'Tak', 'success_action'=>'message', 'rows'=>$postRows];
  [$form,$fields,$errors,$rowLayouts] = $normalize->invoke($module,$source);
  check(!$errors && count($rowLayouts) === count($postRows), "Server accepts all template rows: {$template['id']}");
  check(count($fields) === count(array_unique(array_column($fields,'field_key'))), "Server creates unique template keys: {$template['id']}");
  check(count($fields) === array_sum(array_map(fn($row)=>count($row['fields']),$postRows)), "Server preserves all template fields: {$template['id']}");
}
$formTemplates = $templates;
$formData=['name'=>'Sample','submit_label'=>'Send','status'=>'active'];
$fields=[]; $builderRows=[]; $errors=[]; $publishedPages=[]; $notificationEmail=''; $isEdit=false; $formId=0; $deleteDependencies=['submissions'=>0,'pages'=>0];
ob_start(); require $root . '/modules/forms/admin/views/partials/form-editor.php'; $html=(string)ob_get_clean();
check(substr_count($html, 'type="radio" name="forms_template_choice"') === 4, 'View renders four templates');
check(str_contains($html, 'data-forms-apply-template') && str_contains($html, 'data-forms-templates-dialog'), 'Functional template modal and action rendered');
check(str_contains($html, 'adminConfirm(uiText.replaceMessage') && str_contains($html, 'rows.replaceChildren(...replacement)'), 'Confirmation precedes replacement');
check(str_contains((string)file_get_contents($root . '/modules/forms/admin/views/partials/form-editor.php'), 'JSON_HEX_TAG | JSON_HEX_AMP'), 'Safe JSON serialization for inline template data is configured');
check(!str_contains($html, 'Skabeloner kan indsættes fra næste version.'), 'Old coming-soon placeholder removed');
$trans=json_decode((string)file_get_contents($root.'/modules/forms/lang/translations.json'),true);
$raw=json_decode((string)file_get_contents($root.'/modules/forms/templates/standard.json'),true);
$required=['Vælg en skabelon. Alle felter og rækker kan redigeres bagefter.','Brug skabelon','Erstat formularens felter?', 'Erstat felter'];
foreach ($raw as $template) {
  $required[]=$template['title']; $required[]=$template['description'];
  foreach ($template['rows'] as $row) foreach ($row['fields'] as $field) {
    $required[]=$field['label']; foreach ($field['options']??[] as $option) $required[]=$option;
  }
}
$all=true;
foreach (array_unique($required) as $key) foreach (['da','en','de','sv','nb'] as $locale) {
 if (!isset($trans[$key][$locale]) || trim((string)$trans[$key][$locale])==='') $all=false;
}
check($all, 'Template content and UI translations complete for five locales');
check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Admin SCSS and compiled CSS synchronized');
check(preg_match("~BOILERPLATE_VERSION', '([0-9.]+)'~", (string)file_get_contents($root.'/public_html/incl/init.php'), $cmsVersionMatch) === 1 && version_compare($cmsVersionMatch[1], '2.4.4.31', '>='), 'Correct release version');
check(json_decode((string)file_get_contents($root.'/modules/forms/module.json'),true)['version']==='1.3.12', 'Correct Forms module version');
echo 'Forms Templates V2.4.4v: ' . ($failures ? "$failures FAIL" : 'ALL TESTS PASSED') . "\n";
exit($failures?1:0);
