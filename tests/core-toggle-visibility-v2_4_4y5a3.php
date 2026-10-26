<?php
/** Regression: Core toggle + Page Builder visibility mapping. */
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
if (!function_exists('t')) { function t(string $key): string { return $key; } }
require_once $root . '/public_html/admin/incl/page-builder-functions.php';
$pass = 0; $fail = 0;
$check = static function (bool $yes, string $name) use (&$pass, &$fail): void { ($yes ? $pass++ : $fail++); echo ($yes ? 'PASS ' : 'FAIL ') . $name . "\n"; };
$throws = static function (callable $fn): bool { try { $fn(); } catch (InvalidArgumentException) { return true; } return false; };
$control = admin_toggle_switch('example-visibility','Synlighed',true,'Synlig på hjemmesiden',['data-test-toggle'=>'yes']);
$check(str_contains($control, 'class="admin-toggle"') && str_contains($control,'class="admin-toggle__track"'), 'Core renders shared markup');
$check(str_contains($control,'role="switch"') && str_contains($control,'type="checkbox"'), 'Native accessible switch');
$check(str_contains($control,' checked'), 'Initial enabled state');
$check(str_contains($control,'for="example-visibility"') && str_contains($control,'id="example-visibility"'), 'Label associated with control');
$check(str_contains($control,'data-test-toggle="yes"'), 'Safe data attributes supported');
$check(!str_contains(admin_toggle_switch('unselected','No',false), ' checked'), 'Disabled state does not accidentally render checked');
$check(str_contains(admin_toggle_switch('x','Test',false,'',['disabled'=>true]),' disabled'), 'Disabled support');
$check(str_contains(admin_toggle_switch('x','Test',false,'',['disabled'=>false]),' disabled') === false, 'False disabled omitted');
$check(str_contains(admin_toggle_switch('x','<img src=x onerror=alert(1)>',true,'"bad"'), '&lt;img'), 'Labels escaped');
$check(!str_contains(admin_toggle_switch('x','<script>'),'<script>'), 'Untrusted markup escaped');
$check($throws(static fn()=>admin_toggle_switch('bad id','Label')), 'Malformed id rejected');
$check($throws(static fn()=>admin_toggle_switch('valid','')), 'Empty label rejected');
foreach (['onclick','style','type','role','checked','aria-label','aria-checked','aria-describedby','aria-labelledby','id','class'] as $attr) {
  $check($throws(static fn()=>admin_toggle_switch('safe','Label',false,'',[$attr=>'bad'])), 'Disallowed attribute rejected: '.$attr);
}
$check($throws(static fn()=>admin_toggle_switch('safe','Label',false,'',['data-check'=>['bad']])), 'Nested attributes rejected');
$view = file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$js = file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$css = file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$scss = file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$check(str_contains($view,"admin_toggle_switch('page-section-visible'") && str_contains($view,'data-page-section-visibility'), 'Pages uses Core control');
$check(strpos($view,'data-page-section-visibility') < strpos($view,'data-page-row-layout-field'), 'Visibility first in modal');
$check(!str_contains($view,'data-page-section-status'), 'Old select removed from settings dialog');
$check(str_contains($js,"sectionVisibility.checked = statusInput.value !== 'hidden';"), 'Modal restores previously saved status');
$check(str_contains($js,"const nextStatus = sectionVisibility.checked ? 'active' : 'hidden';"), 'Switch maps to existing storage values');
$check(str_contains($js,'if (!settingsSection) return;'), 'Save guarded by active section');
$check(str_contains($js,'closeDialog(sectionDialog);'), 'Modal is closed on apply');
$check(str_contains($js,'syncSectionVisibility();'), 'Visible status text synchronized');
$check(str_contains($css, 'background:var(--admin-accent)') && str_contains($css,'.admin-toggle__input:checked'), 'On state uses shared Core accent');
$check(str_contains($css, '.admin-toggle__input:focus-visible'), 'Keyboard focus styling');
$check($scss === $css, 'Admin SCSS CSS synchronized');
$check(!str_contains(file_get_contents($root.'/public_html/assets/css/style.css'),'admin-toggle__input'), 'Toggle styling never added to Theme');
foreach (['active','hidden'] as $status) {
  $result=PageBlocks::normalizeSubmitted([['type'=>'intro','status'=>$status,'data'=>[]]]);
  $check(($result['blocks'][0]['status']??null)===$status,'Existing '.$status.' storage round trip');
  $editor=admin_page_builder_block_editor(['type'=>'intro','status'=>$status,'data'=>[]],0);
  $check(str_contains($editor, 'data-builder-section-status'), 'Section hidden field persists '.$status);
}
$trans=json_decode(file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
foreach(['da','en','de','sv','nb'] as $lang) $check(trim((string)($trans['page_builder_v2.visibility_help'][$lang]??''))!=='', 'Visibility help '.$lang.' translated');
$versionSource=(string)file_get_contents($root.'/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", $versionSource, $versionMatch)===1 && version_compare($versionMatch[1], '2.4.4.46', '>='), 'Release version retains Core Toggle compatibility');
echo "SUMMARY: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
