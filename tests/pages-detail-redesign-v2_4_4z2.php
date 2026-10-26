<?php
/** V2.4.4z2 — output-based Pages detail view checks, no DB required. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$catalog = json_decode((string)file_get_contents($root.'/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
function t(string $key, array $vars = []): string { global $catalog; $text=$catalog[$key]['da']??$key;foreach($vars as $k=>$v) $text=str_replace('{'.$k.'}',(string)$v,$text);return $text; }
function admin_e(string $v): string { return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
require_once $root.'/core/classes/AdminEscaper.php';
require_once $root.'/core/classes/AdminIcons.php';
function admin_icon(string $name, string $class=''): string { return AdminIcons::render($name, $class); }
function admin_page_public_url(array $p): string { return (string)($p['public_url']??'/example'); }
function admin_page_status_class(string $status): string { return 'page-'.preg_replace('/[^a-z-]/','',$status); }
function admin_page_status_label(string $status): string { return match($status){'published'=>'Publiceret','draft'=>'Kladde','protected'=>'Beskyttet',default=>'Ukendt'}; }
function admin_page_builder_summary(string $type,array $data): string { return (string)($data['title']??'Ingen overskrift endnu'); }
final class Pages { public static function isSystemSlug(string $slug):bool{return $slug==='home';} }
final class PageBlocks { public static function definitions():array {return ['hero'=>['label'=>'Hero'],'grid'=>['label'=>'Row'],'intro'=>['label'=>'Intro'],'text'=>['label'=>'Tekstblok'],'cta'=>['label'=>'CTA']];} }
final class PageScheduler { public static function localLabel(?string $date):string{return $date??'Ingen';} }
$source = (string)file_get_contents($root.'/public_html/admin/views/pages/view.php');
$end = "require __DIR__ . '/../partials/layout.php';";
if (substr_count($source,$end) !== 1) { fwrite(STDERR, "Cannot identify view layout boundary\n"); exit(1); }
$mockView = str_replace($end,'echo $bodyHtml;', $source);
$temp = tempnam(sys_get_temp_dir(),'page-view-z2-');
if (!$temp) { exit(1); }
file_put_contents($temp,$mockView);
$record = ['id'=>17,'page_slug'=>'om-os','page_title'=>'Om os','page_description'=>'Kort introduktion', 'page_status'=>'published','content_mode'=>'builder','public_url'=>'https://example.org/om-os','meta_title'=>'Om os – Virksomhed','meta_description'=>'Læs vores historie og værdier.','updated'=>'2026-10-15 17:20:00','parent_title'=>'Forside'];
$blocksFixture=[
 ['block_type'=>'hero','data'=>['title'=>'Velkommen til os'],'status'=>'active'],
 ['block_type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>1,'columns'=>[]],'status'=>'hidden'],
 ['block_type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>4,'columns'=>[]],'status'=>'active'],
 ['block_type'=>'cta','data'=>['title'=>'Kontakt os'],'status'=>'active'],
];
$render = static function(array $record,array $records,int $count=2,array $plan=[]) use ($temp):string {
    $pageRecord=$record;$blocks=$records;$revisionCount=$count;$childCount=2;$schedule=$plan;$notice=null;
    ob_start(); include $temp; return (string)ob_get_clean();
};
$builder=$render($record,$blocksFixture,8);
$legacy=$render(array_merge($record,['content_mode'=>'legacy','page_title'=>'<img src=x onerror=alert(1)>','page_description'=>'','meta_title'=>'','meta_description'=>'','page_content'=>'<script>alert("hi")</script>']),[]);
$scheduled=$render(array_merge($record,['page_status'=>'draft']),[],0,['publish_at_utc'=>'2026-11-01','unpublish_at_utc'=>'2026-12-01']);
$system=$render(array_merge($record,['page_slug'=>'home']),$blocksFixture);
$pass=0;$fail=0;
$check=static function(bool $ok,string $what) use (&$pass,&$fail):void { $ok?++$pass:++$fail;echo ($ok?'PASS':'FAIL').': '.$what."\n"; };
$check(str_contains($source,'V2.4.4z2'),'New view only, no controller replacement');
$check(str_contains($builder,'admin-page-overview-heading'),'New page header rendered');
$check(str_contains($builder,'Om os'),'Page title shown');
$check(str_contains($builder,'Publiceret'),'Status in heading');
$check(str_contains($builder,'https://example.org/om-os'),'Real public URL');
$check(str_contains($builder,'target="_blank" rel="noopener noreferrer"'),'External opens new tab safely');
$check(str_contains($builder,'mode=edit&amp;id=17'),'Edit route remains');
$check(!str_contains($builder,'page-delete-dialog') && !str_contains($builder,'Slet side') && !str_contains($builder,'admin-danger-zone'),'Delete absent on view');
$check(str_contains($builder,'admin-page-overview-sections'),'Builder sections list');
$check(substr_count($builder,'class="admin-page-overview-section')>=4,'Four rendered section entries');
$check(str_contains($builder,'4 sektioner'),'Real section count');
$check(str_contains($builder,'1 skjult'),'Hidden count correctly shown');
$check(str_contains($builder,'50 / 50')===false,'V2 rows do not claim legacy preset');
$check(str_contains($builder,'1 kolonner') && str_contains($builder,'4 kolonner'),'V2 single and four columns represented');
$check(str_contains($builder,'admin-page-overview-section__hidden'),'Hidden section marked');
$check(str_contains($builder,'Velkommen til os') && str_contains($builder,'Kontakt os'),'Existing builder content summarised');
$check(str_contains($builder,'Om os – Virksomhed') && str_contains($builder,'Læs vores historie og værdier.'),'Actual saved SEO fields displayed');
$check(str_contains($legacy,'SEO-titel ikke udfyldt') && str_contains($legacy,'SEO-beskrivelse ikke udfyldt'),'Missing SEO fields not fabricated');
$check(!str_contains($legacy,'Læs vores historie og værdier.'),'SEO preview never retains old record');
$check(str_contains($builder,'8 gemte versioner'),'Actual revision count in sidebar');
$check(str_contains($builder,'mode=history&amp;id=17'),'History link available');
$check(str_contains($builder,'Forside'),'Parent title visible');
$check(str_contains($builder,'2</dd>'),'Child count visible');
$check(str_contains($builder,'Kort introduktion'),'Description retained');
$check(str_contains($builder,'Ingen planlægning'),'No planned schedule accurate');
$check(str_contains($scheduled,'2026-11-01') && str_contains($scheduled,'2026-12-01'),'Both scheduled timestamps visible');
$check(str_contains($system,'Systemside'),'System page indication retained');
$check(str_contains($legacy,'<details class="admin-page-overview-source">'),'Legacy source collapsed by default');
$check(str_contains($legacy,'Vis HTML-kildekode'),'Legacy source disclosure labelled');
$check(str_contains($legacy,'&lt;script&gt;alert(&quot;hi&quot;)&lt;/script&gt;'),'Legacy HTML is escaped');
$check(!str_contains($legacy,'<script>alert'),'No executable legacy source reflected');
$check(str_contains($legacy,'&lt;img src=x onerror=alert(1)&gt;'),'Page title escaped');
$check(!str_contains($legacy,'<img src=x onerror'),'HTML injection not reflected');
$check(str_contains($legacy,'admin-page-overview-legacy') && !str_contains($legacy,'admin-page-overview-sections'),'Legacy view avoids builder summary');
$check(str_contains($source,'aria-labelledby="page-structure-title"') && str_contains($source,'aria-labelledby="page-seo-title"'),'Section headings exposed');
$check(str_contains($source,'admin_page_builder_summary('),'Existing summary helper preserved');
$check(str_contains($source,'PageScheduler::localLabel('),'Existing scheduler formatting used');
$css=(string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$scss=(string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$check($css===$scss,'Core SCSS and CSS synchronized');
$check(str_contains($css,'.admin-page-overview-heading') && str_contains($css,'.admin-page-overview-seo'),'Scoped Core styles provided');
$check(str_contains($css,'@media (max-width: 700px)'),'Responsive styles provided');
$check(!str_contains($source,'<style') && !str_contains($source,'style="'),'No inline styles in Pages module');
$check(!str_contains($source,'delete-panel.php') && !str_contains($source,'delete-modal.php'),'Delete panel/confirmation moved out of detail');
foreach (['pages_detail.structure','pages_detail.seo_preview_help','pages_detail.view_revisions','pages_detail.show_html','pages_detail.no_schedule'] as $key) {
 $check(count(array_filter(['da','en','de','sv','nb'],static fn($locale):bool=>isset($GLOBALS['catalog'][$key][$locale]) && trim($GLOBALS['catalog'][$key][$locale])!==''))===5,"$key translated in all 5 locales");
}
$check((bool)preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $versionMatch) && version_compare($versionMatch[1] ?? '0', '2.4.4.49', '>='), 'V2.4.4z2 version baseline preserved');
if (in_array('--fixture',$argv,true)) {
 $html='<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$css.'</style></head><body class="nf-admin"><main style="max-width:1230px;margin:24px auto;padding:16px">'.$builder.'</main></body></html>';
 file_put_contents(dirname(__DIR__).'/../page_detail_fixture.html',$html);
}
unlink($temp);
echo "Summary V2.4.4z2: $pass PASS / $fail FAIL\n";
exit($fail?1:0);
