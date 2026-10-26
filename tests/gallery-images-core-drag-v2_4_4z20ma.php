<?php

declare(strict_types=1);
/** z20m-a: Gallery image editor alignment, PHP HTML contract and drag behavior guards. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
$translations = json_decode((string)file_get_contents($root . '/modules/gallery/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$GLOBALS['z20ma_locale'] = 'da';
$GLOBALS['z20ma_translations'] = $translations;
function t(string $key): string {
    if (str_starts_with($key, 'gallery:')) {
        return (string)($GLOBALS['z20ma_translations'][substr($key, 8)][$GLOBALS['z20ma_locale']] ?? '__MISSING_TRANSLATION__');
    }
    return $key;
}
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'synthetic-z20ma-token'; }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_icon_action_button(string $name, string $label, array $attributes = [], string $tone = 'neutral'): string { return AdminIcons::actionButton($name, $label, $attributes, $tone); }

$source = (string)file_get_contents($root . '/modules/gallery/admin/views/partials/gallery-editor.php');
$rowSource = (string)file_get_contents($root . '/modules/gallery/admin/views/partials/gallery-image-row.php');
$script = (string)file_get_contents($root . '/modules/gallery/admin/views/partials/gallery-builder-script.php');
$modal = (string)file_get_contents($root . '/modules/gallery/admin/views/partials/media-picker-dialog.php');
$repo = (string)file_get_contents($root . '/modules/gallery/GalleryRepository.php');
$controller = (string)file_get_contents($root . '/modules/gallery/admin/controllers/GalleryAdminController.php');
$css = (string)file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$check(str_contains($source, "admin_icon('plus', 'admin-button__icon')") && str_contains($source, 'class="admin-button" type="button" data-gallery-media-open'), 'Add images uses shared Core primary plus+text button and keeps picker hook');
$check(str_contains($modal, "admin_icon('plus', 'admin-button__icon')") && str_contains($modal, 'data-gallery-media-add'), 'Picker add-selected uses matching Core plus and original hook');
$check(substr_count($source, "require __DIR__ . '/gallery-image-row.php'")===2, 'Existing and newly added rows consume one shared PHP row template');
$check(str_contains($rowSource, "admin_icon('grip'") && str_contains($rowSource, "admin_icon_action_button('delete'"), 'Full card grip SVG and Core danger delete action');
$check(str_contains($rowSource, 'data-gallery-remove') && !str_contains($rowSource, 'data-gallery-up') && !str_contains($rowSource, 'data-gallery-down'), 'No old up/down actions or text remove controls');
$check(!str_contains($script, 'data-gallery-up') && !str_contains($script, 'data-gallery-down'), 'All obsolete up/down click handlers removed');
$check(!str_contains($script, 'article.innerHTML') && str_contains($script, 'cloneNode(true)') && str_contains($script, 'textContent'), 'Dynamic rows clone identical template and assign untrusted data safely');
$check(str_contains($script, 'setDragImage(dragged,') && str_contains($script, "list?.addEventListener('drop'") && str_contains($script, "list?.addEventListener('dragend'"), 'Full-row drag ghost and explicit drop + cleanup');
$dragOverStart = strpos($script, "list?.addEventListener('dragover'");
$dropStart = strpos($script, "list?.addEventListener('drop'");
$check($dragOverStart !== false && $dropStart !== false && !str_contains(substr($script, $dragOverStart, $dropStart-$dragOverStart), 'list.insertBefore('), 'No DOM mutation during dragover (in-place drag jitter eliminated)');
$check(str_contains($script, "list?.addEventListener('keydown'") && str_contains($script, "event.altKey") && str_contains($script, "'ArrowUp', 'ArrowDown'"), 'Keyboard Alt+arrows alternative for reorder');
$check(str_contains($source, 'data-gallery-sort-status') && str_contains($script, 'announce(labels.moved)'), 'Screen reader notified of moves');
$check(str_contains($source, 'name="media_ids[]"') || str_contains($rowSource, 'name="media_ids[]"'), 'Original ordered media_ids[] POST format remains');
$check(str_contains($repo, "function saveGallery(") && str_contains($controller, "saveGallery(\$id, \$gallery, \$mediaIds)"), 'Gallery save repository/controller unchanged');
$check(str_contains($controller, 'admin_verify_csrf('), 'Controller retains server-side CSRF protection');
$check(str_contains($css, '.admin-builder-block--media[data-gallery-drop="before"]') && str_contains($css, '.admin-builder-block--media .admin-builder-drag__icon'), 'Core stylesheet owns drop indicator and grip icon styling');
$check($css === file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS byte-identical');
foreach (['da','en','de','sv','nb'] as $locale) {
    foreach (['gallery_move_keyboard','gallery_remove_image','gallery_remove_confirm','gallery_moved_feedback','gallery_removed_feedback'] as $key) {
        $check(isset($translations[$key][$locale]) && trim($translations[$key][$locale]) !== '', "Translation $key: $locale");
    }
}
$editor = $root . '/modules/gallery/admin/views/partials/gallery-editor.php';
$render = static function(array $rows, string $locale) use ($editor): string {
    $GLOBALS['z20ma_locale'] = $locale;
    $galleryData = ['name'=>'Real test gallery', 'status'=>'active'];
    $items = $rows; $isEdit = true; $galleryId = 42; $errors = []; $usage = ['count'=>0, 'page_ids'=>[]];
    ob_start();
    try { require $editor; return (string)ob_get_clean(); }
    catch (Throwable $e) { ob_end_clean(); throw $e; }
};
$rows = [
 ['media_id'=>17, 'public_url'=>'/uploads/a.png', 'title'=>'<img src=x onerror=alert(1)> & hello', 'original_filename'=>'file-a.png'],
 ['media_id'=>20, 'public_url'=>'/uploads/b.png', 'title'=>'Second', 'original_filename'=>'file-b.png'],
];
foreach (['da','en','de','sv','nb'] as $locale) {
    $html = $render($rows,$locale);
    $check(substr_count($html, 'name="media_ids[]" value="17"')===1 && substr_count($html,'name="media_ids[]" value="20"')===1, "Saved image IDs retained exactly once: $locale");
    $check(strpos($html,'value="17"') < strpos($html,'value="20"'), "Image storage order preserved: $locale");
    $check(str_contains($html, '<template data-gallery-item-template>') && str_contains($html, 'data-media-id="0"'), "New row template valid: $locale");
    $check(preg_match_all('/<button[^>]*data-gallery-remove=""/', $html)===3 && preg_match_all('/<button[^>]*data-gallery-drag/', $html)===3, "Each row and template has grip + remove action: $locale");
    $check(str_contains($html,'aria-label="' . admin_e($translations['gallery_remove_image'][$locale] . ': ' . $rows[0]['title']) . '"'), "Localized and row-specific Remove accessible label: $locale");
    $check(!str_contains($html,'<img src=x onerror') && str_contains($html,'&lt;img src=x onerror=alert(1)&gt;'), "Malicious image title escaped: $locale");
    $check(!str_contains($html,'data-gallery-up') && !str_contains($html,'data-gallery-down') && !str_contains($html, '>↑</button>'), "Obsolete arrows completely gone: $locale");
    $check(str_contains($html, 'data-gallery-media-open') && str_contains($html, 'admin-button__icon'), "Localized add button and Core icon rendered: $locale");
    $check(str_contains($html,'name="csrf_token" value="synthetic-z20ma-token"') && str_contains($html,'name="gallery_action" value="save"'), "Existing CSRF and save action retained: $locale");
}
$empty = $render([], 'da');
$check(str_contains($empty, 'data-gallery-empty') && str_contains($empty, 'data-gallery-item-template') && substr_count($empty, 'name="media_ids[]" value="0"')===1, 'Empty gallery renders only hidden template row and original empty state');
$check(str_contains($empty, 'data-gallery-media-add') && str_contains($empty, 'data-gallery-media-grid'), 'Empty gallery retains working image picker');
$version = file_get_contents($root . '/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/", (string)$version, $versionMatch) === 1
    && version_compare($versionMatch[1], '2.4.4.101', '>='), 'Retains minimum z20m-a version 2.4.4.101');
printf("Gallery Images UI Alignment z20m-a: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail ? 1 : 0);
