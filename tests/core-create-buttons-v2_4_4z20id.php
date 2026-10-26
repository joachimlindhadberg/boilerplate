<?php
/** V2.4.4z20i-d: all ordinary Ny/Opret create buttons follow Pages' Core icon+text pattern. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminIcons.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
$get = static fn(string $relative): string => (string)file_get_contents($root . '/' . $relative);
$pages = $get('public_html/admin/views/pages/index.php');
$users = $get('public_html/admin/views/users/index.php');
$navigation = $get('public_html/admin/views/navigation/partials/header.php');
$gallery = $get('modules/gallery/admin/views/index.php');
$forms = $get('modules/forms/admin/workspace.php');
$workspace = $get('public_html/admin/views/partials/section-workspace.php');
$redirects = $get('public_html/admin/views/tools/redirects.php');
$check(str_contains($pages, '<a class="admin-button" href="/admin/sider?mode=new"><?= admin_icon(\'plus\', \'admin-button__icon\'); ?>'), 'Reference: Sider uses shared Core plus+text primary create link');
$check(str_contains($users, '<a class="admin-button" href="/admin/brugere?mode=new"><?= admin_icon(\'plus\', \'admin-button__icon\'); ?>'), 'Users new button matches Sider markup');
$check(str_contains($navigation, '<a class="admin-button" href="/admin/navigation?mode=new"><?= admin_icon(\'plus\', \'admin-button__icon\'); ?>'), 'Navigation create button matches Sider markup');
$check(str_contains($gallery, '<a class="admin-button" href="/admin/gallerier?screen=new"><?= admin_icon(\'plus\', \'admin-button__icon\'); ?>'), 'Gallery new button matches Sider markup');
$check(str_contains($gallery, '<a class="admin-button" href="/admin/gallerier?screen=new"><?= admin_icon(\'plus\', \'admin-button__icon\'); ?><?= admin_e(t(\'gallery:Opret galleri\')); ?>'), 'Gallery empty-state create button matches Sider');
$check(str_contains($forms, "'label' => 'forms:Ny formular'") && str_contains($forms, "'kind' => 'create'"), 'Forms module declares create action in Core workspace');
$check(str_contains($workspace, "(\$sectionAction['kind'] ?? '') === 'create'") && str_contains($workspace, "admin_icon('plus', 'admin-button__icon')"), 'Core workspace applies plus only to explicitly declared create actions');
$check(str_contains($redirects, '<a class="admin-button" href="/admin/vaerktoejer/redirects"><?= admin_icon(\'plus\', \'admin-button__icon\'); ?>'), 'Redirects create-new shortcut matches shared Core button');
$check(!str_contains($navigation, '<a class="admin-button" href="/admin/navigation?mode=new"><?= admin_e'), 'Navigation old text-only create CTA removed');
$check(!str_contains($users, '<a class="admin-button" href="/admin/brugere?mode=new"><?= admin_e'), 'Users old text-only create CTA removed');
$check(!str_contains($gallery, '<a class="admin-button" href="/admin/gallerier?screen=new"><?= admin_e'), 'Gallery old text-only create CTA removed');
// A Core helper returns decorative SVG; button/link text continues to supply the accessible name.
$svg = AdminIcons::render('plus', 'admin-button__icon');
$check(str_contains($svg, 'class="admin-button__icon"') && str_contains($svg, 'aria-hidden="true"'), 'Shared Core plus has correct size class and is decorative');
$check(!str_contains($svg, '<script') && !str_contains($svg, 'onclick'), 'Core SVG contains no executable markup');
// Render the real shared workspace with two different actions to verify optional icon behavior.
function t(string $value): string { return $value; }
function admin_e(mixed $value): string { return AdminEscaper::text($value); }
function admin_icon(string $name, string $css = ''): string { return AdminIcons::render($name, $css); }
$render = static function (array $action) use ($root): string {
    $sectionWorkspace = ['heading'=>'Test', 'description'=>'', 'navigationMode'=>'none', 'action'=>$action];
    $bodyHtml = '<section id="contents">Content</section>';
    ob_start();
    require $root . '/public_html/admin/views/partials/section-workspace.php';
    return (string)ob_get_clean();
};
$create = $render(['href'=>'/admin/forms?screen=new', 'label'=>'Ny formular', 'kind'=>'create']);
$normal = $render(['href'=>'/admin/forms?screen=settings', 'label'=>'Indstillinger']);
$unsafe = $render(['href'=>'/admin/forms?screen=new&source=x', 'label'=>'Ny <script>alert(1)</script>', 'kind'=>'create']);
$check(substr_count($create, 'class="admin-button__icon"') === 1, 'Create workspace renders exactly one Core plus icon');
$check(str_contains($create, 'href="/admin/forms?screen=new"') && str_contains($create, '>Ny formular</a>'), 'Forms create route and localized display label retained');
$check(!str_contains($normal, 'admin-button__icon'), 'Non-create workspace does not get a misleading plus icon');
$check(str_contains($normal, 'href="/admin/forms?screen=settings"'), 'Normal workspace route remains unchanged');
$check(!str_contains($unsafe, '<script>') && str_contains($unsafe, '&lt;script&gt;'), 'Untrusted create label escaped safely');
$check(str_contains($unsafe, 'screen=new&amp;source=x'), 'Create URL query escaped safely');
$check(substr_count($create, 'class="admin-section-layout--') === 0 && str_contains($create, 'admin-section-layout--full'), 'Forms workspace remains full width, no restored sidebar');
// Creating and saving are different intents. Only the actual create button gets the plus.
$usersForm = $get('public_html/admin/views/users/partials/form-fields.php');
$pageForm = $get('public_html/admin/views/pages/partials/form-fields.php');
$newMenu = $get('public_html/admin/views/navigation/new.php');
$check(str_contains($usersForm, "\$isEdit ? '' : admin_icon('plus', 'admin-button__icon')"), 'New user submit gets plus; Edit/Save stays icon-free');
$check(str_contains($pageForm, "\$isEdit ? '' : admin_icon('plus', 'admin-button__icon')"), 'Page create submit gets plus; Edit/Save stays icon-free');
$check(str_contains($newMenu, '<button class="admin-button" type="submit"><?= admin_icon(\'plus\', \'admin-button__icon\'); ?>'), 'New menu create submit gets plus');
$check(str_contains($redirects, "\$isEdit ? '' : admin_icon('plus', 'admin-button__icon')"), 'Redirect create submit gets plus; Edit/Save stays icon-free');
$check(str_contains($get('modules/forms/admin/views/partials/form-editor.php'), "t('forms:Gem formular')"), 'Forms save action is still Save, not mislabeled Create');
$check(str_contains($get('modules/gallery/admin/views/partials/gallery-editor.php'), "t('gallery:Gem galleri')"), 'Gallery save action is still Save, not mislabeled Create');
$check(str_contains($pages, "!\$hasFilters ? admin_icon('plus', 'admin-button__icon') : ''"), 'Pages existing filtered empty state unaffected');
$check($get('public_html/admin/assets/scss/admin.scss') === $get('public_html/admin/assets/css/admin.css'), 'Global Core SCSS and CSS byte-identical');
$check(str_contains($get('public_html/admin/assets/css/admin.css'), '.admin-button__icon { display: block; width: 16px; height: 16px; flex: 0 0 16px; margin-right: 7px; }'), 'Common Core icon size and spacing reused without CSS changes');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/", $get('public_html/incl/init.php'), $versionMatch) === 1 && version_compare($versionMatch[1], '2.4.4.90', '>='), 'Internal version >= 2.4.4.90');
echo "Core Create Buttons z20i-d: {$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
