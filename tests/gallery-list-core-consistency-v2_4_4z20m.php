<?php

declare(strict_types=1);
/** z20m: Gallery list uses Core icon actions and translations; no data changes. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$pass = 0; $fail = 0;
$check = static function (bool $condition, string $label) use (&$pass, &$fail): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $condition ? ++$pass : ++$fail;
};
$translations = json_decode((string)file_get_contents($root . '/modules/gallery/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$GLOBALS['z20m_translations'] = $translations;
$GLOBALS['z20m_locale'] = 'da';
function t(string $key, array $params = []): string {
    if (str_starts_with($key, 'gallery:')) {
        $key = substr($key, 8);
        return (string)($GLOBALS['z20m_translations'][$key][$GLOBALS['z20m_locale']] ?? '__MISSING_GALLERY_TRANSLATION__');
    }
    return $key;
}
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'gallery-list-test-csrf'; }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_crud_pagination(int $total): array { return ['page'=>1,'pages'=>1,'from'=>$total ? 1:0,'to'=>$total,'offset'=>0]; }
function admin_render_pagination(int $page, int $pages, callable $url, string $label): void {}

$view = $root . '/modules/gallery/admin/views/index.php';
$source = (string)file_get_contents($view);
$galleryCtrl = (string)file_get_contents($root . '/modules/gallery/admin/controllers/GalleryAdminController.php');
$module = (string)file_get_contents($root . '/modules/gallery/GalleryModule.php');
$docs = (string)file_get_contents($root . '/docs/design/CORE-UI-STANDARD-V1.0.md');
$check(substr_count($source, 'AdminListUI::actionGroup(') === 1, 'Gallery list delegates exactly one row action group to Core');
$check(!str_contains($source, '<a class="admin-button admin-button--secondary admin-button--compact"'), 'Legacy text-only Gallery Edit control removed');
$check(str_contains($source, "'icon' => 'edit'") && str_contains($source, "'url' => '/admin/gallerier?screen=edit&id=' . \$id"), 'Core edit icon navigates to exact original edit route');
$check(str_contains($source, "t('gallery:Rediger galleri') . ': ' . (string)\$gallery['name']"), 'Each action describes the affected gallery in its localized accessible label');
$check(str_contains($source, "t('gallery:gallery_page_builder_component')") && !str_contains($source, 'Page Builder-komponent: Galleri'), 'Table subtitle is translated, not literal Danish');
$check(str_contains($source, 'data-admin-live-list') && str_contains($source, 'data-admin-live-search'), 'Existing live search/list hooks preserved');
$check(str_contains($source, 'admin_render_pagination(') && str_contains($source, "'q'=>\$search"), 'Pagination keeps existing search query');
$check(str_contains($galleryCtrl, 'GalleryRepository') && str_contains($module, "'capability' => 'content.manage'"), 'Gallery controller/repository and capability protection remain');
$check(!str_contains($docs, 'Gallery, Medier og øvrige modaler er endnu ikke migreret') && str_contains($docs, 'V2.4.4z15 migrerede Gallery-slettebekræftelsen'), 'Core UI document no longer misstates Gallery/Media modal migration');
foreach (['da', 'en', 'de', 'sv', 'nb'] as $locale) {
    $check(isset($translations['gallery_page_builder_component'][$locale]) && trim($translations['gallery_page_builder_component'][$locale]) !== '', 'Page Builder subtitle translated: ' . $locale);
    $check(isset($translations['Rediger galleri'][$locale]) && trim($translations['Rediger galleri'][$locale]) !== '', 'Specific Edit label translated: ' . $locale);
}
$render = static function (array $records, string $locale = 'da', string $query = '') use ($view): string {
    $GLOBALS['z20m_locale'] = $locale;
    $galleries = $records; $search = $query; $total = count($records);
    $paging = ['page'=>1,'pages'=>1,'from'=>$total ? 1:0,'to'=>$total,'offset'=>0];
    $notice = null;
    ob_start();
    try { require $view; return (string)ob_get_clean(); }
    catch (Throwable $exception) { ob_end_clean(); throw $exception; }
};
$hostile = '<svg onload=alert(1)> & "Galleria"';
$sample = [
    ['id'=>11, 'name'=>$hostile, 'status'=>'active', 'image_count'=>3, 'updated_at'=>'2026-10-18 12:34:56'],
    ['id'=>12, 'name'=>'Andet galleri', 'status'=>'inactive', 'image_count'=>0, 'updated_at'=>null],
];
foreach (['da', 'en', 'de', 'sv', 'nb'] as $locale) {
    $html = $render($sample, $locale, 'test&navn');
    $check(substr_count($html, 'role="group"')===2 && substr_count($html, 'admin-table-actions--icons')===2, "Exactly two Core Gallery action groups: $locale");
    $check(substr_count($html, 'admin-icon-action__icon')===4 && substr_count($html, 'href="/admin/gallerier?screen=edit&amp;id=11"')===2, "Edit and delete SVG actions and safe original edit GET links: $locale");
    $check(str_contains($html, $translations['gallery_page_builder_component'][$locale] . ' · 11'), "Localized subtitle renders alongside unchanged ID: $locale");
    $check(str_contains($html, 'aria-label="' . admin_e($translations['Rediger galleri'][$locale] . ': ' . $hostile) . '"'), "Accessible row-specific Edit label safely escaped: $locale");
    $check(!str_contains($html, '<svg onload=') && !str_contains($html, '&amp;amp;') && str_contains($html, '&lt;svg onload=alert(1)&gt;'), "Hostile gallery names are not executable HTML: $locale");
    $check(str_contains($html, 'name="q" type="search" value="test&amp;navn"') && str_contains($html, 'data-admin-live-list'), "Search and list semantics retained: $locale");
}
$empty = $render([], 'da');
$check(!str_contains($empty, 'role="group"') && str_contains($empty, 'admin-empty-state'), 'Empty gallery list has no fictional row actions');
$check(str_contains($empty, 'gallery') || str_contains($empty, 'galleri'), 'Empty gallery list retains existing create/empty controls');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss')===file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Core SCSS and compiled CSS remain byte-identical');
$version=(string)file_get_contents($root.'/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/", $version, $m) === 1 && version_compare($m[1], '2.4.4.100', '>='), 'Retains minimum z20m version 2.4.4.100');
printf("Gallery Core UI Consistency z20m: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
