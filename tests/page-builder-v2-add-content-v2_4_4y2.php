<?php
/** V2.4.4y2: sections/layout chooser, three-column V2 rows and legacy protection. */
require_once dirname(__DIR__) . '/core/classes/PageBlocks.php';
require_once dirname(__DIR__) . '/public_html/incl/helpers/page_builder_renderer.php';
$root = dirname(__DIR__);
$passes = 0;
function check_y2(bool $ok, string $description): void {
    global $passes;
    if (!$ok) { fwrite(STDERR, "FAIL: {$description}\n"); exit(1); }
    ++$passes;
    echo "PASS: {$description}\n";
}
function render_y2(string $type, array $data): string {
    ob_start();
    render_page_builder_block(['block_type'=>$type, 'data'=>$data, 'status'=>'active']);
    return ob_get_clean();
}
$view = file_get_contents($root . '/public_html/admin/views/pages/partials/form-fields.php');
$editor = file_get_contents($root . '/public_html/admin/incl/page-builder-functions.php');
$js = file_get_contents($root . '/public_html/admin/assets/js/admin.js');
check_y2(str_contains($view,'data-page-add-section') && str_contains($view,'page_builder_v2.sections_group'), 'Add modal includes standalone sections');
check_y2(str_contains($view,'data-page-add-layout="33"') && str_contains($view,'page_builder_v2.layouts_group'), 'Add modal includes 33/33/33 in layout group');
check_y2(str_contains($view,'data-page-add-layout="100"') && str_contains($view,'data-page-add-layout="50"'), 'Existing 100% and 50/50 workflows preserved');
check_y2(str_contains($view,'data-builder-template="grid-3"'), 'Three-column template is available without mutating the existing Grid template');
check_y2(str_contains($editor,'data-page-row-toggle') && str_contains($editor,'data-grid-child-add'), 'Row and column editors still rendered');
check_y2(str_contains($js,"'33': 'grid-3'") && str_contains($js,'data-page-add-section'), 'JS wires section and three-column creation');
check_y2(str_contains($js,'data-page-block-choices') && str_contains($view,'data-page-block-dialog'), 'Nested chooser preserved');
foreach (['hero','intro','text','text_image','cta','component'] as $type) {
    check_y2(isset(PageBlocks::definitions()[$type]), "Standalone section type {$type} supported");
}
$input = ['type'=>'grid', 'status'=>'active','data'=>['section_id'=>'section-556677','layout_version'=>'v2','column_count'=>3,'preset'=>'equal','gap'=>'large','columns'=>[
 ['blocks'=>[['type'=>'text','status'=>'active','data'=>['title'=>'Venstre']]]],
 ['blocks'=>[]],
 ['blocks'=>[['type'=>'cta','status'=>'active','data'=>['title'=>'Højre','button_label'=>'Læs mere','button_url'=>'/kontakt']]]],
]]];
$n = PageBlocks::normalizeSubmitted([$input]);
check_y2($n['errors'] === [] && count($n['blocks']) === 1, 'Three-column row normalizes without errors');
$data = $n['blocks'][0]['data'];
check_y2(($data['layout_version']??'') === 'v2' && ($data['column_count']??0) === 3, 'Three-column V2 marker and layout persisted');
check_y2(count($data['columns']) === 3 && $data['columns'][1]['blocks'] === [], 'Empty middle column preserved');
check_y2(($data['columns'][0]['blocks'][0]['data']['title']??'') === 'Venstre', 'First column content preserved');
check_y2(($data['columns'][2]['blocks'][0]['data']['title']??'') === 'Højre', 'Third column content preserved');
$html = render_y2('grid',$data);
check_y2(str_contains($html,'<section id="section-556677" class="block-section"'), 'Persistent V2 section anchor rendered');
check_y2(str_contains($html,'site-grid site-grid--3') && substr_count($html,'<div class="site-grid--item">') === 3, 'Exactly three semantic V2 wrappers');
check_y2(substr_count($html,'<section') === 1 && str_contains($html,'data-gap="large"'), 'One section and Theme-owned gap configuration');
check_y2(strpos($html,'Venstre') < strpos($html,'Højre'), 'Column content order correct');
$roundtrip = PageBlocks::normalizeSubmitted($n['blocks']);
check_y2($roundtrip['errors'] === [] && $roundtrip['blocks'] === $n['blocks'], 'Three-column data round-trips exactly');
$empty=PageBlocks::normalizeSubmitted([['type'=>'grid','status'=>'active','data'=>['layout_version'=>'v2','column_count'=>3,'preset'=>'equal','columns'=>[]]]]);
check_y2($empty['errors'] === [] && count($empty['blocks'][0]['data']['columns']) === 3, 'Three empty columns survive normalization');
check_y2(substr_count(render_y2('grid',$empty['blocks'][0]['data']),'<div class="site-grid--item">') === 3, 'Three empty columns render');
$legacy = PageBlocks::normalizeSubmitted([['type'=>'grid','status'=>'active','data'=>['column_count'=>3,'preset'=>'equal','section_id'=>'section-123456','columns'=>[]]]]);
check_y2(!isset($legacy['blocks'][0]['data']['layout_version']), 'Legacy 3-column grid is never auto-converted');
check_y2(str_contains(render_y2('grid',$legacy['blocks'][0]['data']),'grid--cols-3'), 'Legacy 3-column frontend output unchanged');
$unequal = PageBlocks::normalizeSubmitted([['type'=>'grid','status'=>'active','data'=>['column_count'=>3,'preset'=>'25-50-25','columns'=>[]]]]);
check_y2(!isset($unequal['blocks'][0]['data']['layout_version']), 'Asymmetric three-column legacy layout unaffected');
$previous = PageBlocks::normalizeSubmitted([['type'=>'grid','status'=>'active','data'=>['layout_version'=>'v2','column_count'=>2,'preset'=>'equal','columns'=>[]]]]);
check_y2(($previous['blocks'][0]['data']['layout_version']??'') === 'v2' && count($previous['blocks'][0]['data']['columns']) === 2, 'Existing V2 50/50 remains valid');
foreach (['da','en','de','sv','nb'] as $locale) {
    $translations=json_decode(file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
    foreach (['add_dialog_title','add_dialog_help','sections_group','layouts_group','section_heading','third_width','third_width_help'] as $key) {
        check_y2(isset($translations['page_builder_v2.'.$key][$locale]) && trim($translations['page_builder_v2.'.$key][$locale]) !== '', "{$key} is translated: {$locale}");
    }
}
$adminScss=file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$adminCss=file_get_contents($root.'/public_html/admin/assets/css/admin.css');
check_y2($adminScss === $adminCss, 'Core-admin SCSS and CSS remain byte-equivalent');
$themeScss=file_get_contents($root.'/public_html/assets/scss/layout/_layout.site-grid.scss');
$themeCss=file_get_contents($root.'/public_html/assets/css/style.css');
check_y2(str_contains($themeCss,$themeScss), 'Theme SCSS is reflected verbatim in compiled CSS');
check_y2(str_contains($themeScss,'.site-grid--3') && str_contains($themeCss,'.site-grid--3'), 'Three-column layout is Theme-owned');
check_y2(str_contains($adminScss,'admin-grid-editor[data-grid-count="3"]'), 'Admin has three-column V2 layout');
check_y2(str_contains($adminScss,'.admin-grid-column__add select.sr-only'), 'Previously approved overflow fix retained');
$versionMatches = [];
preg_match("~BOILERPLATE_VERSION', '([0-9.]+)'~", file_get_contents($root.'/public_html/incl/init.php'), $versionMatches);
check_y2(isset($versionMatches[1]) && version_compare($versionMatches[1], "2.4.4.36", ">="), 'Current release is at least V2.4.4y2');
echo "V2.4.4y2 Page Builder: {$passes} PASS / 0 FAIL\n";
