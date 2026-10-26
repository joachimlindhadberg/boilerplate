<?php
/** V2.4.4y4a: Page Builder drag preview and section status in settings. */
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root.'/core/classes/Autoload.php';
require_once $root.'/public_html/admin/incl/functions.php';
if (!function_exists('t')) { function t(string $key): string { return $key; } }
require_once $root.'/public_html/admin/incl/page-builder-functions.php';
require_once $root.'/public_html/incl/helpers/page_builder_renderer.php';
$pass=0;$fail=0;
$check=static function(bool $ok, string $name) use (&$pass,&$fail){ if($ok){++$pass;echo "PASS $name\n";}else{++$fail;echo "FAIL $name\n";} };
foreach (['hero','intro','text','text_image','cta','component','grid'] as $type) {
    $data = $type==='grid' ? ['column_count'=>3,'preset'=>'equal','layout_version'=>'v2'] : ($type==='hero' ? ['title'=>'Welcome'] : []);
    foreach (['active','hidden'] as $status) {
        $editor=admin_page_builder_block_editor(['type'=>$type,'status'=>$status,'data'=>$data],0);
        $check((bool)preg_match('/<input type="hidden" name="blocks\[0\]\[status\]" value="'.preg_quote($status,'/').'" data-builder-name="status" data-builder-section-status>/', $editor), "$type preserves $status status field");
        $check(substr_count($editor,'data-page-section-hidden-label')===1, "$type has one hidden marker");
        $check(!str_contains($editor,'class="admin-builder-visibility"'), "$type has no header status dropdown");
        $normalized=PageBlocks::normalizeSubmitted([['type'=>$type,'status'=>$status,'data'=>$data]]);
        $check(($type==='component' || $normalized['errors']===[]) && ($normalized['blocks'][0]['status']??null)===$status, "$type $status survives normalization");
    }
}
$js=file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$modal=file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$check(str_contains($modal,'data-page-section-visibility') && str_contains($modal,'admin_toggle_switch'), 'Section settings uses Core visibility toggle');
$check(str_contains($js,"const nextStatus = sectionVisibility.checked ? 'active' : 'hidden';"),'JS maps toggle to active/hidden');
$check(str_contains($js,"sectionVisibility.checked = statusInput.value !== 'hidden';"), 'Status toggle initializes from selected section');
$check(str_contains($js,'hiddenLabel.hidden = nextStatus !=='), 'Status change updates hidden marker');
$check(str_contains($js,'event.dataTransfer.setDragImage(draggedBlock,'),'Top-level drag preview is the full section');
$check(str_contains($js,'event.dataTransfer.setDragImage(draggedGridChild,'),'Nested block preview is the full block');
$check(!str_contains($modal,'name="blocks[0][status]"'), 'Settings modal has no submit field name of its own');
$check(preg_match("~BOILERPLATE_VERSION', '([0-9.]+)'~", file_get_contents($root.'/public_html/incl/init.php'), $ver) === 1 && version_compare($ver[1], '2.4.4.39', '>='), 'Version incremented');
echo "SUMMARY: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
