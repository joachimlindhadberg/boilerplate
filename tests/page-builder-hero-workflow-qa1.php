<?php

declare(strict_types=1);
/** Read-only QA: dedicated Hero workflow, preserved field names and Core translations. */
$root = dirname(__DIR__);
$tests = 0;
$failures = 0;
$check = static function (bool $condition, string $label) use (&$tests, &$failures): void {
    ++$tests;
    if (!$condition) { ++$failures; fwrite(STDERR, "FAIL: {$label}\n"); }
};
$read = static fn(string $name): string => (string)file_get_contents($root . '/' . $name);
$view = $read('public_html/admin/views/pages/partials/form-fields.php');
$php = $read('public_html/admin/incl/page-builder-functions.php');
$js = $read('public_html/admin/assets/js/admin.js');
$css = $read('public_html/admin/assets/css/admin.css');
$scss = $read('public_html/admin/assets/scss/admin.scss');
$translations = json_decode($read('core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);

$check($scss === $css, 'Compiled Core admin CSS matches the authoritative SCSS');
$check(str_contains($css, '[data-page-hero-toggle][aria-expanded="true"] .admin-icon-action__icon'), 'Hero chevron uses the same expanded-state rotation as other blocks');
$check(str_contains($css, '[data-page-child-toggle][aria-expanded="true"] .admin-icon-action__icon'), 'Existing child chevron rotation preserved');
$check(str_contains($js, 'syncBlockToggle(heroToggle, !fields.hidden)'), 'Hero toggler update accessible expanded state');
$check(str_contains($js, 'syncBlockToggle(childToggle, !fields.hidden)'), 'Existing child toggle behavior preserved');
$check(str_contains($js, 'const syncDedicatedHeroMedia = (field) =>'), 'Dedicated Hero has a scoped image-to-section synchronization');
$check(str_contains($js, 'syncDedicatedHeroMedia(activeMediaPickerField)'), 'Media selection synchronizes the dedicated Hero section');
$check(str_contains($js, 'syncDedicatedHeroMedia(field)'), 'Removing the image synchronizes the dedicated Hero section');
$check(str_contains($js, "['[data-builder-hero-image-id]', id]"), 'Existing Hero media ID field remains authoritative');
$check(str_contains($js, "['[data-builder-hero-image-path]', path]"), 'Existing Hero media path remains authoritative');
$check(str_contains($js, 'data-page-hero-background-settings'), 'Dedicated Hero hides legacy modal image picker');
$check(str_contains($view, 'data-page-hero-background-settings'), 'Legacy image picker remains in settings markup');
$check(str_contains($js, "if (!settingsSection.classList.contains('admin-page-row--dedicated-hero'))"), 'Settings save cannot overwrite a dedicated Hero media selection');
$check(str_contains($js, "if (isHero && heroHeight) heroHeight.focus({ preventScroll: true })"), 'Hero modal starts at visible height control without forced scrolling');
$check(str_contains($js, '// Adding a section must never open its settings modal automatically.'), 'Section chooser still avoids automatic settings modal');
$check(str_contains($view, 'data-builder-template="grid-hero"'), 'New dedicated Hero template retained');
$check(str_contains($php, 'data-page-hero-content-media'), 'Dedicated Hero content includes background image picker');
$check(str_contains($php, 'data-page-hero-fields hidden'), 'Hero content starts collapsed, like existing block editors');
$check(str_contains($php, 'data-page-hero-toggle'), 'Dedicated Hero toggle exists');
$check(str_contains($php, 'data-media-picker-open') && str_contains($php, 'data-media-picker-clear'), 'Hero image picker reuses Core media selection and removal');
$check(str_contains($js, "saveHero('[data-builder-hero-height]'"), 'Height remains in section settings');
$check(str_contains($js, "saveHero('[data-builder-hero-overlay]'"), 'Overlay remains in section settings');
$check(str_contains($js, "saveHero('[data-builder-hero-parallax]'"), 'Parallax remains in section settings');
$check(str_contains($view, 'data-page-section-preview'), 'Standard section preview remains unchanged');
$check(str_contains($view, 'data-page-section-visibility'), 'Hero and standard section visibility remain intact');
$check(str_contains($js, 'closeDialog(sectionDialog);'), 'Settings apply still closes only after explicit Save');
$check(str_contains($js, "sectionSave?.addEventListener('click'"), 'Settings have explicit Save action');
$check(str_contains($js, "sectionDialog?.addEventListener('close'"), 'Cancel and closing modal reset its pending section');
$check(str_contains($js, "target.dispatchEvent(new Event('change', { bubbles: true }))"), 'Image changes notify Core dirty-state tracking');

foreach (['en', 'da', 'de', 'sv', 'nb'] as $language) {
    $check(isset($translations['page_builder_v2.hero_background'][$language]), "Hero background label translated: {$language}");
    $check(isset($translations['page_builder_v2.hero_settings_help'][$language]), "Hero settings help translated: {$language}");
    $check(!str_contains(strtolower($translations['page_builder_v2.hero_settings_help'][$language]), 'background'), "Settings copy no longer incorrectly directs users to background image: {$language}");
    $check(isset($translations['page_builder_v2.save_settings'][$language]) && $translations['page_builder_v2.save_settings'][$language] !== '', "Apply settings button translated: {$language}");
}

// Render the actual editor through the existing Page Builder functions, without DB or HTTP.
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function t(string $key, array $params = []): string {
    global $translations;
    $value = $translations[$key]['en'] ?? $key;
    foreach ($params as $k => $v) $value = str_replace('{' . $k . '}', (string)$v, $value);
    return $value;
}
function admin_icon(string $name, string $class = ''): string { return '<svg class="' . admin_e($class) . '" data-icon="' . admin_e($name) . '"></svg>'; }
function admin_icon_action_button(string $icon, string $label, array $attributes = [], string $variant = ''): string {
    $attrs = '';
    foreach ($attributes as $key => $value) $attrs .= ' ' . $key . '="' . admin_e($value) . '"';
    return '<button type="button" class="admin-icon-action" aria-label="' . admin_e($label) . '"' . $attrs . '><svg class="admin-icon-action__icon"></svg></button>';
}
require_once $root . '/core/classes/AdminToggle.php';
require_once $root . '/core/classes/PageBlocks.php';
require_once $root . '/public_html/admin/incl/page-builder-functions.php';
$source = [
    'type' => 'grid', 'status' => 'active',
    'data' => [
        'layout_version' => 'v2', 'section_variant' => 'hero',
        'hero_layout' => 'dedicated', 'column_count' => 1,
        'hero_image_id' => 68, 'hero_image_path' => '/uploads/2026/11/hero-test.webp',
        'hero_title' => 'Demo heading', 'hero_height' => 'screen',
        'hero_overlay' => '1', 'hero_overlay_opacity' => 40, 'hero_parallax' => '0',
    ],
];
$html = admin_page_builder_block_editor($source, 0);
$check(str_contains($html, 'data-page-hero-content-media'), 'Rendered dedicated Hero contains an inline media picker');
$check(str_contains($html, 'value="/uploads/2026/11/hero-test.webp"'), 'Inline picker displays the saved image path');
$check(str_contains($html, 'value="68" data-media-picker-id') || str_contains($html, 'data-media-picker-id value="68"'), 'Inline picker retains saved media ID');
$check(substr_count($html, 'name="blocks[0][data][hero_image_id]"') === 1, 'Saved Hero image ID still has exactly one named input');
$check(substr_count($html, 'name="blocks[0][data][hero_image_path]"') === 1, 'Saved Hero image path still has exactly one named input');
$check(str_contains($html, 'name="blocks[0][data][hero_image_path]" value="/uploads/2026/11/hero-test.webp"'), 'Existing Hero image data remains in the same POST key');
$check(str_contains($html, 'name="blocks[0][data][hero_title]"'), 'Hero heading key preserved');
$check(str_contains($html, 'name="blocks[0][data][hero_height]"'), 'Hero height key preserved');
$check(str_contains($html, 'name="blocks[0][data][hero_parallax]"'), 'Hero parallax key preserved');
$check(str_contains($html, 'data-page-hero-fields hidden'), 'Rendered Hero initially hides content editor');
$check(str_contains($html, 'data-page-hero-toggle="1" aria-expanded="false"'), 'Rendered chevron initially reports collapsed state');
$check(str_contains($html, 'data-media-picker-clear '), 'Rendered Hero has removable image');
$check(str_contains($html, 'Demo heading'), 'Existing Hero title remains visible in compact summary');

// Non-dedicated Hero must remain renderable; it keeps the legacy modal picker.
$legacy = ['type'=>'grid','status'=>'active','data'=>['section_variant'=>'hero','layout_version'=>'v2','preset'=>'equal','column_count'=>1,'columns'=>[['blocks'=>[]]],'hero_image_path'=>'/uploads/old.jpg']];
try { $legacyHtml = admin_page_builder_block_editor($legacy, 1); $legacyOk = str_contains($legacyHtml, 'data-builder-hero-image-path'); }
catch (Throwable $error) { $legacyOk = false; }
$check($legacyOk, 'Older non-dedicated Hero section remains renderable');

printf("Page Builder Hero Workflow QA1: %d PASS / %d FAIL (read-only)\n", $tests, $failures);
exit($failures ? 1 : 0);
