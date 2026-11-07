<?php
declare(strict_types=1);
/** Synthetic, no DB connection, no file or user data modifications. */
$root = dirname(__DIR__);
$dictionary = json_decode((string)file_get_contents($root . '/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
function t(string $key): string { global $dictionary; return $dictionary[$key]['en'] ?? $key; }
function admin_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
class Pages {
  public function allPublished(): array {
    return [
      ['id'=>1, 'page_title'=>'Home', 'page_slug'=>'forside', 'parent_id'=>null, 'page_status'=>'published'],
      ['id'=>2, 'page_title'=>'Contact', 'page_slug'=>'kontakt', 'parent_id'=>null, 'page_status'=>'published'],
      ['id'=>3, 'page_title'=>'Info & Support', 'page_slug'=>'support', 'parent_id'=>2, 'page_status'=>'published'],
      ['id'=>4, 'page_title'=>'Protected internal', 'page_slug'=>'private', 'parent_id'=>null, 'page_status'=>'protected'],
      ['id'=>5, 'page_title'=>'Published child under protected', 'page_slug'=>'nested', 'parent_id'=>4, 'page_status'=>'published'],
      ['id'=>6, 'page_title'=>'Draft internal', 'page_slug'=>'draft', 'parent_id'=>null, 'page_status'=>'draft'],
      ['id'=>7, 'page_title'=>'Escaping <script>', 'page_slug'=>'escaping', 'parent_id'=>null, 'page_status'=>'published'],
    ];
  }
}
require $root . '/core/classes/PagePath.php';
require $root . '/public_html/admin/incl/page-builder-functions.php';
$count=0;
$test=function(bool $ok,string $name)use(&$count):void {if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);} $count++;};
$choices=admin_page_builder_published_links();
$test(($choices['/']??'')==='Home', 'Home resolves to /');
$test(($choices['/kontakt']??'')==='Contact', 'Published page resolves');
$test(($choices['/kontakt/support']??'')==='Info & Support', 'Nested path resolved');
$test(!isset($choices['/private']), 'Protected excluded');
$test(!isset($choices['/draft']), 'Draft excluded');
$test(!isset($choices['/private/nested']), 'Published child with unpublished ancestor excluded');
$test(($choices['/escaping']??'')==='Escaping <script>', 'Title loaded as data');
foreach(['en','da','de','sv','nb'] as $lang) {
 foreach(['page_builder_v2.link_destination','page_builder_v2.link_none','page_builder_v2.link_published','page_builder_v2.link_custom'] as $key){
  $test(is_string($dictionary[$key][$lang]??null) && trim($dictionary[$key][$lang])!=='', "Translation $lang/$key");
 }
}
$render=static fn($url,$key='button_url'):string=>admin_page_builder_link_field('blocks[0][data][columns][0][blocks][0]',$key,[$key=>$url]);
$known=$render('/kontakt');
$test(substr_count($known, 'name="blocks[0][data][columns][0][blocks][0][data][button_url]"')===1, 'Single persisted POST field');
$test(str_contains($known,'value="/kontakt" selected'), 'Known page preselected');
$test(str_contains($known,'data-page-link-custom hidden'), 'Custom input hidden for known page');
$test(str_contains($known,'data-page-link-custom-input') && str_contains($known,'disabled'), 'No custom submission on known choice');
$test(str_contains($known,'Info &amp; Support'), 'HTML escaped title');
$test(str_contains($known,'Escaping &lt;script&gt;'), 'HTML escaped untrusted page title');
$test(!str_contains($known,'Protected internal') && !str_contains($known,'Draft internal'), 'Restricted pages absent');
$blank=$render('');
$test(str_contains($blank,'value="" selected'), 'Blank link initially selected');
$external=$render('https://example.com/contact?x=1&y=2');
$test(str_contains($external,'value="__custom__" selected'), 'External saved link selects custom');
$test(str_contains($external,'data-page-link-custom >'), 'Custom input visible for saved custom');
$test(str_contains($external,'https://example.com/contact?x=1&amp;y=2'), 'External link escaped and preserved');
$hero=$render('/kontakt','hero_button_url');
$test(substr_count($hero,'[data][hero_button_url]"')===1, 'Hero preserves historic stored field');
$src=(string)file_get_contents($root.'/public_html/admin/incl/page-builder-functions.php');
$test(str_contains($src,"admin_page_builder_link_field(\$prefix, 'button_url', \$data)"), 'CTA renderer uses shared field');
$test(str_contains($src,"admin_page_builder_link_field(\$prefix, 'hero_button_url', \$data)"), 'Hero renderer uses shared field');
$test(!str_contains($src,'name="<?= $e($prefix); ?>[data][button_url]"'), 'Old CTA raw URL field removed');
$js=(string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$test(str_contains($js,'syncPageLinkField')&&str_contains($js,"builderForm.addEventListener('change'"), 'JS selector event delegated');
$test(str_contains($js,"builderForm.addEventListener('input'"), 'JS custom URL event delegated');
$test(str_contains($js,"target.dispatchEvent(new Event('change'"), 'Hidden changes notify form');
// Saving a selected page or custom URL must use the original PageBlocks schema.
require $root . '/core/classes/PageBlocks.php';
$submitCta = static function(string $url): array {
    return PageBlocks::normalizeSubmitted([[ 'type'=>'grid', 'data'=>[
        'layout_version'=>'v2', 'column_count'=>1, 'columns'=>[[ 'blocks'=>[[
            'type'=>'cta', 'data'=>['title'=>'Contact', 'button_label'=>'Contact', 'button_url'=>$url],
        ]] ]],
    ] ]]);
};
foreach (['/kontakt', '/kontakt/support', 'https://example.com/?a=1'] as $url) {
    $saved = $submitCta($url);
    $test($saved['errors']===[], 'CTA link accepts original field format');
    $test(($saved['blocks'][0]['data']['columns'][0]['blocks'][0]['data']['button_url'] ?? null) === $url,
        'CTA link persists unchanged for ' . $url);
}
$hero = PageBlocks::normalizeSubmitted([[ 'type'=>'grid','data'=>[
    'layout_version'=>'v2','column_count'=>1,'section_variant'=>'hero','hero_layout'=>'dedicated',
    'hero_title'=>'Hello', 'hero_button_label'=>'Contact', 'hero_button_url'=>'/kontakt',
]]]);
$test($hero['errors']===[] && ($hero['blocks'][0]['data']['hero_button_url']??'')==='/kontakt',
    'Dedicated Hero button retains original stored URL field');
echo "Page Builder Link Picker QA1: $count PASS / 0 FAIL (synthetic, 5 languages, no DB)\n";
