<?php
/** V2.4.4y4: Page Builder Core actions and per-section settings regression. */
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
if (!function_exists('t')) { function t(string $key): string { return $key; } }
require_once $root . '/public_html/admin/incl/page-builder-functions.php';
require_once $root . '/public_html/incl/helpers/page_builder_renderer.php';

$pass=0; $fail=0;
$check = static function(bool $ok,string $name)use(&$pass,&$fail){ if($ok){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name\n";}};
$cases = [
 ['hero',['title'=>'Hello','theme'=>'dark','container_width'=>'wide']],
 ['intro',['title'=>'Welcome','theme'=>'soft','container_width'=>'narrow']],
 ['text',['title'=>'Details','body'=>'One','container_width'=>'full','theme'=>'light']],
 ['text_image',['title'=>'Picture','container_width'=>'standard','theme'=>'dark']],
 ['cta',['title'=>'Call','container_width'=>'wide','theme'=>'soft']],
 ['grid',['layout_version'=>'v2','column_count'=>2,'preset'=>'equal','gap'=>'large','columns'=>[['blocks'=>[['type'=>'text','data'=>['title'=>'A']]]],['blocks'=>[]]],'theme'=>'soft','container_width'=>'wide']],
 ['grid',['column_count'=>4,'preset'=>'equal','columns'=>[], 'theme'=>'dark','container_width'=>'narrow']],
];
foreach($cases as [$type,$data]) {
 $result=PageBlocks::normalizeSubmitted([['type'=>$type,'status'=>'active','data'=>$data]]);
 $clean=$result['blocks'][0]['data'];
 $check($result['errors'] === [], "$type has no validation errors");
 $check(($clean['container_width']??null) === $data['container_width'], "$type width is stored");
 $check(($clean['theme']??null) === $data['theme'], "$type theme is stored");
 $again=PageBlocks::normalizeSubmitted($result['blocks']);
 $check($again['errors'] === [] && $again['blocks']===$result['blocks'], "$type round-trips without data loss");
 $editor=admin_page_builder_block_editor($result['blocks'][0],0);
 $check(substr_count($editor,'data-builder-section-width')===1 && substr_count($editor,'data-builder-section-theme')===1, "$type has exactly one settings pair");
 $check(str_contains($editor,'data-page-section-settings') && str_contains($editor,'data-builder-remove'), "$type has Core settings/delete actions");
 ob_start();render_page_builder_block(['block_type'=>$type, 'data'=>$clean]);$html=ob_get_clean();
 $check(str_contains($html,'data-container-width="'.$data['container_width'].'"'), "$type width exposed to Theme");
 if($type === 'grid') $check(count($clean['columns'])===(int)$data['column_count'], 'Grid count remains stable');
}
foreach(['narrow','standard','wide','full'] as $width) {
 $r=PageBlocks::normalizeSubmitted([['type'=>'text','data'=>['container_width'=>$width]]]);
 $check($r['blocks'][0]['data']['container_width']===$width,'Valid preset '.$width);
}
foreach(['<script>','150vw','none',''] as $width) {
 $r=PageBlocks::normalizeSubmitted([['type'=>'text','data'=>['container_width'=>$width]]]);
 $check($r['blocks'][0]['data']['container_width']==='standard','Unknown width rejected: '.($width?:'empty'));
}
$invalid=PageBlocks::normalizeSubmitted([['type'=>'hero','data'=>['title'=>'Hello','theme'=>'<script>']]]);
$check($invalid['blocks'][0]['data']['theme']==='light','Unknown theme rejected');
$view=file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$js=file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$builder=file_get_contents($root.'/public_html/admin/incl/page-builder-functions.php');
$check(str_contains($view,'data-page-section-settings-dialog') && str_contains($view,'data-page-section-save'),'Settings modal is present');
$check(str_contains($js,'data-page-section-settings') && str_contains($js,'data-builder-section-width') && str_contains($js,'data-builder-section-theme'),'JS connects modal to fields');
$check(str_contains($js,"['compact', 'normal', 'large']"),'Existing grid gap is still editable');
$check(str_contains($builder,"admin_icon_action_button('chevron-down'") && str_contains($builder,"admin_icon_action_button('settings'") && str_contains($builder,"admin_icon_action_button('delete'"),'Core icons used for actions');
$check(!str_contains($builder,'data-page-row-toggle aria-expanded="false">Rediger'),'Old visible text edit action removed');
$css=file_get_contents($root.'/public_html/assets/css/style.css');
$scss=file_get_contents($root.'/public_html/assets/scss/layout/_layout.site-grid.scss');
$check(str_contains($css,$scss),'Theme SCSS source embedded in CSS');
$check(str_contains($scss,'section[data-container-width="wide"] > .container'),'Theme owns widths');
$adminScss=file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$adminCss=file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$check($adminScss===$adminCss,'Core SCSS/CSS synchronized');
$check(!str_contains((string)file_get_contents($root.'/public_html/incl/helpers/page_builder_v2_renderer.php'),'style='),'No inline CSS in public renderer');
$translations=json_decode(file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
foreach(['page_builder_v2.section_settings','page_builder_v2.container_width','page_builder_v2.width_full','page_builder_v2.theme_dark','page_builder_v2.save_settings','page_builder_v2.delete_section','page_builder_v2.edit_block'] as $key){
 $check(count(array_filter(['da','en','de','sv','nb'],static fn($lang)=>trim((string)($translations[$key][$lang]??''))!==''))===5,"$key has five translations");
}
echo "Summary: $pass PASS / $fail FAIL\n";
exit($fail===0?0:1);
