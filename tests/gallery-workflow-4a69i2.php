<?php
declare(strict_types=1);
/** Static and stubbed runtime tests only. Does not touch active DB, media or sites. */
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__);
require_once $root.'/core/classes/Autoload.php';
require_once $root.'/modules/gallery/GalleryModule.php';
$pass=0;$fail=0;
$check=static function(bool $ok,string $name) use (&$pass,&$fail):void {
    echo ($ok?'PASS':'FAIL').': '.$name."\n";$ok?$pass++:$fail++;
};
function t(string $key):string{return $key;}
class DisplayModesDb extends Db {
    public function __construct(){}
    public function fetchArray(string $sql,array $params=[]):array {
        if (str_contains($sql,'FROM module_galleries WHERE id')) {
            return [['id'=>(int)$params[0],'name'=>'Media & <script>','status'=>'active','lightbox_enabled'=>0]];
        }
        if (str_contains($sql,'FROM module_gallery_items'))return [
            ['file_path'=>'/uploads/one.jpg','alt_text'=>'Picture & One','width'=>1200,'height'=>800],
            ['file_path'=>'/uploads/two.jpg','alt_text'=>'Picture Two','width'=>600,'height'=>400],
        ];
        return [];
    }
}
class DisplayModesMedia extends MediaLibrary {public function __construct(){} public function publicUrl(string $path):string{return $path;}}
$module=new GalleryModule();
foreach(['repository'=>new GalleryRepository(new DisplayModesDb()),'media'=>new DisplayModesMedia()] as $key=>$value){
  (new ReflectionClass(GalleryModule::class))->getProperty($key)->setValue($module,$value);
}
$render=static function(array $mods=[])use($module):string {ob_start();$ok=$module->renderComponent('gallery-9',$mods);$html=(string)ob_get_clean();if(!$ok)throw new RuntimeException('render failed');return $html;};
$legacy=$render();
$check(str_contains($legacy,'gallery--grid')&&!str_contains($legacy,'gallery--lightbox')&&!str_contains($legacy,'data-gallery-controls'),'Existing gallery defaults preserved');
$grid=$render(['gallery_mode'=>'lightbox','gallery_columns'=>5,'gallery_fullscreen'=>'1']);
$check(str_contains($grid,'gallery--lightbox')&&substr_count($grid,'data-gallery-lightbox-image')===2&&!str_contains($grid,'data-gallery-controls'),'New Grid + Lightbox renders clickable images');
$slider=$render(['gallery_mode'=>'slider','gallery_autoplay'=>'1','gallery_interval'=>7]);
$check(str_contains($slider,'data-gallery-slider')&&str_contains($slider,'aria-roledescription="carousel"'),'Slider uses semantic carousel region');
$check(str_contains($slider,'data-gallery-previous')&&str_contains($slider,'data-gallery-next')&&str_contains($slider,'data-gallery-counter'),'Slider has accessible manual controls');
$check(!str_contains($slider,'data-gallery-lightbox-image') && str_contains($slider,'data-gallery-autoplay="1"') && str_contains($slider,'data-gallery-interval="7"'),'Slider has separate autoplay and no Lightbox link');
$check(str_contains($slider,'Media &amp; &lt;script&gt;')&&!str_contains($slider,'<script>'),'Gallery label escaped');
$off=$render(['gallery_mode'=>'slider','gallery_autoplay'=>'0']);
$check(!str_contains($off,'gallery--lightbox')&&!str_contains($off,'data-gallery-lightbox-image'),'Manual slider uses no Lightbox');
$bad=$render(['gallery_layout'=>'<img>','gallery_lightbox'=>'<img>']);
$check(str_contains($bad,'gallery--grid')&&!str_contains($bad,'<img>'),'Unknown overrides safely ignored');
$check($module->frontendScripts()===['gallery-lightbox.js','gallery-slider.js'],'Both behavior scripts registered');
$js=file_get_contents($root.'/modules/gallery/assets/gallery-slider.js');
$check(str_contains($js,'controls.hidden = slides.length <= 1')&&str_contains($js,'gallery--enhanced'),'No-JS gallery fallback and single-image controls');
$check(str_contains($js,'setTimeout')&&!str_contains($js,'setInterval'),'Bounded pauseable slideshow uses timeout rather than interval');
$check(str_contains($js,"event.key === 'ArrowLeft'")&&str_contains($js,"event.key === 'ArrowRight'"),'Keyboard arrow support');
$check(str_contains($js,'slide.hidden = position !== active'),'Inactive images hidden for keyboard and screen reader');
$check(str_contains($js,'return;')&&str_contains($js,'gallery.dataset.galleryInitialized'),'Idempotent JS initialization');
$galleryLayout=(string)file_get_contents($root.'/public_html/admin/incl/page-builder-functions.php');
$check(substr_count($galleryLayout,'admin_page_builder_gallery_fields($prefix, $data);')===2,'Top-level and grid-child editors include gallery settings');
$check(str_contains($galleryLayout,'data-builder-gallery-settings'),'Other component types do not show gallery UI');
$adminJs=file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$check(str_contains($adminJs,"mode.value = 'lightbox'")&&str_contains($adminJs,'syncGalleryFields'),'New component instances default to Grid + Lightbox');
$pb=(string)file_get_contents($root.'/public_html/incl/helpers/page_builder_renderer.php');
$check(str_contains($pb,'component_renderer($component, $modifiers)'),'Page Builder passes per-placement settings to Gallery');
$check(str_contains($root.'/core/classes/PageBlocks.php','PageBlocks.php')&&str_contains(file_get_contents($root.'/core/classes/PageBlocks.php'),'galleryDisplaySettings($component, $data)'),'Both component paths normalize gallery settings');
$controller=file_get_contents($root.'/modules/gallery/admin/controllers/GalleryAdminController.php');
$check(str_contains($controller,"'lightbox_enabled' => 1"),'New gallery default preserved for legacy; existing rows untouched');
$theme=file_get_contents($root.'/public_html/assets/scss/components/_components.gallery.scss');
$css=file_get_contents($root.'/public_html/assets/css/style.css');
$check(str_contains($theme,'.gallery--slider.gallery--enhanced')&&str_contains($css,'.gallery--slider.gallery--enhanced'),'Theme owns CSS in both source and compiled stylesheet');
$normalize=(new ReflectionClass(PageBlocks::class))->getMethod('galleryDisplaySettings');
$check($normalize->invoke(null,'gallery:gallery-9',['gallery_mode'=>'slider','gallery_interval'=>8,'gallery_autoplay'=>'1'])===['gallery_mode'=>'slider','gallery_columns'=>3,'gallery_fullscreen'=>'1','gallery_autoplay'=>'1','gallery_interval'=>8],'New autoplay settings persist');
$check($normalize->invoke(null,'gallery:gallery-9',[])===[],'Existing content preserves gallery-wide lightbox setting');
$check($normalize->invoke(null,'blog:posts',['gallery_layout'=>'slider'])===[],'Other component types never store gallery-specific options');
$check($normalize->invoke(null,'gallery:gallery-9',['gallery_layout'=>'unexpected','gallery_lightbox'=>'unexpected'])===['gallery_layout'=>'grid','gallery_lightbox'=>'inherit'],'Untrusted data restricted to known enum values');
$check(str_contains($grid, 'data-gallery-columns="5"') && str_contains($grid, 'data-gallery-viewer="fullscreen"'), 'Grid columns and fullscreen propagated');
$contained=$render(['gallery_mode'=>'lightbox','gallery_fullscreen'=>'0','gallery_columns'=>6]);
$check(str_contains($contained, 'data-gallery-viewer="contained"') && str_contains($contained, 'data-gallery-columns="6"'), 'Contained viewer and six-column option work');
$check(str_contains($js,'visibilitychange') && str_contains($js,'focusin') && str_contains($js,'mouseenter'), 'Autoplay pauses on visibility, focus, and hover');
$check(str_contains($js, "event.key === 'ArrowLeft'") && str_contains($js, 'touchstart'), 'Slider supports keyboard and touch');
$check(!str_contains((string)file_get_contents($root.'/modules/gallery/admin/views/partials/gallery-editor.php'), 'gallery-lightbox-enabled'), 'Gallery admin no longer has display option');
$check(str_contains($galleryLayout, 'data-gallery-legacy') && str_contains($adminJs,'data-gallery-name'), 'Unedited legacy placements keep stored settings');
$check(str_contains($theme,'[data-gallery-columns="6"]') && str_contains($css,'[data-gallery-columns="6"]'), 'Columns defined only in theme SCSS/CSS');
echo "Gallery Workflow i2 QA: {$pass} PASS / {$fail} FAIL\n"; exit($fail>0?1:0);
