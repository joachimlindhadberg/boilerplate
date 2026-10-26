<?php
/** Local fixture generator; does not use DB or alter site data. */
declare(strict_types=1);
require_once __DIR__ . '/../../core/classes/AdminEscaper.php';
require_once __DIR__ . '/../../core/classes/AdminIcons.php';
function t(string $key): string {
    static $strings = null;
    $strings ??= json_decode((string)file_get_contents(__DIR__ . '/../../core/lang/translations.json'), true);
    return (string)($strings[$key]['da'] ?? $key);
}
function admin_e(mixed $v): string { return AdminEscaper::text($v); }
function admin_icon(string $icon, string $class = ''): string { return AdminIcons::render($icon, $class); }
function admin_icon_action_button(string $icon, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($icon, $label, $attributes, $tone); }
function admin_csrf_token(): string { return 'fixture-csrf'; }
$_SERVER['REQUEST_METHOD'] = 'GET';
$mode = 'edit';
$items = [
    ['id' => '1', 'parent_id' => null, 'type'=>'page', 'page_id'=>1, 'label'=>'Forside', 'page_title'=>'Forside', 'is_visible'=>1],
    ['id' => '2', 'parent_id' => 1, 'type'=>'page', 'page_id'=>2, 'label'=>'Underside', 'page_title'=>'Underside', 'is_visible'=>1],
    ['id' => '3', 'parent_id' => null, 'type'=>'page', 'page_id'=>3, 'label'=>'Studiet', 'page_title'=>'Studiet', 'is_visible'=>1],
    ['id' => '4', 'parent_id' => null, 'type'=>'page', 'page_id'=>4, 'label'=>'Kontakt', 'page_title'=>'Kontakt', 'is_visible'=>1],
];
$pages = [['id'=>1,'page_title'=>'Forside','page_status'=>'published'],['id'=>2,'page_title'=>'Underside','page_status'=>'published'],['id'=>3,'page_title'=>'Studiet','page_status'=>'published'],['id'=>4,'page_title'=>'Kontakt','page_status'=>'published']];
$selectedMenu = ['id'=>1,'name'=>'Fixture'];
require __DIR__ . '/../../public_html/admin/views/navigation/partials/context.php';
ob_start(); foreach ($renderedItems as $item) $renderItem($item); $rows=ob_get_clean();
ob_start(); $renderItem(['id'=>'__KEY__','type'=>'page','page_id'=>0,'label'=>'','url'=>'','parent_id'=>'0','is_visible'=>1,'target'=>'_self']); $template=ob_get_clean();
$dictionary = json_decode((string)file_get_contents(__DIR__ . '/../../core/lang/translations.json'), true);
$msg=[]; foreach($dictionary as $k=>$trans) $msg[$k]=(string)($trans['da']??$k);
$root=dirname(__DIR__, 2);
$style = file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$js = file_get_contents($root . '/public_html/admin/assets/js/admin.js');
?><!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style><?= $style ?></style><script>window.CMS_ADMIN_MESSAGES = <?= json_encode($msg,JSON_HEX_TAG|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) ?>; window.cmsTranslate = key=>window.CMS_ADMIN_MESSAGES[key]||key;</script></head>
<body class="nf-admin" style="padding:20px;max-width:960px;margin:auto"><main id="admin-main"><form data-nav-editor data-admin-track-changes><section class="admin-panel admin-nav-editor__main"><div class="admin-panel__header"><h2>Menupunkter</h2><button data-nav-add type="button" hidden>Tilføj menupunkt</button></div><div class="admin-nav-editor__list-wrap"><ul class="admin-nav-editor__list" data-nav-list><?= $rows ?></ul><p class="sr-only" data-nav-move-status aria-live="polite" role="status"></p><div data-nav-empty hidden>Tom</div></div></section></form><template data-nav-template><?= $template ?></template></main><script><?= $js ?></script></body></html>
