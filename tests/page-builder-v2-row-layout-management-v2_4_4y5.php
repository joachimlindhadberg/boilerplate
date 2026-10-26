<?php
/** V2.4.4y5: persistable 1–6 column Rows (z20j), safe layout changes, legacy compatibility. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
if (!function_exists('t')) { function t(string $key): string { return $key; } }
require_once $root . '/public_html/incl/helpers/page_builder_renderer.php';
$passes = 0;
$failures = 0;
$check = static function (bool $valid, string $name) use (&$passes, &$failures): void {
    $valid ? ++$passes : ++$failures;
    echo ($valid ? 'PASS: ' : 'FAIL: ') . $name . "\n";
};
$rows = [];
foreach ([1, 2, 3, 4, 5, 6] as $count) {
    $columns = array_fill(0, $count, ['blocks'=>[]]);
    $columns[0]['blocks'][] = ['type'=>'text','status'=>'active','data'=>['title'=>'Col One','body'=>'Details']];
    $input = ['type'=>'grid','status'=>'active','data'=>[
        'section_id'=>'section-' . str_pad((string)(240000 + $count), 6, '0', STR_PAD_LEFT),
        'layout_version'=>'v2','column_count'=>$count,'preset'=>'equal',
        'theme'=>'soft','container_width'=>'wide','columns'=>$columns,
    ]];
    $result = PageBlocks::normalizeSubmitted([$input]);
    $check($result['errors'] === [], "V2 row {$count} valid");
    $data = $result['blocks'][0]['data'];
    $check(($data['column_count'] ?? null) === $count && ($data['layout_version'] ?? '') === 'v2', "V2 row {$count} keeps explicit layout marker");
    $check(count($data['columns']) === $count, "V2 row {$count} keeps exact column count");
    $check($data['columns'][0]['blocks'][0]['data']['title'] === 'Col One', "V2 row {$count} retains real block content");
    $check(($data['theme']??'')==='soft' && ($data['container_width']??'')==='wide', "V2 row {$count} retains settings");
    $again = PageBlocks::normalizeSubmitted($result['blocks']);
    $check($again['errors'] === [] && $again['blocks'] === $result['blocks'], "V2 row {$count} exact round-trip");
    ob_start(); render_page_builder_block(['block_type'=>'grid','status'=>'active','data'=>$data]); $html = (string)ob_get_clean();
    $check(substr_count($html, '<section') === 1 && substr_count($html, 'site-grid--item') === $count, "V2 row {$count} public HTML one section and {$count} columns");
    $check(str_contains($html, 'site-grid site-grid--'.$count) && str_contains($html, 'Col One'), "V2 row {$count} correct child content renderer");
    $editor = admin_page_builder_block_editor($result['blocks'][0], '0');
    $check(substr_count($editor, 'data-grid-column-index=') === $count && str_contains($editor,'data-grid-child-add'), "V2 row {$count} visible column editors exist");
    $layoutLabels = [1 => '1 Kolonne', 2 => '2 ' . t('page_builder_v2.columns'), 3 => '3 ' . t('page_builder_v2.columns'), 4 => '4 ' . t('page_builder_v2.columns'), 5 => '5 ' . t('page_builder_v2.columns'), 6 => '6 ' . t('page_builder_v2.columns')];
    $check(str_contains($editor, 'data-page-layout-label>' . $layoutLabels[$count]), "V2 row {$count} uses expected layout label");
    $check(str_contains($editor,'data-page-child-toggle') && str_contains($editor,'data-grid-child-remove'), "V2 row {$count} has shared Edit/Delete SVG actions");
    $check(str_contains($editor,'data-grid-child__body') === false || str_contains($editor,'data-page-child-fields'), "V2 row {$count} has individual block editor");
    $empty = PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>$count,'columns'=>[]]]]);
    $check($empty['errors']===[] && count($empty['blocks'][0]['data']['columns'])===$count, "V2 row {$count} supports fully empty columns");
    $rows[]=$result['blocks'][0];
}
// Reject any POST that would silently drop populated columns.
$truncated = $rows[3];
$truncated['data']['column_count'] = 2;
$truncated['data']['columns'][3]['blocks']=[['type'=>'text','data'=>['title'=>'Do not lose me']]];
$invalid = PageBlocks::normalizeSubmitted([$truncated]);
$check($invalid['errors'] !== [], 'Server refuses to discard content from a removed V2 column');
$check(PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>0]]])['errors']!==[], 'Server rejects zero-column V2 row');
$check(PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>7]]])['errors']!==[], 'Server rejects seven-column V2 row');
foreach ([2,3,4,5,6] as $n) {
    $legacy = PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['column_count'=>$n,'preset'=>'equal','columns'=>[]]]]);
    $check($legacy['errors']===[] && ($legacy['blocks'][0]['data']['column_count']??0)===$n && !isset($legacy['blocks'][0]['data']['layout_version']), "Legacy {$n} columns remain unchanged");
}
$unequal = PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['column_count'=>3,'preset'=>'25-50-25','columns'=>[]]]]);
$check(($unequal['blocks'][0]['data']['preset'] ?? '') === '25-50-25', 'Legacy asymmetric Grid remains unchanged');
$view=(string)file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$js=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$check(str_contains($view,'data-builder-template="grid-1"') && str_contains($view,'data-builder-template="grid-4"'), 'One- and four-column templates in existing editor');
$check(str_contains($view,'data-page-row-layout-choice') && str_contains($js,'data-page-row-layout-choice'), 'Row settings modal offers 1–6 column layouts');
$check(str_contains($js,'layout_change_blocked') && str_contains($js,'if (containsContent && isV2)'), 'Unsafe Row reduction blocked client-side');
$check(str_contains($view,'data-page-add-section') && str_contains($js,'data-page-add-section'), 'Standalone sections unchanged');
$check(str_contains($js,'Alt+ArrowLeft') === false && str_contains($js,"event.altKey"), 'Keyboard movement between adjacent columns supported');
$check(str_contains($js,'data-grid-child-remove') && str_contains($js,'data-page-child-toggle'), 'Independent Edit/Delete handlers retained');
$translations=json_decode((string)file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
foreach (['page_builder_v2.layout_columns','page_builder_v2.layout_change_help','page_builder_v2.layout_change_blocked','page_builder_v2.add_block','page_builder_v2.fourth_width_help'] as $key) {
    foreach (['da','en','de','sv','nb'] as $locale) $check(isset($translations[$key][$locale]) && trim($translations[$key][$locale]) !== '', "$key: $locale translated");
}
$adminScss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$adminCss=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$themeScss=(string)file_get_contents($root.'/public_html/assets/scss/layout/_layout.site-grid.scss');
$themeCss=(string)file_get_contents($root.'/public_html/assets/css/style.css');
$check($adminScss===$adminCss, 'Core SCSS/CSS synchronized');
$check(str_contains($themeCss,$themeScss) && str_contains($themeScss,'.site-grid--4'), 'Theme owns responsive 4-column CSS');
$check(!str_contains((string)file_get_contents($root.'/public_html/incl/helpers/page_builder_v2_renderer.php'), 'style='), 'Frontend renderer emits no inline styles');
$version=(string)file_get_contents($root.'/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", $version, $releaseVersion) === 1
    && version_compare($releaseVersion[1], '2.4.4.43', '>='), 'Release version remains at least V2.4.4y5a');
echo "Summary V2.4.4y5: {$passes} PASS / {$failures} FAIL\n";
exit($failures===0?0:1);
