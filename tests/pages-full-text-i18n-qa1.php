<?php

declare(strict_types=1);
/**
 * Read-only localization QA across the complete Pages administration area.
 * Uses rendering stubs: no DB, HTTP, files written or real CMS sessions.
 */
$root = dirname(__DIR__);
require_once $root . '/core/classes/Translator.php';
require_once $root . '/core/classes/PageBlocks.php';
Translator::registerModule('gallery', $root . '/modules/gallery');
Translator::boot('en');
function t(string $key, array $params = []): string { return Translator::get($key, $params); }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function admin_icon(string $name, string $class = ''): string { return '<span data-icon="' . admin_e($name) . '"></span>'; }
function admin_icon_action_button(string $name, string $label, array $attributes = [], string $tone = ''): string {
    return '<button type="button" aria-label="' . admin_e($label) . '"></button>';
}
class AdminToggle {
    public static function render(mixed ...$args): string { return '<span data-toggle></span>'; }
}
require_once $root . '/public_html/admin/incl/page-builder-functions.php';

$pass=0;$fail=0;
$check=static function(bool $ok, string $label)use(&$pass,&$fail):void {
    if($ok){$pass++;}else{$fail++;fwrite(STDERR,"FAIL: $label\n");}
};
$catalog=json_decode((string)file_get_contents($root.'/core/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
$moduleCatalog=json_decode((string)file_get_contents($root.'/modules/gallery/lang/translations.json'),true,512,JSON_THROW_ON_ERROR);
$locales=['en','da','de','sv','nb'];
$keys=['Billede','Tekst','Tekst + billede','CTA','Dynamisk komponent','Kompakt','Normal','Stor',
    'Lige brede kolonner','pages.builder.no_heading','pages_detail.row_column_singular',
    'pages_detail.row_columns','blok','blokke','Øverst','Nederst',
    'pages.builder.remove_columns','pages.builder.remove_columns_warning',
    'pages.builder.legacy_section_error','Beskyttet', 'Sektion med 1–6 kolonner og indhold i hver kolonne.'];
foreach ($keys as $key) {
    $item=$catalog[$key]??null;
    $check(is_array($item)&&array_keys($item)!==[]&&count(array_diff($locales,array_keys($item)))===0,
        "Every locale exists for $key");
}
foreach (['Display type','Grid + Lightbox','Slider','Images per row','Fullscreen Lightbox','Autoplay','Time per slide (seconds)'] as $key) {
    $item=$moduleCatalog[$key]??null;
    $check(is_array($item)&&count(array_diff($locales,array_keys($item)))===0,"Gallery locale $key");
}
$grid=[
 'type'=>'grid','status'=>'active',
 'data'=>['layout_version'=>'v2','section_variant'=>'standard','column_count'=>1,'preset'=>'equal','gap'=>'normal',
   'columns'=>[['blocks'=>[['type'=>'image','status'=>'active','data'=>[]]]]]]
];
foreach($locales as $locale){
    Translator::setLocale($locale);
    $html=admin_page_builder_block_editor($grid,0);
    $image=t('Billede');
    $check(str_contains($html,'<strong>'.$image.'</strong>'),"$locale: image block name translated");
    $check(str_contains($html,t('Kolonne').' 1'),"$locale: column heading translated");
    $check(str_contains($html,'1 '.t('blok')),"$locale: one block translated");
    $check(str_contains($html,t('Intet billede valgt')),"$locale: empty image summary translated");
    $check(str_contains($html,t('pages_detail.row_column_singular',['count'=>1]).' · 100 %'),"$locale: grid summary singular translated");
    $check(str_contains($html,'<option value="image">'.$image.'</option>'),"$locale: picker image option translated");
    $child=admin_page_builder_grid_child_editor(['type'=>'text_image','data'=>[]],'blocks[0][data][columns][0][blocks][0]');
    $check(str_contains($child,'<strong>'.t('Tekst + billede').'</strong>'),"$locale: text-image title translated");
    $check(str_contains($child,'>'.t('Øverst').'</option>')&&str_contains($child,'>'.t('Nederst').'</option>'),"$locale: image placement options translated");
    $check(str_contains($child,t('pages.builder.no_heading')),"$locale: no-heading summary translated");
    $check(admin_page_builder_summary('grid',['column_count'=>2,'preset'=>'equal'])===t('pages_detail.row_columns',['count'=>2]).' · 50 / 50',"$locale: plural section summary");
    $legacy=admin_page_builder_grid_editor('blocks[0]',['column_count'=>2,'preset'=>'equal','gap'=>'large','columns'=>[]]);
    $check(str_contains($legacy,'>'.t('Stor').'</option>'),"$locale: gap label translated");
    $check(t('gallery:Display type')!==''&&t('gallery:Images per row')!=='',"$locale: Gallery module translations registered");
}
// Validation messages are localized only at the Pages admin boundary; the
// normalizer's default CLI/model errors remain unchanged for old callers.
$validationExamples = [
 ['pages.builder.error_unknown_section', [['type'=>'no-such-section','data'=>[]]], 'section'],
 ['pages.builder.error_invalid_column_count', [['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>7]]], 'section'],
 ['pages.builder.error_populated_columns', [['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>1,
   'columns'=>[['blocks'=>[]],['blocks'=>[['type'=>'text','data'=>['title'=>'Keep']]]]]]]], 'section'],
 ['pages.builder.error_max_column_blocks', [['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>1,
   'columns'=>[['blocks'=>array_fill(0,13,['type'=>'text','data'=>[]])]]]]], 'section'],
 ['pages.builder.error_hero_has_columns', [['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>1,
    'section_variant'=>'hero','hero_layout'=>'dedicated',
    'columns'=>[['blocks'=>[['type'=>'text','data'=>[]]]]]]]], 'hero'],
 ['pages.builder.error_unknown_block', [['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>1,
    'columns'=>[['blocks'=>[['type'=>'unrecognized','data'=>[]]]]]]]], 'block'],
 ['pages.builder.error_invalid_component', [['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>1,
    'columns'=>[['blocks'=>[['type'=>'component','data'=>['component'=>'not-a-module']]]]]]]], 'component'],
 ['pages.builder.error_max_sections', array_fill(0,61,['type'=>'grid','data'=>['column_count'=>2,'columns'=>[]]]), 'limit'],
];
foreach($locales as $locale){
    Translator::setLocale($locale);
    foreach($validationExamples as [$key,$input,$label]){
        $r=admin_page_builder_payload(['blocks'=>$input]);
        $check(!empty($r['errors']), "$locale: validation $label returned a message");
        $check(in_array(t($key, ['max'=>str_contains($key, 'max_sections') ? 60 : 12,
                'section'=>1,'column'=>1,'block'=>1]), $r['errors'], true),
                "$locale: validation $label uses localized message and correct placeholders");
    }
}
Translator::setLocale('en');
$validInput=[['type'=>'grid','status'=>'active','data'=>[
    'section_id'=>'section-492017','layout_version'=>'v2','column_count'=>1,'preset'=>'equal',
    'columns'=>[['blocks'=>[['type'=>'image','status'=>'active','data'=>['image_id'=>5,'image_path'=>'/uploads/example.webp']]]]]
]]];
$plain=PageBlocks::normalizeSubmitted($validInput);
$localized=admin_page_builder_payload(['blocks'=>$validInput]);
$check($plain['errors']===[] && $localized['errors']===[] && $plain['blocks']===$localized['blocks'],
       'Translated validation changes no normalized Page Builder content or section identifiers');
