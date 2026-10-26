<?php
/** V2.4.4z20j – six-column Row Settings, persistence, rendering and UI contracts. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
if (!function_exists('t')) {
    function t(string $key): string { return ['Kolonne'=>'Kolonne','page_builder_v2.columns'=>'kolonner'][$key] ?? $key; }
}
require_once $root . '/public_html/incl/helpers/page_builder_renderer.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass,&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $ok ? $pass++ : $fail++;
};
foreach (range(1, 6) as $n) {
    $cols = array_fill(0,$n,['blocks'=>[]]);
    $cols[$n - 1]['blocks'][]=['type'=>'text','status'=>'active','data'=>['title'=>"Visible $n",'body'=>"Body $n"]];
    $input=[['type'=>'grid','status'=>'active','data'=>[
        'section_id'=>'section-' . str_pad((string)(880000+$n),6,'0',STR_PAD_LEFT),
        'layout_version'=>'v2','column_count'=>$n,'preset'=>'equal','gap'=>'large',
        'columns'=>$cols,'theme'=>'soft','container_width'=>'wide'
    ]]];
    $normalized=PageBlocks::normalizeSubmitted($input);
    $check($normalized['errors']===[],"$n columns validates");
    $block=$normalized['blocks'][0]??[];
    $data=$block['data']??[];
    $check(($data['layout_version']??null)==='v2' && ($data['column_count']??0)===$n,"$n V2 marker/count persists");
    $check(count($data['columns']??[])===$n && ($data['columns'][$n-1]['blocks'][0]['data']['title']??'')==="Visible $n","$n last column content retained");
    $check(PageBlocks::normalizeSubmitted($normalized['blocks'])===$normalized,"$n exact normalization roundtrip");
    ob_start();render_page_builder_block(['block_type'=>'grid','status'=>'active','data'=>$data]);$html=(string)ob_get_clean();
    $check(str_contains($html,'class="block-section"') && str_contains($html,"site-grid site-grid--$n"),"$n uses V2 frontend section and grid class");
    $check(substr_count($html,'class="site-grid--item"')===$n && str_contains($html,"Visible $n"),"$n public columns and last block rendered");
    $check(!str_contains($html,'grid--cols-'),"$n not routed to legacy renderer");
    $editor=admin_page_builder_block_editor($block,'0');
    $check(substr_count($editor,'data-grid-column-index=')===$n,"$n editable column slots");
    $check(str_contains($editor,"data-grid-count=\"$n\"") && str_contains($editor,"data-page-layout-label>$n ".t($n===1?'Kolonne':'page_builder_v2.columns')),"$n header and editor count");
    $check(str_contains($editor,'data-builder-section-status') && str_contains($editor,'data-grid-child-add'),"$n status and add-child hooks intact");
    $empty=PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>$n,'columns'=>[]]]]);
    $check($empty['errors']===[] && count($empty['blocks'][0]['data']['columns'])===$n,"$n empty column row persists");
}
foreach ([0,-1,7,999] as $n) {
    $invalid=PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>$n,'columns'=>[]]]]);
    $check($invalid['errors']!==[],"Invalid $n columns rejected server-side");
}
foreach ([5,6] as $n) {
    $cols=array_fill(0,$n,['blocks'=>[]]);
    $cols[$n-1]['blocks'][]=['type'=>'text','status'=>'active','data'=>['title'=>'Do not remove']];
    $result=PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>$n-1,'columns'=>$cols]]]);
    $check($result['errors']!==[],"Shrinking $n with populated removed column rejected");
    $cols[$n-1]['blocks']=[];
    $result=PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>$n-1,'columns'=>$cols]]]);
    $check($result['errors']===[] && count($result['blocks'][0]['data']['columns'])===$n-1,"Shrinking $n with empty removed column allowed");
    $legacy=PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['column_count'=>$n,'preset'=>'equal','columns'=>[]]]]);
    $check($legacy['errors']===[] && !isset($legacy['blocks'][0]['data']['layout_version']),"Legacy $n grid remains legacy");
}
$view=(string)file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$js=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$server=(string)file_get_contents($root.'/core/classes/PageBlocks.php');
$frontend=(string)file_get_contents($root.'/public_html/incl/helpers/page_builder_renderer.php');
$themeScss=(string)file_get_contents($root.'/public_html/assets/scss/layout/_layout.site-grid.scss');
$themeCss=(string)file_get_contents($root.'/public_html/assets/css/style.css');
$adminCss=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$adminScss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
foreach(range(1,6) as $n) {
    $check(str_contains($view,'value="<?= $number; ?>" data-page-row-layout-choice') && str_contains($view,'range(1, 6)'),"Picker can offer $n");
    $check(str_contains($js,"[1, 2, 3, 4, 5, 6].includes(chosen)"),"JS accepts $n through settings dialog");
    $check($n<=4 || (str_contains($view,"data-builder-template=\"grid-<?= \$count; ?>\"") && str_contains($js,"'grid-$n'")),"$n available as new Row template");
    $check($n<=4 || str_contains($themeScss,".site-grid--$n"),"$n theme column layout");
}
$check(str_contains($js,'if (containsContent && isV2)') && str_contains($js,'layout_change_blocked'), 'JS blocks destructive reductions');
$check(str_contains($server,'flyt eller fjern blokke i overskydende kolonner før gemning'), 'Server still blocks destructive reductions');
$check(str_contains($frontend,'[1, 2, 3, 4, 5, 6]'), 'Frontend renderer accepts new V2 row range');
$check(str_contains($themeScss,'.site-grid--5 { grid-template-columns: repeat(5,') && str_contains($themeScss,'.site-grid--6 { grid-template-columns: repeat(6,'), 'Theme desktop supports 5/6');
$check(str_contains($themeScss,'.site-grid--5, .site-grid--6') && str_contains($themeScss,'@media (min-width: 992px)'), 'Theme reflows new columns by breakpoint');
$check(str_contains($themeCss,$themeScss), 'Compiled frontend contains new full Theme fragment');
$check($adminScss===$adminCss, 'Core SCSS/CSS byte-identical');
$check(str_contains($adminCss,'[data-grid-count="5"]') && str_contains($adminCss,'[data-grid-count="6"]') && str_contains($adminCss,'@container (max-width:620px)'), 'Responsive Core admin CSS supports 5/6');
$check(!str_contains((string)file_get_contents($root.'/public_html/incl/helpers/page_builder_v2_renderer.php'),'style='), 'No frontend inline styling');
$translations=json_decode((string)file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
foreach(['da','en','de','sv','nb'] as $locale) {
    $check(trim((string)($translations['page_builder_v2.equal_width_help'][$locale]??''))!=='',"New add-row help localized $locale");
}
$version=(string)file_get_contents($root.'/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", $version, $matches) === 1 && version_compare($matches[1] ?? '0', '2.4.4.92', '>='), 'z20j or newer version retains compatibility');
echo "Page Builder 1–6 Columns z20j: $pass PASS / $fail FAIL\n";
exit($fail===0 ? 0 : 1);
