<?php
/** V2.4.4z: validate actual Page Templates catalog, renderers, metadata isolation. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root=dirname(__DIR__);
require_once $root.'/core/classes/Autoload.php';
require_once $root.'/public_html/admin/incl/functions.php';
$translations=json_decode(file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
if (!function_exists('t')) { function t(string $key,array $params=[]): string { global $translations; $result=$translations[$key]['da']??$key;foreach($params as $k=>$v)$result=str_replace('{'.$k.'}',(string)$v,$result);return $result; } }
require_once $root.'/public_html/incl/helpers/page_builder_renderer.php';
$pass=0;$fail=0;
$check=static function(bool $value,string $label)use(&$pass,&$fail):void { $value?++$pass:++$fail;echo ($value?'PASS':'FAIL').': '.$label."\n"; };
$presets=PageTemplateCatalog::all();
$ids=array_column($presets,'id');
$check($ids===['home','about','services','contact','landing'],'Five agreed presets in exact order');
foreach($presets as $p){
  $id=$p['id'];
  $check(count($p['blocks'])===count($p['preview_blocks']) && count($p['blocks'])>=2,"$id previews match block count");
  $check(!str_contains(json_encode($p['blocks']), 'section-'),"$id template carries no persistent section ID");
  $normal=PageBlocks::normalizeSubmitted($p['blocks']);
  $check(!$normal['errors'] && count($normal['blocks'])===count($p['blocks']),"$id normalizes on current model");
  $again=PageBlocks::normalizeSubmitted($normal['blocks']);
  $check(!$again['errors'] && $normal['blocks']===$again['blocks'],"$id PHP round-trip stable");
  $valid=true;
  foreach($p['blocks'] as $block){
   if($block['type']==='grid'){
    $columns=$block['data']['columns']??[];
    if(count($columns)!==(int)$block['data']['column_count']||($block['data']['layout_version']??'')!=='v2')$valid=false;
   }
   ob_start();render_page_builder_block(['block_type'=>$block['type'],'status'=>$block['status'],'data'=>$block['data']]);$html=ob_get_clean();
   if(!str_contains($html,'class="block-section"')) $valid=false;
  }
  $check($valid,"$id frontend renders as Page Builder sections and Rows");
  $check(count(array_filter($p['blocks'],static fn($b)=>$b['type']==='component'))===0,"$id contains no foreign component");
}
$contact=$presets[3];
$contactGrid=$contact['blocks'][1]['data']['columns']??[];
$check(count($contactGrid)===2 && count($contactGrid[1]['blocks'])===0,'Contact form slot intentionally empty');
$check(count($presets[0]['blocks'][2]['data']['columns'])===3,'Home uses 33/33/33');
$check(count($presets[1]['blocks'][3]['data']['columns'])===2,'About uses 50/50');
$strings=['page_templates.modal_title','page_templates.preview_note','page_templates.inserted','page_templates.replace_confirm','page_templates.switch_to_builder'];
foreach($strings as $string){$check(count(array_filter(['da','en','de','sv','nb'],static fn($locale)=>isset($translations[$string][$locale])&&trim($translations[$string][$locale])!==''))===5,"$string localized to all five languages");}
$view=file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$js=file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$check(str_contains($view,'data-page-template-choice') && str_contains($view,'data-page-template-preview') && str_contains($view,'data-page-template-content'),'Real two-pane modal + editor templates');
$check(str_contains($js,"blocks().length > 0 && !await window.adminConfirm") && str_contains($js,'list.replaceChildren(...incoming)'), 'No replacement before explicit confirmation');
$check(str_contains($js,"modeInputs.find((input) => input.checked)?.value !== 'builder'"), 'Legacy HTML mode not implicitly converted');
$check(str_contains($js,'data-builder-section-id') && str_contains($js,'used.has(candidate)'), 'Inserted instance uses unique section IDs');
$check(str_contains($js,"templatesDialog?.addEventListener('keydown'") && str_contains($js,"'ArrowDown'"),'Accessible keyboard template selector');
$check(str_contains($js,"closeDialog(templatesDialog);") && str_contains($js,"admin:changed"),'Changes stay pending normal page Save');
$check(str_contains(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss'),'admin-page-templates__preview'),'Shared Core admin styles own preview');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss')===file_get_contents($root.'/public_html/admin/assets/css/admin.css'),'SCSS and CSS synchronized');
$version=file_get_contents($root.'/public_html/incl/init.php');
$check((bool)preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", $version, $m) && version_compare($m[1] ?? '0', '2.4.4.47', '>='),'Real V2.4.4z version, not TEST release');
echo "Summary V2.4.4z: $pass PASS / $fail FAIL\n";exit($fail?1:0);
