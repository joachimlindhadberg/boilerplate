<?php
/** V2.4.4z20l — Core dashboard welcome: locale, persistence, permissions, markup. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function(bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $ok ? ++$pass : ++$fail;
};
$get = static fn(string $rel): string => (string)file_get_contents($root . '/' . $rel);
$view = $get('public_html/admin/views/dashboard.php');
$js = $get('public_html/admin/assets/js/admin.js');
$css = $get('public_html/admin/assets/css/admin.css');
$scss = $get('public_html/admin/assets/scss/admin.scss');
$controller = $get('public_html/admin/index.php');
$dict = json_decode($get('core/lang/translations.json'), true, 512, JSON_THROW_ON_ERROR);
$check(str_contains($view, 'admin-dashboard-welcome" data-dashboard-welcome') && str_contains($view, 'data-user-id'), 'Welcome is a Core-owned dashboard-only section and has account scope');
$check(str_contains($view, 'data-dashboard-welcome-close') && str_contains($view, 'admin-modal__close'), 'Exact modal-header close control reused');
$check(!str_contains($view, 'data-modal-close'), 'Closing welcome never triggers a modal action');
$check(str_contains($view, 'data-dashboard-welcome-restore hidden'), 'Accessible opt-in restore control starts hidden');
$check(str_contains($view, 'aria-labelledby="dashboard-welcome-heading"'), 'Semantic heading association');
$check(str_contains($view, "admin_icon('edit'"), 'Homepage shortcut uses Core edit SVG');
$check(str_contains($view, "admin_icon('settings'"), 'Settings shortcut uses Core settings SVG');
$check(str_contains($view, "admin_icon('external'"), 'Site shortcut has Core SVG external link');
$check(str_contains($view, 'rel="noopener"'), 'External new-tab site link remains safe');
$check(str_contains($view, "if (!empty(\$canManageContent))"), 'New page shortcut gated by content capability');
$check(str_contains($view, "if (!empty(\$canManageSettings))"), 'Settings shortcut gated by settings capability');
$check(str_contains($controller, "\$canManageContent = \$auth->can('content.manage')"), 'Controller computes content permission server-side');
$check(str_contains($controller, "'canManageSettings' => \$auth->can('settings.manage')"), 'Controller computes settings permission server-side');
$check(str_contains($js, 'localStorage.getItem(storageKey)'), 'Browser preference read from storage');
$check(str_contains($js, "'boilerplate.admin.dashboard-welcome.v3.user-' + accountId"), 'Per-user v3 key prevents cross-account hide');
$check(str_contains($js, 'localStorage.setItem(storageKey'), 'Close saves preference');
$check(str_contains($js, 'dismissed = false') && str_contains($js, 'persistWelcome()'), 'Restore redisplays and persists in browser');
$check(str_contains($js, 'welcome.hidden = dismissed'), 'Previously dismissed panel does not appear at startup');
$check(str_contains($js, 'welcomeRestore.focus({ preventScroll: true })'), 'Focus moves to restore when closing');
$check(str_contains($js, 'close.focus({ preventScroll: true })'), 'Focus moves into panel when restoring');
$check(str_contains($js, 'catch { /* Blocked browser storage') && str_contains($js, 'catch { /* A blocked store'), 'Blocked storage falls back to in-page dismiss');
$check(str_contains($css, '.admin-dashboard-welcome .admin-modal__close { position: absolute;'), 'Modal close button is positioned in welcome corner');
$check(str_contains($css, '.admin-dashboard-welcome[hidden], .admin-dashboard-welcome__restore[hidden] { display: none; }'), 'Hidden state cannot be overridden by Core CSS');
$check(str_contains($css, '.admin-dashboard-welcome__links { display: flex; flex-wrap: wrap;'), 'Actions wrap safely at narrow widths');
$check($css === $scss, 'Core SCSS and compiled CSS byte-identical');
foreach (['dashboard.welcome_kicker', 'dashboard.welcome_title', 'dashboard.welcome_name', 'dashboard.welcome_description', 'dashboard.welcome_shortcuts', 'dashboard.welcome_dismiss', 'dashboard.welcome_show_again', 'dashboard.welcome_edit_homepage', 'dashboard.welcome_site_settings', 'dashboard.welcome_view_site'] as $key) {
    $check(isset($dict[$key]) && array_keys($dict[$key]) === ['en','da','de','sv','nb'], "All five locales for $key");
}
$fixture = $root . '/tests/fixtures/dashboard-welcome-browser-v2_4_4z20l.php';
$run = static fn(string ...$args): string => (string)shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' ' . implode(' ', array_map('escapeshellarg', $args)));
$html = $run('da', '81', '<script>alert(1)</script>');
$check(str_contains($html, 'data-user-id="81"'), 'Real PHP rendering retains correct user ID');
$check(str_contains($html, 'Velkommen til Boilerplate CMS!'), 'Welcome title uses translated onboarding text');
$check(!str_contains($html, '<script>alert(1)</script>'), 'Untrusted first name cannot inject executable markup');
$check(str_contains($html, 'data-dashboard-welcome-close') && str_contains($html, 'admin-modal__close'), 'Rendered view has functional Core modal close styling');
$check(substr_count($html, 'class="admin-dashboard-welcome__link"') === 3, 'Authorized administrator gets three quick links');
$check(str_contains($html, '/admin/sider?mode=edit&amp;id=9'), 'Homepage shortcut routes to real page edit destination');
$restricted = $run('da', '82', 'Bruger', '0', '0', '', '0');
$check(substr_count($restricted, 'class="admin-dashboard-welcome__link"') === 1, 'Restricted user sees only public site link');
$check(!str_contains($restricted, '/admin/sider?mode=edit') && !str_contains($restricted, '/admin/indstillinger'), 'Unauthorized shortcuts completely omitted');
foreach (['en','de','sv','nb'] as $lang) {
    $rendered = $run($lang, '83', 'Test');
    $check(str_contains($rendered, htmlspecialchars($dict['dashboard.welcome_title'][$lang], ENT_QUOTES, 'UTF-8')), "Real translation rendered for $lang");
}
$check(preg_match("/BOILERPLATE_VERSION', '([^']+)'/", $get('public_html/incl/init.php'), $v) === 1 && version_compare($v[1], '2.4.4.98', '>='), 'Version at least 2.4.4.98');
$check(is_file($root . '/docs/releases/DASHBOARD-WELCOME-PANEL-V2.4.4z20l.md'), 'Dedicated release notes present');
echo "Dashboard Welcome z20l: $pass PASS / $fail FAIL\n";
exit($fail ? 1 : 0);
