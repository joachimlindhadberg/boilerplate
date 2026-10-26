<?php
/**
 * V2.5.0-RC4a frontend topbar UX regression; isolated source-only test.
 * Never opens the CMS database, starts a live site, or changes session data.
 */
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
Translator::boot('da');
$pass = 0;
$check = static function (bool $ok, string $what) use (&$pass): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$what}\n"); exit(1); }
    echo "PASS: {$what}\n";
    ++$pass;
};
$user = ['firstname'=>'Joachim','lastname'=>'Hadberg'];
$page = ['id'=>21,'page_status'=>'published'];
$markup = static fn (bool $can, array $p = []): string => FrontendAdminBar::markup($user, $can, $p ?: $page, 'abc123');
$html = $markup(true);
$check(substr_count($html, 'href="/admin"') === 1, 'Exactly one /admin Dashboard destination');
$check(str_contains($html, '<a class="frontend-admin-bar__brand" href="/admin"'), 'CMS brand is sole Dashboard shortcut');
$check(!preg_match('~aria-label="Dashboard"|>Dashboard</a>~u', $html), 'No duplicate Dashboard text or action');
$check(str_contains($html, 'href="/admin/sider?mode=edit&amp;id=21"'), 'Edit current page remains');
$check(str_contains($html, 'href="/admin/sider?mode=new"'), 'Create page remains');
$check(substr_count($html, 'class="frontend-admin-bar__nav"') === 1, 'Exactly one relevant action navigation');
$check(str_contains($html, '<details class="frontend-admin-bar__account">'), 'Account disclosure remains');
$check(str_contains($html, 'Joachim Hadberg'), 'Account name retained');
$check(str_contains($html, 'method="post" action="/admin/index.php?mode=logout"'), 'Logout retains safe POST action');
$check(str_contains($html, 'name="csrf_token" value="abc123"'), 'Logout retains CSRF token');
$check(substr_count($html, 'href="/admin"') === 1 && substr_count($html, '<form') === 1, 'No redundant account Dashboard action');
$restricted = $markup(false);
$check(substr_count($restricted, 'href="/admin"') === 1, 'Restricted account still has Dashboard from CMS brand');
$check(!str_contains($restricted, 'class="frontend-admin-bar__nav"'), 'Restricted account does not get an empty shortcuts nav');
$check(!str_contains($restricted, '/admin/sider'), 'Restricted account gets no page management links');
$check(str_contains($restricted, 'mode=logout'), 'Restricted account can still sign out');
$uneditable = $markup(true, ['page_status'=>'draft', 'id'=>21]);
$check(!str_contains($uneditable, 'mode=edit'), 'Draft or otherwise ineligible frontend page gets no edit action');
$check(str_contains($uneditable, 'mode=new'), 'Create page still available on other routes');
$scss = file_get_contents($root . '/public_html/assets/scss/components/_components.admin-bar.scss');
$css = file_get_contents($root . '/public_html/assets/css/style.css');
$check(is_string($scss) && is_string($css) && str_contains($css, $scss), 'Exact topbar SCSS rules reproduced in compiled CSS');
$check((bool)preg_match('/\.frontend-admin-bar\s*\{[^}]*position:\s*fixed;/s', $scss), 'Topbar is position fixed');
$check((bool)preg_match('/\.frontend-admin-bar\s*\{[^}]*inset:\s*0 0 auto;/s', $scss), 'Fixed topbar anchors to viewport top');
$check((bool)preg_match('/\.frontend-admin-bar\s*\{[^}]*height:\s*40px;/s', $scss), 'Topbar reserves defined height');
$check(str_contains($scss, 'body:has(> .frontend-admin-bar) {') && str_contains($scss,'padding-top: 40px;'), 'Authenticated pages reserve space beneath fixed bar');
$check(str_contains($scss, 'html:has(> body > .frontend-admin-bar)') && str_contains($scss, 'scroll-padding-top: 104px;'), 'In-page anchors account for both fixed toolbar and sticky header');
$check(str_contains($scss, 'body:has(> .frontend-admin-bar) .site-header') && str_contains($scss, 'top: 40px;'), 'Sticky site header remains below topbar');
$check(str_contains($scss, 'body:has(> .frontend-admin-bar) .site-header .site-navigation'), 'Mobile navigation offset scoped only to authenticated pages');
$check(str_contains($scss, 'inset: 104px 0 0;'), 'Mobile navigation respects bar plus 64px header');
$check(!str_contains($scss, 'position: relative;\n  z-index: 1100;'), 'Old flow-positioned toolbar styling removed');
$check(!str_contains($html, '<style') && !str_contains($html, 'style='), 'Frontend Core markup has no inline styles');
$check(str_contains(file_get_contents($root . '/public_html/incl/header.php'), 'FrontendAdminBar::render($page)'), 'Optional topbar still renders directly before public header');
$check(str_contains(file_get_contents($root . '/public_html/assets/scss/style.scss'), '@import "components/components.admin-bar"'), 'Frontend theme remains the styling owner');
foreach (Translator::locales() as $locale) {
    Translator::setLocale($locale);
    $localized = $markup(true);
    $check(substr_count($localized, 'href="/admin"') === 1, "No Dashboard duplication in {$locale}");
    $check(str_contains($localized, htmlspecialchars(Translator::get('frontend_bar.account'), ENT_QUOTES, 'UTF-8')), "Account still localized in {$locale}");
}
echo "Frontend Admin Topbar RC4a: {$pass} PASS / 0 FAIL (read-only, no DB)\n";
