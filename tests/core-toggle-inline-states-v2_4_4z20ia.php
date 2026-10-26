<?php
/** V2.4.4z20ia – Core-owned inline state labels, validation toggles and spacing. */
declare(strict_types=1);
$root = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
if (!function_exists('t')) {
    function t(string $key): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
}
require_once $root . '/public_html/admin/incl/page-builder-functions.php';
$p=0;$f=0;
$check = static function(bool $test, string $label) use (&$p,&$f):void {
    $test ? ++$p : ++$f;
    echo ($test ? 'PASS: ' : 'FAIL: ') . $label . "\n";
};
$states=['on'=>'Vises','off'=>'Skjult'];
foreach ([true,false] as $on) {
    $html=AdminToggle::render('visible-test','Synlighed',$on,'',[], $states);
    $label=$on?'Vises':'Skjult';
    $check(str_contains($html,'data-admin-toggle-state'),$label.' core state element');
    $check(str_contains($html,'data-state-on="Vises" data-state-off="Skjult"'),$label.' on/off values');
    $check(str_contains($html,'aria-live="polite"'),$label.' politely announced state');
    $check((bool)preg_match('/data-admin-toggle-state[^>]+>'.$label.'<\/span><span class="admin-toggle__control">/', $html),$label.' is immediately before the switch');
    $check(str_contains($html, 'role="switch"'.($on?' checked':'')),$label.' native checked state');
}
$check(str_contains(AdminToggle::render('plain','Plain'),'admin-toggle__right') && !str_contains(AdminToggle::render('plain','Plain'),'data-admin-toggle-state'),'Legacy Core switch remains usable without state text');
$escaping=AdminToggle::render('safe','Safe',true,'',[],['on'=>'<script>alert(1)</script>','off'=>'" & <']);
$check(str_contains($escaping,'&lt;script&gt;')&&!str_contains($escaping,'<script>'),'On status safely escaped');
$check(str_contains($escaping,'&quot; &amp; &lt;'),'Off status safely escaped');
foreach ([['on'=>'yes'], ['off'=>'no'], ['on'=>'','off'=>'no'], ['on'=>42,'off'=>'no']] as $invalid) {
    try { AdminToggle::render('safe','Safe',true,'',[], $invalid); $threw=false; }
    catch (InvalidArgumentException) { $threw=true; }
    $check($threw,'Reject invalid state config');
}
$formTypes=['text'=>'Tekst','email'=>'E-mail','tel'=>'Telefon','textarea'=>'Tekstområde','select'=>'Dropdown','radio'=>'Radio','checkbox'=>'Checkbox'];
// Include the real Forms partial in its normal rendering context to define the shared field renderer.
$fieldTypes=$formTypes; $formData=['status'=>'active']; $fields=[]; $builderRows=[]; $formTemplates=[]; $errors=[]; $publishedPages=[]; $notificationEmail=''; $isEdit=false; $formId=0;
ob_start(); require $root . '/modules/forms/admin/views/partials/form-editor.php'; ob_end_clean();
foreach ([1,0] as $required) {
    $html=forms_admin_field_card(['field_type'=>'email','label'=>'E-mail','is_required'=>$required],'5','1',$formTypes);
    $check(str_contains($html,'data-forms-required="1"'),'Forms required uses Core checkbox hook');
    $check(str_contains($html,'role="switch"'),'Forms required is a native Core Toggle');
    $check(str_contains($html,'name="rows[5][fields][1][is_required]" value="1"'),'Forms original required POST name and value');
    $check(str_contains($html,'data-state-on="Påkrævet" data-state-off="Valgfrit"'),'Forms validation uses localized required/optional labels');
    $check(str_contains($html,$required ? '>Påkrævet</span>' : '>Valgfrit</span>'),'Forms required state text matches storage');
    $check(!str_contains($html,'<label class="admin-checkbox">'),'Forms required no longer uses old checkbox wrapper');
    $check(str_contains($html,'forms-required-')&&str_contains($html,'for="forms-required-'),'Forms required Core label accessible');
}
foreach (['active','hidden'] as $status) {
    $html=admin_page_builder_grid_child_editor(['type'=>'text','status'=>$status,'data'=>[]], 'blocks[0][data][columns][0][blocks][0]',true);
    $check(str_contains($html,'data-page-child-visibility="1"'),'Page block Core hook');
    $check(str_contains($html,'data-state-on="Vises" data-state-off="Skjult"'),'Page block state labels');
    $check(str_contains($html,$status==='active' ? '>Vises</span>' : '>Skjult</span>'),'Page block correct initial state');
    $check(str_contains($html,'name="blocks[0][data][columns][0][blocks][0][status]" value="'.$status.'"'),'Page block original status payload');
}
$page=(string)file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$forms=(string)file_get_contents($root.'/modules/forms/admin/views/partials/form-editor.php');
$js=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$css=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$scss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$check(str_contains($page,"['on'=>t('Vises'), 'off'=>t('Skjult')]"),'Row settings renders inline statuses');
$check(!str_contains($page,'data-page-section-visibility-state'),'Old below-card row label removed');
$check(str_contains($forms,"['on'=>t('Vises'), 'off'=>t('Skjult')]"),'Forms editor uses inline visibility statuses');
$check(!str_contains($forms,'data-forms-visibility-state'),'Old below-card Forms status removed');
$check(str_contains($js,'data-admin-toggle-state') && str_contains($js,"document.addEventListener('change'"),'Delegated Core updates dynamically inserted toggles');
$check(str_contains($forms,'required.id = id') && str_contains($forms,'label.htmlFor = id'),'Forms reindex updates unique ID and label');
$check(str_contains($js,'sectionVisibilityState.dataset.stateOn'),'Row modal synchronizes Core state');
$check(str_contains($forms,'formsVisibilityState.dataset.stateOn'),'Forms editor synchronizes Core state');
$check(str_contains($css,'.admin-toggle__right { display:inline-flex; align-items:center;') && str_contains($css,'text-align:center; white-space:nowrap'),'State sits centered in front of switch');
$check(str_contains($css,'.admin-grid-child__body > .admin-toggle { margin-bottom:18px; }'),'Spacing before Page block input label');
$check($css===$scss,'Compiled CSS and SCSS byte-identical');
$tr=json_decode((string)file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
foreach(['da','en','de','sv','nb'] as $lang) $check(trim((string)($tr['Vises'][$lang]??''))!=='' && trim((string)($tr['Skjult'][$lang]??''))!=='','Vises/Skjult localized: '.$lang);
$check((bool)preg_match("/BOILERPLATE_VERSION\', \'([0-9.]+)\'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $versionMatch) && version_compare($versionMatch[1], '2.4.4.87', '>='), 'z20ia or later compatible version');
echo "Core Toggle Inline States z20ia: $p PASS / $f FAIL\n";
exit($f?1:0);
