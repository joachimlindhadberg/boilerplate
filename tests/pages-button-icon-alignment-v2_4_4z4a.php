<?php
/** V2.4.4z4a – shared Core text-button icon sizing and Pages new/view alignment. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminIcons.php';
$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    $ok ? ++$pass : ++$fail;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
};
$index = (string)file_get_contents($root . '/public_html/admin/views/pages/index.php');
$edit = (string)file_get_contents($root . '/public_html/admin/views/pages/partials/form-fields.php');
$view = (string)file_get_contents($root . '/public_html/admin/views/pages/view.php');
$scss = (string)file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$css = (string)file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$version = (string)file_get_contents($root . '/public_html/incl/init.php');
$check(str_contains($index, '<a class="admin-button" href="/admin/sider?mode=new"><?= admin_icon(\'plus\', \'admin-button__icon\'); ?>'), 'Ny side includes Core plus in main list CTA');
$check(str_contains($index, "!\$hasFilters ? admin_icon('plus', 'admin-button__icon') : ''"), 'Unfiltered empty-state create CTA also gets plus');
$check(str_contains($index, 'pages.clear_filters'), 'Filtered empty state remains Clear filters (no misleading plus)');
$check(str_contains($edit, "admin_icon('external', 'admin-button__icon')"), 'Edit Se side SVG receives sized Core text-button class');
$check(!str_contains($edit, "admin_icon('external');"), 'Edit Se side no longer renders unsized SVG');
$check(str_contains($edit, 'target="_blank" rel="noopener noreferrer"'), 'Edit Se side preserves public new-tab navigation');
$check(str_contains($edit, 'admin_page_public_url($pageRecord)'), 'Edit public URL generation unchanged');
$check(str_contains($edit, 'href="/admin/sider?mode=history&amp;id='), 'Versionshistorik sibling unchanged');
$check(str_contains($view, "admin_icon('external', 'admin-button__icon')"), 'Detail Se side uses shared text-button icon');
$check(str_contains($view, "admin_icon('edit', 'admin-button__icon')"), 'Detail Rediger uses shared text-button icon');
$check(!str_contains($view, 'admin-page-overview-button-icon'), 'Old view-specific icon class eliminated');
$check(str_contains($css, '.admin-button__icon { display: block; width: 16px; height: 16px; flex: 0 0 16px; margin-right: 7px; }'), 'Shared Core CSS defines explicit icon dimensions and spacing');
$check(!str_contains($css, '.admin-page-overview-button-icon'), 'No duplicate view-only button icon styling');
$check(str_contains($css, '.admin-button--secondary {') && str_contains($css, '.admin-button--secondary:hover {'), 'Existing secondary button styling unchanged');
$check($scss === $css, 'Admin SCSS and compiled CSS synchronized');
$check(str_contains(AdminIcons::render('plus', 'admin-button__icon'), 'class="admin-button__icon"'), 'Core SVG icon rendering has expected CSS class');
$check(str_contains(AdminIcons::render('external', 'admin-button__icon'), 'aria-hidden="true"'), 'Decorative icon is hidden from accessibility tree');
$check((bool)preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/",$version,$match) && version_compare($match[1],'2.4.4.52','>='), 'Version metadata is V2.4.4z4a or later');
$check(is_file($root.'/database/updates/20261016_000_pages_author_audit.php'), 'Existing author-audit migration retained');
$check(is_file($root.'/public_html/admin/assets/js/admin-page-workflow.js'), 'Existing Pages workflow JS retained');
$check(is_file($root.'/public_html/assets/css/style.css'), 'Frontend stylesheet retained');
printf('Summary V2.4.4z4a: %d PASS / %d FAIL' . "\n",$pass,$fail);
exit($fail === 0 ? 0 : 1);
