<?php
/** DB-free full edit-view fixture for responsive Core layout/browser verification. */
declare(strict_types=1);
require_once __DIR__ . '/../../core/classes/AdminEscaper.php';
require_once __DIR__ . '/../../core/classes/AdminIcons.php';
require_once __DIR__ . '/../../core/classes/AdminDeleteDialog.php';
class Translator { public static function locale(): string { return 'da'; } public static function defaultLocale(): string { return 'da'; } }
function t(string $key): string {
    static $strings = null;
    $strings ??= json_decode((string)file_get_contents(__DIR__ . '/../../core/lang/translations.json'), true);
    return (string)($strings[$key]['da'] ?? $key);
}
function admin_e(mixed $v): string { return AdminEscaper::text($v); }
function admin_icon(string $icon, string $class = ''): string { return AdminIcons::render($icon, $class); }
function admin_icon_action_button(string $icon, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($icon, $label, $attributes, $tone); }
function admin_csrf_token(): string { return 'fixture-csrf'; }
function admin_nav_items(array $badges = []): array { return []; }
function admin_nav_badges(): array { return []; }
function admin_user_role_label(string $role): string { return 'Administrator'; }
function admin_asset(string $path): string { return $path; }
$user = ['firstname' => 'Test', 'lastname' => 'Admin', 'role' => 'admin'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/navigation?mode=edit&id=1';
$mode = 'edit';
$activeNav = 'navigation';
$placement = (isset($argv[1]) && $argv[1] === 'assigned') ? 'header' : 'none';
$selectedMenu = ['id' => 1, 'name' => 'Hovedmenu'];
$items = [
    ['id' => '1', 'parent_id' => null, 'type'=>'page', 'page_id'=>1, 'label'=>'Forside', 'page_title'=>'Forside', 'is_visible'=>1],
    ['id' => '2', 'parent_id' => '1', 'type'=>'page', 'page_id'=>2, 'label'=>'Kontrolrum', 'page_title'=>'Kontrolrum', 'is_visible'=>1],
    ['id' => '3', 'parent_id' => null, 'type'=>'page', 'page_id'=>3, 'label'=>'Studiet', 'page_title'=>'Studiet', 'is_visible'=>1],
    ['id' => '4', 'parent_id' => null, 'type'=>'page', 'page_id'=>4, 'label'=>'Kontakt', 'page_title'=>'Kontakt', 'is_visible'=>1],
];
$pages = [['id'=>1,'page_title'=>'Forside','page_status'=>'published'],['id'=>2,'page_title'=>'Kontrolrum','page_status'=>'published'],['id'=>3,'page_title'=>'Studiet','page_status'=>'published'],['id'=>4,'page_title'=>'Kontakt','page_status'=>'published']];
ob_start();
require __DIR__ . '/../../public_html/admin/views/navigation/edit.php';
$html=(string)ob_get_clean();
$css=(string)file_get_contents(__DIR__ . '/../../public_html/admin/assets/css/admin.css');
$js=(string)file_get_contents(__DIR__ . '/../../public_html/admin/assets/js/admin.js');
$html = str_replace('<link rel="stylesheet" href="/admin/assets/css/admin.css">', '<style>' . $css . '</style>', $html);
$html = preg_replace('~<link[^>]+(?:fonts\.googleapis|fonts\.gstatic)[^>]*>~', '', $html);
$html = str_replace('<script src="/admin/assets/js/admin.js" defer></script>', '', $html);
$html = str_replace('<script src="/admin/assets/js/admin-live-search.js" defer></script>', '', $html);
$html = str_replace('</body>', '<script>' . $js . '</script></body>', $html);
echo $html;
