<?php
/** V2.4.4y5a: contextual chevrons and no phantom whitespace in empty columns. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/functions.php';
if (!function_exists('t')) { function t(string $key): string { return $key; } }
$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    $ok ? ++$pass : ++$fail;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
};
$section = admin_page_builder_block_editor(['type'=>'text','data'=>['title'=>'Test content']], '0');
$check(str_contains($section, 'data-page-row-toggle'), 'Section keeps editor toggle');
$check(str_contains($section, 'aria-expanded="false"'), 'Section toggle starts collapsed');
$check(str_contains($section, 'm6 9 6 6 6-6'), 'Section toggle uses Core down chevron');
$grid = admin_page_builder_block_editor(['type'=>'grid','data'=>['layout_version'=>'v2','column_count'=>2,'preset'=>'equal','columns'=>[
  ['blocks'=>[['type'=>'text','data'=>['title'=>'Kept','body'=>'Text']]]], ['blocks'=>[]]
]]], '0');
$check(str_contains($grid, 'data-page-child-toggle'), 'Grid child has editor toggle');
$check(str_contains($grid, 'data-grid-child-remove'), 'Grid child retains delete action');
$check(str_contains($grid, 'm6 9 6 6 6-6'), 'Grid child toggle uses down chevron');
$check(substr_count($grid, 'data-grid-child-list') === 2, 'Two grid-column lists preserved');
$check(substr_count($grid, 'data-grid-child-add') === 2, 'Both columns retain add-action markup');
$css = (string) file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$scss = (string) file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$check($scss === $css, 'Core compiled CSS and SCSS synchronized');
$check(str_contains($css, '.admin-page-row--v2-grid .admin-grid-child-list:not(:has(> [data-grid-child])) { padding:0; }'), 'Only empty V2 grids remove padding');
$check(str_contains($css, '.admin-grid-child-list { display: grid; min-height: 46px; gap: 8px; padding: 8px; }'), 'Legacy grid padding remains unchanged');
$check(str_contains($css, '[data-page-child-toggle][aria-expanded="true"]'), 'Expanded child rotates chevron');
$js = (string) file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$check(str_contains($js, 'syncBlockToggle(childToggle, !fields.hidden)'), 'Chevron title, label and ARIA update after click');
$check(str_contains($js, 'syncBlockToggle(toggle, true)'), 'Auto-opened new blocks use expanded state');
$check(str_contains($js, 'dragTargetGridList(event.target)'), 'Drop targets resolve empty columns after spacing fix');
$check(str_contains($js, "column.querySelector('[data-grid-child-list]')"), 'Drop logic resolves real list in empty column');
$check(str_contains($js, 'event.altKey'), 'Keyboard movement remains available');
$translations = json_decode((string)file_get_contents($root.'/core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['page_builder_v2.expand_block','page_builder_v2.collapse_block'] as $key) {
  foreach (['da','en','de','sv','nb'] as $lang) $check(!empty($translations[$key][$lang]), "$key $lang translated");
}
$bootstrap = (string)file_get_contents($root.'/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", $bootstrap, $releaseVersion) === 1
    && version_compare($releaseVersion[1], '2.4.4.43', '>='), 'Patch version remains at least V2.4.4y5a');
echo "Summary V2.4.4y5a: $pass PASS / $fail FAIL\n";
exit($fail === 0 ? 0 : 1);
