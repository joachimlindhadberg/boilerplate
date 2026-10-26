<?php
/** V2.4.4x: isolated Page Builder V2 UI + data compatibility regression. */
require_once dirname(__DIR__) . '/core/classes/PageBlocks.php';
function verify(bool $value, string $label): void {
    if (!$value) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
$root = dirname(__DIR__);
$view = file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$rendererBefore = __DIR__ . '/../public_html/incl/helpers/page_builder_renderer.php';
$builder = file_get_contents($root.'/public_html/admin/incl/page-builder-functions.php');
$js = file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$scss = file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$css = file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$translations = json_decode(file_get_contents($root.'/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
verify(str_contains($view, 'data-page-layout-dialog') && str_contains($view, 'data-page-block-dialog'), 'Layout and block modals are in Pages view');
verify(str_contains($view, 'data-page-templates-dialog'), 'Templates button opens a non-destructive placeholder');
verify(str_contains($view, 'data-builder-add'), 'Always-bottom add box in Pages view');
verify(str_contains($view, 'page_builder_v2.subtitle'), 'Approved Page Builder heading and subtitle');
verify(str_contains($builder, 'data-page-row-toggle') && str_contains($builder, 'data-page-child-toggle'), 'Independent editor toggles render');
verify(str_contains($builder, '[data][section_id]') && str_contains($builder, 'data-builder-section-id'), 'Section IDs remain in posted form');
verify(str_contains($js, 'data-page-add-layout') && str_contains($js, 'data-page-block-choice'), 'JavaScript connects layout and block modals');
verify(str_contains($js, 'data-page-section-settings'), 'New row settings remain accessible');
verify(str_contains($scss, 'admin-page-add-box') && str_contains($css, 'admin-page-add-box'), 'Core admin SCSS/CSS both updated');
verify(str_contains($scss, '.admin-page-builder-panel [hidden]') && str_contains($css, '.admin-page-builder-panel [hidden]'), 'Hidden state uses correct CSS');
foreach ($translations as $key=>$locale) {
    if (!str_starts_with($key, 'page_builder_v2.')) continue;
    verify(count(array_filter(['da','en','de','sv','nb'], static fn($lang) => isset($locale[$lang]) && trim((string)$locale[$lang]) !== '')) === 5, 'Five translations for '.$key);
}
$v2 = ['type'=>'grid','status'=>'active','data'=>[
    'section_id'=>'section-123456',
    'layout_version'=>'v2','column_count'=>2,'preset'=>'equal','gap'=>'normal',
    'columns'=>[['blocks'=>[['type'=>'text','status'=>'active','data'=>['title'=>'Fornavn']]]],['blocks'=>[]]],
]];
$r = PageBlocks::normalizeSubmitted([$v2]);
verify($r['errors'] === [], 'V2 50/50 grid accepted by server');
verify(($r['blocks'][0]['data']['layout_version'] ?? null) === 'v2', 'V2 marker persists after save');
verify($r['blocks'][0]['data']['section_id'] === 'section-123456','Stable ID retained');
verify(count($r['blocks'][0]['data']['columns']) === 2,'Two columns remain after save');
verify(($r['blocks'][0]['data']['columns'][0]['blocks'][0]['data']['title'] ?? '') === 'Fornavn','Existing child data retained');
verify($r['blocks'][0]['data']['columns'][1]['blocks'] === [], 'Empty second column persists');
$empty = PageBlocks::normalizeSubmitted([['type'=>'grid','status'=>'active','data'=>['layout_version'=>'v2','column_count'=>2,'columns'=>[]]]]);
verify($empty['errors'] === [] && count($empty['blocks'][0]['data']['columns']) === 2, 'Empty 50/50 row round-trip works');
verify(count(array_filter($empty['blocks'][0]['data']['columns'],fn($c) => $c['blocks'] === []))===2, 'Both empty columns remain empty');
$legacy = PageBlocks::normalizeSubmitted([['type'=>'grid','status'=>'active','data'=>['column_count'=>4,'preset'=>'equal','columns'=>[]]]]);
verify($legacy['errors'] === [] && $legacy['blocks'][0]['data']['column_count'] === 4, 'Legacy 4-column grid unchanged');
verify(!isset($legacy['blocks'][0]['data']['layout_version']), 'Legacy grids not forcibly converted to V2');
$legacyUnequal = PageBlocks::normalizeSubmitted([['type'=>'grid','status'=>'active','data'=>['column_count'=>2,'preset'=>'33-67','columns'=>[]]]]);
verify($legacyUnequal['blocks'][0]['data']['preset'] === '33-67', 'Unequal legacy two-column layout preserved');
$roundtrip = PageBlocks::normalizeSubmitted($r['blocks']);
verify($roundtrip['errors'] === [] && $roundtrip['blocks'] === $r['blocks'], 'Saved rows byte-equivalent after normalization round-trip');
verify(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", file_get_contents($root.'/public_html/incl/init.php'), $version) === 1 && version_compare($version[1], '2.4.4.33', '>='), 'Version retains V2.4.4x features');
verify(is_file($rendererBefore) && str_contains(file_get_contents($rendererBefore), 'render_page_builder_block'), 'Original public renderer entry point is preserved');
echo "Page Builder V2.4.4x UI compatibility: ALL PASS\n";
