<?php
/** V2.4.4y5a1: inline Page Builder add-box SVG without inner icon frame. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root.'/core/classes/Autoload.php';
require_once $root.'/public_html/admin/incl/functions.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    $ok ? ++$pass : ++$fail;
    echo ($ok ? 'PASS: ' : 'FAIL: ').$label."\n";
};
$view = (string)file_get_contents($root.'/public_html/admin/views/pages/partials/form-fields.php');
$scss = (string)file_get_contents($root.'/public_html/admin/assets/scss/admin.scss');
$css = (string)file_get_contents($root.'/public_html/admin/assets/css/admin.css');
$icon = admin_icon('plus', 'admin-page-add-box__plus');
$check(str_contains($icon, '<svg') && str_contains($icon, 'M12 5v14M5 12h14'), 'Plus is a Core-owned SVG');
$check(str_contains($icon, 'class="admin-page-add-box__plus"'), 'SVG has Page Builder class');
$check(str_contains($icon, 'aria-hidden="true"'), 'Decorative plus does not duplicate button accessible label');
$check(str_contains($view, "admin_icon('plus', 'admin-page-add-box__plus')"), 'Add-box renders reusable Core SVG');
$check(!str_contains($view, '<span class="admin-page-add-box__plus"'), 'Old boxed text-plus markup removed');
$check(str_contains($view, 'class="admin-page-add-box" type="button" data-builder-add'), 'Original add action and non-submitting type remain unchanged');
$check(str_contains($view, "page_builder_v2.add_content"), 'Localized add-button label preserved');
$check($scss === $css, 'SCSS source and compiled CSS match exactly');
$check(str_contains($css, '.admin-page-add-box { display:flex; justify-content:center; align-items:center; gap:8px;'), 'Centered icon-text row uses compact gap');
$check(str_contains($css, '.admin-page-add-box__plus { display:block; flex:0 0 18px; width:18px; height:18px; border:0; background:transparent; }'), 'Plus has no inner frame or fill');
$check(str_contains($css, '.admin-page-add-box:hover, .admin-page-add-box:focus-visible'), 'Outer button hover and keyboard focus preserved');
$check(str_contains($css, 'border:1px dashed var(--admin-border)'), 'Outer dashed button border preserved');
$js = (string)file_get_contents($root.'/public_html/admin/assets/js/admin.js');
$check(str_contains($js, "builderForm.querySelector('[data-builder-add]')"), 'Builder JS retains add-button click integration');
$check(is_file($root.'/public_html/assets/css/style.css'), 'Frontend CSS remains present');
$version = (string)file_get_contents($root.'/public_html/incl/init.php');
$check(preg_match("~define\('BOILERPLATE_VERSION', '([0-9.]+)'\)~", $version, $releaseVersion) === 1
    && version_compare($releaseVersion[1], '2.4.4.44', '>='), 'Release version remains at least 2.4.4.44');
printf("Summary V2.4.4y5a1: %d PASS / %d FAIL\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
