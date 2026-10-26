<?php

declare(strict_types=1);
/** Safe regression: fake database, synthetic HTML, no live DB, no HTTP. */
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/modules/gallery/GalleryModule.php';
$passed = 0; $failed = 0;
$check = static function (bool $ok, string $label) use (&$passed,&$failed): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $ok ? ++$passed : ++$failed;
};
$catalog = json_decode((string)file_get_contents($root . '/modules/gallery/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$GLOBALS['gallery_hotfix_translations'] = $catalog;
function t(string $key): string {
    $slug = str_starts_with($key, 'gallery:') ? substr($key, 8) : $key;
    return $GLOBALS['gallery_hotfix_translations'][$slug]['da'] ?? $slug;
}
function admin_e(mixed $x): string { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
function admin_csrf_token(): string { return 'fake-gallery-csrf'; }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_crud_pagination(int $total): array { return ['page'=>1,'pages'=>1,'from'=>$total ? 1:0,'to'=>$total,'offset'=>0]; }
function admin_render_pagination(int $page, int $pages, callable $url, string $label): void {}
function admin_icon_action_button(string $icon, string $label, array $attrs = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($icon,$label,$attrs,$tone); }

class GalleryHotfixDb extends Db {
    public array $gallery = [];
    public array $statements = [];
    public int $columnCount = 0;
    public function __construct() {}
    public function fetchValue(string $sql, array $params = []) { return $this->columnCount; }
    public function query(string $sql, array $params = []): void { $this->statements[] = [$sql, $params]; }
    public function fetchArray(string $sql, array $params = []): array {
        if (str_contains($sql, 'FROM module_galleries WHERE id')) {
            $id = (int)($params[0] ?? 0);
            return isset($this->gallery[$id]) ? [$this->gallery[$id]] : [];
        }
        if (str_contains($sql, 'FROM module_gallery_items')) return [
            ['file_path'=>'/uploads/demo-1.jpg', 'alt_text'=>'Alpha & <img>', 'width'=>1000, 'height'=>700],
            ['file_path'=>'/uploads/demo-2.jpg', 'alt_text'=>'', 'width'=>0, 'height'=>0],
        ];
        return [];
    }
}
class GalleryHotfixMedia extends MediaLibrary {
    public function __construct() {}
    public function publicUrl(string $path): string { return $path; }
}
$db = new GalleryHotfixDb();
$db->gallery[8] = ['id'=>8,'name'=>'Our < Gallery','status'=>'active','lightbox_enabled'=>1];
$db->gallery[9] = ['id'=>9,'name'=>'Ordinary Gallery','status'=>'active','lightbox_enabled'=>0];
$repo = new GalleryRepository($db);
$module = new GalleryModule();
foreach (['repository'=>$repo,'media'=>new GalleryHotfixMedia()] as $field => $value) {
    $property = (new ReflectionClass(GalleryModule::class))->getProperty($field);
    $property->setValue($module, $value);
}
$render = static function (int $id) use ($module): string {
    ob_start(); $ok = $module->renderComponent('gallery-' . $id); $html = (string)ob_get_clean();
    if (!$ok) throw new RuntimeException('Gallery component was not recognized');
    return $html;
};
$html = $render(8);
$check(str_contains($html, 'gallery--lightbox') && substr_count($html, 'data-gallery-lightbox-image') === 2, 'Enabled gallery renders two real lightbox links');
$check(str_contains($html, 'href="/uploads/demo-1.jpg"') && str_contains($html, '<img src="/uploads/demo-1.jpg"'), 'Anchor fallback uses full original media URL');
$check(str_contains($html, 'Alpha &amp; &lt;img&gt;') && !str_contains($html, '<img>'), 'Alt and aria content safely escaped');
$check(str_contains($html, 'data-gallery-label-close=') && str_contains($html, 'data-gallery-label-next='), 'Lightbox controls are localized in inert data attributes');
$plain = $render(9);
$check(!str_contains($plain, 'gallery--lightbox') && !str_contains($plain, '<a ') && str_contains($plain,'<img '), 'Existing galleries remain unchanged when lightbox disabled');
$check($module->frontendScripts() === ['gallery-lightbox.js', 'gallery-slider.js'] && $module instanceof ModuleFrontendScriptProviderInterface, 'Lightbox script registered through module Core contract');
$check(is_file($root.'/modules/gallery/assets/gallery-lightbox.js'), 'Actual JS asset exists');
$js = (string)file_get_contents($root.'/modules/gallery/assets/gallery-lightbox.js');
$check(str_contains($js, 'event.key === \'ArrowLeft\'') && str_contains($js,'event.key === \'ArrowRight\''), 'Keyboard previous/next implemented');
$check(str_contains($js,"dialog.addEventListener('close'") && str_contains($js, 'returnFocus.focus'), 'Dialog close restores focus');
$check(str_contains($js, 'event.ctrlKey') && str_contains($js,'event.metaKey'), 'Modified link clicks remain browser-native');
$check(str_contains($js,'textContent = description') && !str_contains($js, 'innerHTML = description'), 'Captions are inert text');
$check(str_contains((string)file_get_contents($root.'/public_html/assets/scss/components/_components.gallery.scss'), '.gallery-lightbox::backdrop')
    && str_contains((string)file_get_contents($root.'/public_html/assets/css/style.css'), '.gallery-lightbox::backdrop'), 'Frontend SCSS and CSS include same Lightbox presentation');
$manifest = json_decode((string)file_get_contents($root.'/modules/gallery/module.json'),true);
$check(version_compare((string)($manifest['version'] ?? '0'), '1.0.16', '>='), 'Gallery module version advanced for explicit update');
$migration = require $root.'/modules/gallery/migrations/20261019_100_gallery_lightbox.php';
$db->columnCount = 0; $migration($db);
$check(count($db->statements) === 1 && str_contains($db->statements[0][0], 'ADD COLUMN lightbox_enabled'), 'Migration adds missing option exactly once');
$db->columnCount = 1; $migration($db);
$check(count($db->statements) === 1, 'Migration is idempotent and restart-safe');
$check(substr_count((string)file_get_contents($root.'/database/install/empty-schema.sql'), '`lightbox_enabled`')===1, 'New installs include the Gallery option directly');
$check(str_contains((string)file_get_contents($root.'/core/classes/FirstInstall.php'), "'module:gallery:20261019_100_gallery_lightbox'"), 'Fresh installer reconciles new migration ID');
foreach (['da','en','de','sv','nb'] as $lang) {
    foreach (['Vis billeder i lightbox','Klik på et billede for at åbne det i en enkel fuldskærmsvisning.','Åbn billede','Luk billedvisning','Forrige billede','Næste billede','Billedvisning'] as $key) {
        $check(isset($catalog[$key][$lang]) && trim((string)$catalog[$key][$lang]) !== '', "Translation {$lang}: {$key}");
    }
}
$list = $root.'/modules/gallery/admin/views/index.php';
$galleries=[['id'=>8,'name'=>'Danger <svg onload=alert(1)>','status'=>'active','image_count'=>2,'updated_at'=>null]];
$search=''; $total=1; $paging=['page'=>1,'pages'=>1,'from'=>1,'to'=>1]; $notice=null;
ob_start(); require $list; $listHtml = (string)ob_get_clean();
$check(substr_count($listHtml, 'data-gallery-list-delete-id=')===1 && str_contains($listHtml, 'data-gallery-list-delete-dialog')===false, 'Gallery row has exactly one Core Delete trigger');
$check(str_contains($listHtml, 'id="gallery-list-delete-dialog"') && str_contains($listHtml, 'data-gallery-list-delete-target'), 'Shared Core modal outside list has an inert target');
$check(str_contains($listHtml, 'method="post" action="/admin/gallerier?screen=edit"') && str_contains($listHtml,'name="gallery_action" value="delete"'), 'List delete uses unchanged POST/CSRF controller route');
$check(str_contains($listHtml,'name="csrf_token" value="fake-gallery-csrf"'), 'List confirmation has server-issued CSRF token');
$check(!str_contains($listHtml,'<svg onload=') && str_contains($listHtml,'&lt;svg onload=alert(1)&gt;'), 'Hostile gallery name never becomes executable markup');
$check(str_contains((string)file_get_contents($root.'/public_html/admin/assets/js/admin.js'), "if (dialog.id === 'gallery-list-delete-dialog')") && str_contains((string)file_get_contents($root.'/public_html/admin/assets/js/admin.js'),'button.dataset.galleryListDeleteId'), 'Core delegated admin JS supports dynamically refreshed Gallery lists');
$editor=(string)file_get_contents($root.'/modules/gallery/admin/views/partials/gallery-editor.php');
$check(str_contains($editor, 'admin-panel admin-danger-zone') && str_contains($editor, 'admin-button admin-button--danger'), 'Gallery Edit Delete uses the shared Pages-style danger zone');
$check(!str_contains($editor, 'admin-form-actions--left') && !str_contains($editor, 'admin-button--danger-outline'), 'Old loose Gallery Edit delete action removed');
$check(!str_contains($editor, 'gallery-lightbox-enabled') && !str_contains($editor, "'name' => 'lightbox_enabled'"), 'Gallery editor no longer exposes global Lightbox option');
$modSource=(string)file_get_contents($root.'/modules/gallery/GalleryModule.php');
$check(str_contains((string)file_get_contents($root.'/modules/gallery/admin/controllers/GalleryAdminController.php'), "\$existing['lightbox_enabled'] ?? 1"), 'Gallery POST preserves old lightbox state while new galleries default on');
$check(str_contains((string)file_get_contents($root.'/modules/gallery/GalleryRepository.php'), 'lightbox_enabled = ?'), 'Repository stores selection as parameterized SQL');
$check(str_contains((string)file_get_contents($root.'/modules/gallery/GalleryRepository.php'), "if (\$usage['count'] > 0)"), 'Page Builder usage still protects Gallery deletion');
printf("Gallery Delete + Lightbox hotfix: %d PASS / %d FAIL\n",$passed,$failed);
exit($failed ? 1 : 0);
