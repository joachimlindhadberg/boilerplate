<?php
/** Read-only UI regression; no database or server changes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/admin/incl/page-builder-functions.php';
function t(string $key): string { return $key; }
function admin_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
$pass = 0; $fail = 0;
$check = static function (bool $result, string $name) use (&$pass, &$fail): void {
    echo ($result ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
    $result ? $pass++ : $fail++;
};
$slider = admin_page_builder_gallery_fields('blocks[0]', ['component'=>'gallery:gallery-9','gallery_mode'=>'slider','gallery_autoplay'=>'1']);
$check(str_contains($slider,'admin-gallery-settings'), 'Gallery Page Builder has scoped Core-owned styling');
$check(substr_count($slider,'class="admin-toggle"') === 2, 'Fullscreen and autoplay reuse Core Toggle');
$check(str_contains($slider, 'admin-toggle__input'), 'Controls use accessible native checkboxes');
$check(str_contains($slider, 'data-gallery-autoplay="1"'), 'Autoplay behavior data hook preserved');
$check(str_contains($slider, '[data][gallery_autoplay]') && str_contains($slider,'[data][gallery_fullscreen]'), 'Saved form payload names preserved');
$check(str_contains($slider,'data-gallery-slider-settings') && str_contains($slider,'data-gallery-grid-settings hidden'), 'Only the selected mode is initially visible');
$grid = admin_page_builder_gallery_fields('blocks[1]',['component'=>'gallery:gallery-9','gallery_mode'=>'lightbox']);
$check(str_contains($grid,'data-gallery-slider-settings hidden') && !str_contains($grid, 'data-gallery-grid-settings hidden') && str_contains($grid, 'data-gallery-grid-settings'), 'Grid default shows only Grid fields');
$check(!str_contains($slider,'<label><input type="checkbox"'), 'Ad-hoc checkbox styling removed');
$icons = file_get_contents($root . '/core/classes/AdminIcons.php');
foreach (['chevron','close','play','pause'] as $icon) {
    $check(str_contains($icons, "'{$icon}' =>") && str_contains(AdminIcons::render($icon), '<svg'), "{$icon} comes from Core SVG registry");
}
$gallery = file_get_contents($root . '/modules/gallery/GalleryModule.php');
$check(str_contains($gallery, "AdminIcons::render('chevron')") && str_contains($gallery, "AdminIcons::render('pause')"), 'Slider navigation and playback icons rendered via Core');
$check(str_contains($gallery, 'data-gallery-viewer-icons') && str_contains($gallery,"AdminIcons::render(\$icon)"), 'Lightbox receives inert Core SVG templates');
$lightbox = file_get_contents($root . '/modules/gallery/assets/gallery-lightbox.js');
$check(str_contains($lightbox, 'icon.cloneNode(true)') && !str_contains($lightbox, '&times;') && !str_contains($lightbox, '&#8250;'), 'Viewer clones icons, no Unicode glyph buttons');
$sliderJs = file_get_contents($root . '/modules/gallery/assets/gallery-slider.js');
$check(str_contains($sliderJs, 'pauseIcon.hidden = !playing') && !str_contains($sliderJs,'play.textContent ='), 'Play/pause updates icon state without destroying SVG');
$theme = file_get_contents($root . '/public_html/assets/scss/components/_components.gallery.scss');
$compiled = file_get_contents($root . '/public_html/assets/css/style.css');
$check(str_contains($theme,'.gallery__previous { position:absolute;') && str_contains($compiled,'.gallery__previous { position:absolute;'), 'Theme owns overlay navigation in SCSS and CSS');
$check(str_contains($theme,'.gallery-lightbox__image { grid-column:2;') && str_contains($compiled,'.gallery-lightbox__image { grid-column:2;'), 'Single-image Lightbox stays centered');
$adminCss = file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$adminScss = file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$check($adminCss === $adminScss && str_contains($adminCss,'.admin-gallery-settings[hidden]'), 'Admin Core styling synchronized and hidden safe');
$check(!str_contains($gallery, '<style') && !str_contains($gallery, 'style="'), 'Gallery module retains frontend CSS ownership contract');
echo "Gallery UI 4A.6.9i3: {$pass} PASS / {$fail} FAIL" . PHP_EOL;
exit($fail ? 1 : 0);
