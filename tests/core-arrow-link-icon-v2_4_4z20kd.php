<?php
/** V2.4.4z20k-d — Core navigation arrows and real translated back links. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminIcons.php';
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $message) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $message . "\n";
    if ($ok) { ++$pass; } else { ++$fail; }
};
$read = static fn(string $file): string => (string)file_get_contents($root . '/' . $file);
$left = AdminIcons::render('arrow-left', 'admin-button__icon');
$right = AdminIcons::render('arrow-right', 'admin-back-link__icon');
$check(str_contains($left, 'M20 12H4M11 5l-7 7 7 7'), 'Back icon is a real Core left-facing arrow');
$check(str_contains($right, 'M4 12h16M13 5l7 7-7 7'), 'Forward icon is a real Core right-facing arrow');
$check($left !== $right, 'Back and forward are different SVG shapes');
$check(str_contains($left, 'viewBox="0 0 24 24"'), 'Arrow uses existing Core viewBox');
$check(str_contains($left, 'aria-hidden="true"') && str_contains($left, 'focusable="false"'), 'Arrow is decorative to assistive tech');
$check(!str_contains($left, '<script') && !str_contains($left, 'onclick'), 'Arrow cannot inject executable markup');
$check(str_contains(AdminIcons::render('arrow-left', 'safe" onclick="bad'), '&quot;'), 'Icon class attributes are escaped');
$check(str_contains(AdminIcons::actionLink('arrow-left', 'Tilbage', '/admin/sider'), 'aria-label="Tilbage"'), 'Core icon-only action helper can reuse arrow safely');
foreach (['core/lang/translations.json','modules/forms/lang/translations.json','modules/gallery/lang/translations.json'] as $source) {
    $data = json_decode($read($source), true, 512, JSON_THROW_ON_ERROR);
    $expected = match ($source) {
        'core/lang/translations.json' => ['Tilbage','Tilbage til oversigten','Tilbage til hjemmesiden','Tilbage til bruger','Tilbage til brugere','Tilbage til siden','Tilbage til sider','Tilbage til versionshistorik'],
        'modules/forms/lang/translations.json' => ['Tilbage til Forms','Tilbage til indsendelser'],
        default => ['Tilbage til Gallerier'],
    };
    foreach ($expected as $key) {
        $labels = $data[$key] ?? null;
        $check(is_array($labels) && count($labels) === 5 && !array_diff(['da','en','de','sv','nb'], array_keys($labels)), "$source localized $key in five languages");
        $check(is_array($labels) && !array_filter($labels, static fn($s): bool => !is_string($s) || str_contains($s, '←')), "$source has no hardcoded arrow in $key");
        if ($key !== 'Tilbage til bruger') {
            $check(isset($data['← ' . $key]), "$source retains backwards compatible legacy translation $key");
        }
    }
}
$locations = [
    'public_html/admin/views/navigation/partials/header.php' => 'admin-button__icon',
    'public_html/admin/views/pages/partials/form-fields.php' => 'admin-back-link__icon',
    'public_html/admin/views/pages/view.php' => 'admin-back-link__icon',
    'public_html/admin/views/pages/history.php' => 'admin-back-link__icon',
    'public_html/admin/views/pages/revision.php' => 'admin-back-link__icon',
    'public_html/admin/views/users/view.php' => 'admin-back-link__icon',
    'public_html/admin/views/users/partials/form-context.php' => 'admin-back-link__icon',
    'public_html/admin/views/delete-confirm.php' => 'admin-back-link__icon',
    'modules/forms/admin/views/partials/form-editor.php' => 'admin-back-link__icon',
    'modules/forms/admin/views/partials/submission-detail.php' => 'admin-back-link__icon',
    'modules/gallery/admin/views/partials/gallery-editor.php' => 'admin-back-link__icon',
    'public_html/admin/views/login.php' => 'admin-back-link__icon',
    'public_html/admin/views/tools/redirects.php' => 'admin-button__icon',
];
foreach ($locations as $name => $cls) {
    $view = $read($name);
    $check(str_contains($view, "admin_icon('arrow-left', '$cls')"), "$name consumes Core arrow icon");
    $check(!str_contains($view, '←'), "$name has no text glyph arrow");
}
$nav = $read('public_html/admin/views/navigation/partials/header.php');
$check(str_contains($nav, 'href="/admin/navigation"'), 'Original Navigation back destination preserved');
$check(str_contains($nav, 'admin-button admin-button--secondary'), 'Navigation back retains secondary Core button style');
$check(str_contains($nav, "t('Tilbage til oversigten')"), 'Navigation uses arrow-free localized label');
$scss = $read('public_html/admin/assets/scss/admin.scss');
$css = $read('public_html/admin/assets/css/admin.css');
$check($scss === $css, 'Shared Core SCSS/CSS byte-identical');
$check(str_contains($scss, '.admin-back-link { display: inline-flex; align-items: center; gap: 6px; }'), 'Core owns link icon horizontal layout');
$check(str_contains($scss, '.admin-back-link__icon { display: block; width: 14px; height: 14px; flex: 0 0 14px; }'), 'Core owns arrow glyph dimensions');
$check(str_contains($scss, '.admin-button__icon { display: block; width: 16px; height: 16px; flex: 0 0 16px; margin-right: 7px; }'), 'Existing Core button dimensions unchanged');
$check(!str_contains($read('modules/forms/admin/views/partials/form-editor.php'), '<style'), 'Modules add no inline styles');
$check(str_contains($read('docs/design/CORE-UI-STANDARD-V1.0.md'), 'V2.4.4z20k-d'), 'Binding Core UI standard extended');
$check(is_file($root . '/docs/releases/CORE-ARROW-LINK-ICON-V2.4.4z20k-d.md'), 'Dedicated release notes shipped');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", $read('public_html/incl/init.php'), $v) === 1 && version_compare($v[1], '2.4.4.97', '>='), 'Release retains z20k-d compatibility (>= 2.4.4.97)');
$render = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/fixtures/navigation-editor-layout-v2_4_4z20ka.php'));
$check(is_string($render) && str_contains($render, 'href="/admin/navigation"') && str_contains($render, 'Tilbage til oversigten'), 'Real Navigation editor retains translated return link');
$check(is_string($render) && str_contains($render, '<svg class="admin-button__icon"') && str_contains($render, 'M20 12H4M11 5l-7 7 7 7'), 'Real Navigation editor renders correct SVG back arrow');
$check(is_string($render) && !str_contains($render, '← Tilbage til oversigten'), 'Real Navigation editor has no duplicate text arrow');
echo "Core Arrow Link Icon z20k-d: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
