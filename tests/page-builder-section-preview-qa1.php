<?php
/** Page Builder Section Preview QA1 — source guards only, no HTTP/MySQL/writes. */
declare(strict_types=1);
$root = dirname(__DIR__);
$view = file_get_contents($root . '/public_html/admin/views/pages/partials/form-fields.php');
$js = file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$css = file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$scss = file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$renderer = file_get_contents($root . '/public_html/incl/helpers/page_builder_v2_renderer.php');
$blocks = file_get_contents($root . '/core/classes/PageBlocks.php');
$passed = 0; $failed = 0;
$check = static function (bool $ok, string $name) use (&$passed, &$failed): void {
    if ($ok) { $passed++; echo "PASS: $name\n"; }
    else { $failed++; echo "FAIL: $name\n"; }
};
$check(str_contains($view, 'data-page-section-settings-dialog'), 'Existing Core section settings modal preserved');
$check(str_contains($view, 'data-page-section-width') && str_contains($view, 'data-page-section-theme'), 'Original width/theme controls retained');
$check(str_contains($view, 'data-page-section-save'), 'Explicit Save button retained');
$check(str_contains($view, 'data-page-section-preview') && str_contains($view, 'aria-hidden="true"'), 'Decorative schematic preview appears in modal');
$check(str_contains($view, 'admin-page-section-preview__content'), 'Preview content width indicator included');
$check(str_contains($js, "const sectionPreview = sectionDialog?.querySelector('[data-page-section-preview]')"), 'Preview is scoped to settings dialog');
$check(str_contains($js, "sectionWidth?.addEventListener('change', updateSectionPreview)"), 'Width change updates preview');
$check(str_contains($js, "sectionTheme?.addEventListener('change', updateSectionPreview)"), 'Theme change updates preview');
$check(str_contains($js, "sectionTheme.value = themeInput.value;\n          updateSectionPreview();"), 'Preview resets from saved values on every open');
$check(str_contains($js, 'sectionPreview.dataset.theme =') && str_contains($js, 'sectionPreview.dataset.width ='), 'Preview only changes illustrative data attributes');
$check(str_contains($js, 'const nextWidth =') && str_contains($js, 'const nextTheme =') && str_contains($js, 'closeDialog(sectionDialog);'), 'Existing explicit Save handler remains');
$check($css === $scss, 'Core SCSS and compiled CSS remain synchronized');
foreach (['narrow','wide','full'] as $width) {
    $check(str_contains($css, '[data-width="'.$width.'"]'), "Visual preview supports $width width");
}
foreach (['soft','dark'] as $theme) {
    $check(str_contains($css, '[data-theme="'.$theme.'"]'), "Visual preview supports $theme theme");
}
$check(str_contains($css, 'prefers-reduced-motion:reduce'), 'Reduced-motion preference supported');
$check(str_contains($renderer, 'class="block-section" data-theme=') && str_contains($renderer, 'data-container-width='), 'Existing theme-owned frontend contract retained');
$check(str_contains($blocks, "'standard' => 'Standard'") && str_contains($blocks, "'soft' => 'Soft'"), 'Existing canonical values untouched');
echo "RESULT: $passed PASS / $failed FAIL (source QA, no HTTP, browser, MySQL or writes)\n";
exit($failed ? 1 : 0);
