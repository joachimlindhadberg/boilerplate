<?php
declare(strict_types=1);
/** No database or real CMS is touched. */
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__);
require_once $root.'/core/classes/Autoload.php';
class GalleryPlacementManagerI2 {
  public array $seen=[];
  public function componentOptions():array {return ['gallery:gallery-9'=>'Gallery'];}
  public function renderComponent(string $name,array $modifiers=[]):bool { $this->seen[]=[$name,$modifiers];echo '<div data-gallery-tested></div>';return true;}
}
$GLOBALS['gallery_placement_manager_i2']=new GalleryPlacementManagerI2();
function cms_module_manager():GalleryPlacementManagerI2 {return $GLOBALS['gallery_placement_manager_i2'];}
function component_renderer(string $component,array $modifiers=[]):void {cms_module_manager()->renderComponent($component,$modifiers);}
require_once $root.'/public_html/incl/helpers/page_builder_renderer.php';
$pass=0;$fail=0;
$check=function(bool $ok,string $label)use(&$pass,&$fail){echo($ok?'PASS':'FAIL').': '.$label."\n";$ok?$pass++:$fail++;};
$data=['component'=>'gallery:gallery-9','gallery_mode'=>'slider','gallery_columns'=>6,'gallery_fullscreen'=>'0','gallery_autoplay'=>'1','gallery_interval'=>7];
ob_start();render_page_builder_block(['block_type'=>'component','data'=>$data]);$html=(string)ob_get_clean();
$seen=cms_module_manager()->seen;
$check(count($seen)===1 && $seen[0][1]===array_diff_key($data,['component'=>1]),'Top-level placement forwards new settings');
$check(str_contains($html,'data-gallery-tested'),'Top-level gallery markup included');
$child=['type'=>'component','data'=>['component'=>'gallery:gallery-9','gallery_mode'=>'lightbox','gallery_columns'=>5]];
ob_start();render_page_builder_grid_child($child);$html=(string)ob_get_clean();
$check(cms_module_manager()->seen[1][1]===['gallery_mode'=>'lightbox','gallery_columns'=>5],'Grid-child placement forwards independent options');
$check(str_contains($html,'data-gallery-tested'),'Grid child output included');
ob_start();render_page_builder_block(['block_type'=>'component','data'=>['component'=>'gallery:gallery-9']]);ob_end_clean();
$check(cms_module_manager()->seen[2][1]===[],'Legacy placement forwards no new defaults');
ob_start();render_page_builder_block(['block_type'=>'component','data'=>['component'=>'gallery:gallery-9','gallery_layout'=>'grid','gallery_lightbox'=>'off']]);ob_end_clean();
$check(cms_module_manager()->seen[3][1]===['gallery_layout'=>'grid','gallery_lightbox'=>'off'],'Intermediate legacy i/i1 setting preserved');
$normalizer=(new ReflectionClass(PageBlocks::class))->getMethod('galleryDisplaySettings');
$check($normalizer->invoke(null,'gallery:gallery-9', $data)===['gallery_mode'=>'slider','gallery_columns'=>6,'gallery_fullscreen'=>'0','gallery_autoplay'=>'1','gallery_interval'=>7],'PageBlocks accepts validated options');
$check($normalizer->invoke(null,'gallery:gallery-9', ['gallery_mode'=>'slider','gallery_columns'=>999,'gallery_interval'=>-1])['gallery_columns']===6,'Server clamps gallery columns');
$check($normalizer->invoke(null,'gallery:gallery-9', ['gallery_mode'=>'slider','gallery_interval'=>-1])['gallery_interval']===3,'Server clamps autoplay interval');
$check($normalizer->invoke(null,'other:thing',$data)===[],'Non-gallery components discard settings');
echo "Gallery Builder i2: {$pass} PASS / {$fail} FAIL\n";exit($fail?1:0);
