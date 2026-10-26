<?php
/**
 * V2.5.0-RC4 public admin topbar regressions: isolated, read-only.
 * No CMS bootstrap, no network, no MySQL, no session manipulation in a live site.
 */
declare(strict_types=1);
// Keep the response uncommitted while testing header-related code.
ob_start();
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
Translator::boot('da');
$checks = 0;
$check = static function (bool $ok, string $description) use (&$checks): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$description}\n"); exit(1); }
    echo "PASS: {$description}\n";
    ++$checks;
};
$user = ['firstname' => 'Joachim', 'lastname' => 'Hadberg'];
$page = ['id' => 21, 'page_status' => 'published', 'page_title' => 'Om os'];
$render = static fn(array $u, bool $canManage, array $p, string $token): string => FrontendAdminBar::markup($u, $canManage, $p, $token);
$html = $render($user, true, $page, 'test-csrf');
$check(str_contains($html, 'class="frontend-admin-bar"'), 'Authenticated topbar emits only its frontend-specific semantic root');
$check(str_contains($html, 'aria-label="CMS-administrationslinje"'), 'Topbar is accessible landmark');
$check(str_contains($html, 'href="/admin"'), 'Dashboard link is present');
$check(str_contains($html, 'href="/admin/sider?mode=edit&amp;id=21"'), 'Current published Core page edit URL is escaped');
$check(str_contains($html, 'href="/admin/sider?mode=new"'), 'New Core page shortcut present for allowed roles');
$check(str_contains($html, 'aria-label="Rediger side"'), 'Icon-only mobile links preserve accessible labels');
$check(str_contains($html, '<details class="frontend-admin-bar__account">'), 'Account uses native accessible disclosure');
$check(str_contains($html, 'Joachim Hadberg'), 'Account name is displayed');
$check(str_contains($html, 'method="post" action="/admin/index.php?mode=logout"'), 'Logout reuses existing POST-only Core route');
$check(str_contains($html, 'name="csrf_token" value="test-csrf"'), 'Logout preserves existing Core CSRF field');
$check(str_contains($html, 'type="submit"'), 'Logout requires explicit form submit');
$check(!preg_match('/\sonclick\s*=|<style\b|style\s*=/', $html), 'No inline style or event handlers');
$check(!str_contains($html, 'class="admin-'), 'No backend admin classes leak into frontend');
$check(str_contains($render(['firstname'=>'<script>', 'lastname'=>'"onload="'], true, $page, 'x'), '&lt;script&gt;'), 'Untrusted account name safely escaped');
$check(!str_contains($render(['firstname'=>'<script>'], true, $page, 'x'), '<script>'), 'Account cannot inject script');
$check(str_contains($render($user, true, $page, 'x" onclick="evil'), 'value="x&quot; onclick=&quot;evil"'), 'CSRF token safely HTML escaped');
$restricted = $render($user, false, $page, 'safe');
$check(!str_contains($restricted, 'mode=edit'), 'No edit link for role without content.manage');
$check(!str_contains($restricted, 'mode=new'), 'No new-page link for role without content.manage');
$check(str_contains($restricted, 'href="/admin"'), 'Dashboard remains for authorized access-only role');
foreach ([
    ['page_status' => 'draft', 'id' => 21],
    ['page_status' => 'deleted', 'id' => 21],
    ['page_status' => 'published', 'id' => 0],
    ['page_status' => 'published', 'id' => 77, '_module_public' => true],
    ['page_title' => '404 – Siden findes ikke'],
] as $index => $badPage) {
    $badMarkup = $render($user, true, $badPage, 'safe');
    $check(!str_contains($badMarkup, 'mode=edit'), 'No inappropriate page-edit action #' . $index);
    $check(str_contains($badMarkup, 'mode=new'), 'New-page shortcut still present on non-Core pages #' . $index);
}
$check(str_contains($render($user, true, ['page_status'=>'protected', 'id'=>3], 'csrf'), 'mode=edit&amp;id=3'), 'Protected existing Core page may be edited by content manager');
foreach (Translator::locales() as $locale) {
    Translator::setLocale($locale);
    $localized = $render($user, true, $page, 'safe');
    $check(str_contains($localized, htmlspecialchars(Translator::get('frontend_bar.account'), ENT_QUOTES, 'UTF-8')), "Account label localized: {$locale}");
    $check(str_contains($localized, htmlspecialchars(Translator::get('frontend_bar.navigation'), ENT_QUOTES, 'UTF-8')), "Topbar landmark localized: {$locale}");
}
Translator::setLocale('da');
$bootstrap = (string)file_get_contents($root . '/public_html/index.php');
$header = (string)file_get_contents($root . '/public_html/incl/header.php');
$auth = (string)file_get_contents($root . '/core/classes/FrontendAdminBar.php');
$theme = (string)file_get_contents($root . '/public_html/assets/scss/style.scss');
$themeCss = (string)file_get_contents($root . '/public_html/assets/css/style.css');
$check(str_contains($bootstrap, 'FrontendAdminBar::prepareRequest($settings)'), 'Authentication/caching gate runs on frontend page entry');
$check(strpos($bootstrap, 'FrontendAdminBar::prepareRequest(') < strpos($bootstrap, "get_page('header')"), 'Cookie validation happens before HTTP response markup');
$check(str_contains($header, 'FrontendAdminBar::render($page)'), 'Only resolved current page is used for edit link');
$check(str_contains($auth, "header('Vary: Cookie', false)"), 'Public HTML varies by cookie for shared caches');
$check(str_contains($auth, 'Cache-Control: private, no-store'), 'Cookie-bearing HTML responses must not be cached');
$check(str_contains($auth, 'new Auth()'), 'Login state validated by existing Auth service');
$check(str_contains($auth, 'mustChangePassword()'), 'Password reset gate prevents privileged shortcuts');
$check(str_contains($auth, "->can('content.manage')"), 'Core capability gate controls new/edit page links');
$check(str_contains($auth, "session_name()"), 'Session cookie is detected without new anonymous session');
$check(str_contains($auth, "remember_cookie_name"), 'Existing named remember-me cookie is supported');
$check(str_contains($theme, '@import "components/components.admin-bar"'), 'Theme owns topbar style import');
$check(str_contains($themeCss, 'components/components.admin-bar'), 'Compiled frontend CSS carries topbar section');
$check(!str_contains((string)file_get_contents($root . '/public_html/incl/footer.php'), 'href="/admin"'), 'RC3 public footer has no permanent admin link');
$check(str_contains((string)file_get_contents($root . '/public_html/admin/index.php'), "\$mode === 'logout'"), 'Existing logout processing remains server-side');

// Request without session or remember-me cookie must not create a PHP session.
$settings = new class extends Settings {
    public function __construct() {}
    public function get(string $key, mixed $default = null): mixed { return $default; }
};
$_COOKIE = [];
$before = session_status();
FrontendAdminBar::prepareRequest($settings);
$check($before === session_status(), 'Ordinary anonymous frontend visit does not start a session');
// Existing Forms boot starts a PHP session for visitors, but the session
// contains no authenticated identity. It must not initialize Auth/Db.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION = [];
$_COOKIE = [session_name() => 'synthetic-anonymous-session'];
FrontendAdminBar::prepareRequest($settings);
$check(session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['auth']), 'Anonymous Forms session does not initiate privileged authentication');
echo "Frontend Admin Topbar V2.5.0-RC4: {$checks} PASS / 0 FAIL (isolated, no database)\n";
ob_end_flush();
