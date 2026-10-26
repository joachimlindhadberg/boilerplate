<?php
/** V2.4.4z20k-c: shared Core split header must not wrap CTA label. No database. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__); $pass = 0; $fail = 0;
$check = static function (bool $ok, string $name) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . "\n";
    if ($ok) { ++$pass; } else { ++$fail; }
};
$read = static fn(string $name): string => (string)file_get_contents($root . '/' . $name);
$scss = $read('public_html/admin/assets/scss/admin.scss');
$css = $read('public_html/admin/assets/css/admin.css');
$nav = $read('public_html/admin/views/navigation/edit.php');
$check($scss === $css, 'Core stylesheet source and shipped CSS byte-identical');
$check(str_contains($scss, '.admin-panel__header--split { gap: 16px; flex-wrap: wrap; }'), 'Split-panel header may wrap at narrow widths');
$check(str_contains($scss, '.admin-panel__header--split > div { min-width: 0; flex: 1 1 200px; }'), 'Title reserves readable space before wrapping action');
$check(str_contains($scss, '.admin-panel__header--split > .admin-button { flex: 0 0 auto; white-space: nowrap; margin-left: auto; }'), 'Core primary CTA keeps natural width and one-line label');
$check(str_contains($nav, '<div class="admin-panel__header admin-panel__header--split">'), 'Navigation consumes shared Core header');
$check(str_contains($nav, '<button class="admin-button" type="button" data-nav-add hidden><?= admin_icon(\'plus\', \'admin-button__icon\'); ?><?= admin_e(t(\'Tilføj menupunkt\')); ?></button>'), 'Navigation retains unmodified primary add CTA + Core SVG');
$check(!str_contains($nav, 'style="'), 'Navigation editor has no inline style workaround');
$check(str_contains($read('public_html/admin/assets/js/admin.js'), "addButton.addEventListener('click'"), 'Original Add click JS unaffected');
$check(str_contains($read('docs/design/CORE-UI-STANDARD-V1.0.md'), 'split-panel headers'), 'Shared header fit policy documented in Core UI standard');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", $read('public_html/incl/init.php'), $v) === 1 && version_compare($v[1], '2.4.4.96', '>='), 'Hotfix compatibility retained in later versions');
$check(is_file($root . '/docs/releases/NAVIGATION-ADD-ITEM-NOWRAP-HOTFIX-V2.4.4z20k-c.md'), 'Release notes shipped');
$check(str_contains($css, '.admin-button__icon { display: block; width: 16px; height: 16px; flex: 0 0 16px; margin-right: 7px; }'), 'Core icon geometry unchanged');
$check(!str_contains($scss, '.admin-nav-editor__main [data-nav-add]'), 'No Navigation-only button style override');
echo "Navigation Add Item No-Wrap z20k-c: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
