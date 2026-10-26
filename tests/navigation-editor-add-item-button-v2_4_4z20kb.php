<?php
/** V2.4.4z20k-b: Navigation Add Item button aligns with Core creation buttons. Read-only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $name) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . "\n";
    if ($ok) ++$pass; else ++$fail;
};
$read = static fn(string $name): string => (string)file_get_contents($root . '/' . $name);
$editor = $read('public_html/admin/views/navigation/edit.php');
$pages = $read('public_html/admin/views/pages/index.php');
$navigationCreate = $read('public_html/admin/views/navigation/partials/header.php');
$js = $read('public_html/admin/assets/js/admin.js');
$scss = $read('public_html/admin/assets/scss/admin.scss');
$css = $read('public_html/admin/assets/css/admin.css');
$markup = '<button class="admin-button" type="button" data-nav-add hidden><?= admin_icon(\'plus\', \'admin-button__icon\'); ?><?= admin_e(t(\'Tilføj menupunkt\')); ?></button>';
$check(substr_count($editor, $markup) === 1, 'Add menu item uses the exact primary Core plus+text markup');
$check(!str_contains($editor, 'admin-button--secondary admin-button--compact" type="button" data-nav-add'), 'Old secondary compact Add button removed');
$check(str_contains($pages, "admin_icon('plus', 'admin-button__icon')"), 'Reference Sider plus icon remains');
$check(str_contains($navigationCreate, "admin_icon('plus', 'admin-button__icon')"), 'Navigation menu-create CTA remains standardized');
$check(substr_count($editor, 'data-nav-add hidden') === 1, 'Exactly one dynamically-initialized Add item control');
$check(str_contains($editor, 'type="button" data-nav-add hidden'), 'Button does not submit editor form');
$check(str_contains($js, "querySelector('[data-nav-add]')"), 'Existing JavaScript Add button selector is unchanged');
$check(str_contains($js, 'addButton.hidden = false'), 'Existing JS reveals the Add button');
$check(str_contains($js, "addButton.addEventListener('click'"), 'Existing JS Add click behavior is preserved');
$check(str_contains($editor, 'data-nav-editor data-admin-track-changes'), 'Existing Navigation save and dirty-state hooks remain');
$check(str_contains($editor, 'name="csrf_token"') && str_contains($editor, 'name="action" value="save_editor"'), 'Original CSRF and POST action are unchanged');
$check(str_contains($editor, 'data-nav-list') && str_contains($editor, 'data-nav-template'), 'Existing list/template UI remains');
$check($scss === $css, 'Core SCSS and compiled CSS remain byte-identical');
$check(str_contains($css, '.admin-button__icon { display: block; width: 16px; height: 16px; flex: 0 0 16px; margin-right: 7px; }'), 'Icon spacing remains Core-owned');
$check(str_contains($read('docs/design/CORE-UI-STANDARD-V1.0.md'), '**Tilføj menupunkt:**'), 'Binding UI standard covers Add menu item');
$translations = json_decode($read('core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['da', 'en', 'de', 'sv', 'nb'] as $locale) {
    $check(trim((string)($translations['Tilføj menupunkt'][$locale] ?? '')) !== '', "Button has {$locale} translation");
}
preg_match("~BOILERPLATE_VERSION', '([0-9.]+)'~", $read('public_html/incl/init.php'), $priorVersion);
$check(isset($priorVersion[1]) && version_compare($priorVersion[1], '2.4.4.95', '>='), 'z20k-b is retained in later compatible releases');
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/fixtures/navigation-editor-layout-v2_4_4z20ka.php');
foreach (['' => 'Unassigned', ' assigned' => 'Assigned'] as $arg => $name) {
    $html = shell_exec($cmd . $arg);
    $check(is_string($html) && str_contains($html, 'data-nav-add hidden><svg class="admin-button__icon"'), "{$name} actual PHP view renders Core plus icon");
    if (is_string($html)) {
        $check(preg_match('~<button class="admin-button" type="button" data-nav-add hidden><svg class="admin-button__icon"[^>]*><path d="M12 5v14M5 12h14"/></svg>[^<]+</button>~u', $html) === 1, "{$name} rendered plus+translated text button matches reference");
        $check(substr_count($html, 'data-nav-add hidden>') === 1, "{$name} renders one Add item button");
    }
}
echo "Navigation Add Item Button z20k-b: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