$old=PageBlocks::normalizeSubmitted([['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>7]]]);
$check(isset($old['errors'][0])&&str_contains($old['errors'][0], 'skal have mellem 1 og 6 kolonner'),
       'Existing CLI/model error messages preserved when no translator is supplied');

foreach (array_merge(PageBlocks::definitions(), PageBlocks::gridChildDefinitions()) as $definition) {
    foreach (['label','description'] as $field) {
        $key=(string)($definition[$field] ?? '');
        $check(isset($catalog[$key]), 'Builder definition ' . $field . ' translated: ' . $key);
    }
}
$viewsDir=$root.'/public_html/admin/views/pages';
$files=array_merge(glob($viewsDir.'/*.php')?:[],glob($viewsDir.'/partials/*.php')?:[],
  [$root.'/public_html/admin/incl/page-builder-functions.php']);
$referenced=[];
foreach($files as $file){
 $source=(string)file_get_contents($file);
 if(preg_match_all('/\bt\(\s*[\'\"]([^\'\"]+)[\'\"]/', $source,$matches)){
   foreach($matches[1] as $key) {
     if(in_array($key,['page_builder_v2.width_','page_builder_v2.theme_'],true))continue;
     $referenced[$key]=true;
   }
 }
}
foreach(array_keys($referenced) as $key){
    $module=null;$lookup=$key;
    if(preg_match('/^([a-z][a-z0-9_-]*):(.+)$/D',$key,$m)){$module=$m[1];$lookup=$m[2];}
    $dict=$module==='gallery'?$moduleCatalog:$catalog;
    $check(isset($dict[$lookup]),"Catalog key is defined: $key");
}
foreach(['narrow','standard','wide','full'] as $width){$check(isset($catalog['page_builder_v2.width_'.$width]),"Section width $width available");}
foreach(['light','soft','dark'] as $theme){$check(isset($catalog['page_builder_v2.theme_'.$theme]),"Section theme $theme available");}
$revision=(string)file_get_contents($root.'/public_html/admin/views/pages/revision.php');
$check(str_contains($revision,"'protected' => t('Beskyttet')"),'Revision compares protected status in current locale');
$overview=(string)file_get_contents($root.'/public_html/admin/views/pages/view.php');
$check(str_contains($overview,"t((string)(\$def['label'] ?? \$type))"),'Page detail block label translated');
$check(str_contains($overview,"$".'summary .= ' . "' · ' . t(\$presetLabel);"),'Page detail legacy preset translated');
$js=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$layout=(string)file_get_contents($root.'/public_html/admin/views/partials/layout.php');
foreach(['pages_detail.row_column_singular','pages_detail.row_columns','pages.builder.no_heading',
'pages.builder.remove_columns','pages.builder.remove_columns_warning','Lige brede kolonner','Intet billede valgt'] as $key){
    $check(str_contains($layout,"'".$key."'"),"Browser JavaScript receives translation: $key");
}
$check(str_contains($js,"window.cmsTranslate('pages.builder.no_heading')"),'JS uses localized empty summaries');
$check(str_contains($js,"window.cmsTranslate('pages.builder.remove_columns_warning')"),'JS confirms legacy reductions in chosen language');
$check(str_contains($js,"window.cmsTranslate('Intet billede valgt')"),'JS uses localized empty image');
$check(!str_contains($js,'`${count} kolonner · ${presetLabel}`'),'JS no longer hardcodes Danish column summary');
$check(str_contains($js,"'pages_detail.row_column_singular' : 'pages_detail.row_columns'"),'JS uses singular/plural translated summary');

printf("Pages Full Text I18N QA1: %d PASS / %d FAIL (read-only, 5 languages)\n",$pass,$fail);
exit($fail===0?0:1);
