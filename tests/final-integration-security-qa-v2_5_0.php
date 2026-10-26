<?php
/**
 * V2.5.0 Final Integration & Security QA (source + pure-behaviour gates).
 * No CMS bootstrap, .env, active database, HTTP requests or writes.
 * Real DB/browser/restore must run separately on isolated staging.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(2); }
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    $ok ? ++$pass : ++$fail;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
};
$read = static function (string $name) use ($root): string {
    if (!preg_match('~^(?:core|public_html|modules|database|tests|docs)/[a-zA-Z0-9._/-]+$~D', $name)
        || str_contains($name, '..')) throw new LogicException('Unsafe file path');
    $file = $root . '/' . $name;
    if (!is_file($file) || is_link($file)) throw new RuntimeException('Missing source: ' . $name);
    return (string)file_get_contents($file);
};
$all = static fn(string $s, array $needles): bool => array_reduce($needles,
    static fn(bool $ok, string $needle): bool => $ok && str_contains($s, $needle), true);
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminRequest.php';
require_once $root . '/core/classes/CapabilityRegistry.php';
require_once $root . '/core/classes/DashboardCards.php';
$reject = static function (array $value, array $schema): bool {
    try { AdminRequest::validate($value, $schema); return false; }
    catch (InvalidArgumentException) { return true; }
};
$auth = $read('core/classes/Auth.php');
$admin = $read('public_html/admin/index.php');
$adminFns = $read('public_html/admin/incl/functions.php');
$bar = $read('core/classes/FrontendAdminBar.php');
$web = $read('public_html/.htaccess');
$adminHt = $read('public_html/admin/.htaccess');
$frontend = $read('public_html/index.php');
$galleryCtrl = $read('modules/gallery/admin/controllers/GalleryAdminController.php');
$formsCtrl = $read('modules/forms/admin/controllers/FormsAdminController.php');
$gallery = $read('modules/gallery/GalleryModule.php');
$renderer = $read('public_html/incl/helpers/page_builder_renderer.php');
$http = $read('tests/integration/http-smoke.sh');
$runner = $read('tests/integration/run.sh');
$dbSmoke = $read('tests/integration/db-smoke.php');
$workflows = $read('tests/integration/workflows.php');

// Negative behaviour probes: reject malformed input early and fail closed.
foreach (['javascript:alert(1)', 'data:text/html,boom', '//evil.test', '/safe\r\nHeader:bad', '/dir\\bad',
          ' https://site.test', 'https://name:pass@site.test', 'ftp://site.test/file'] as $unsafe) {
    $check(AdminEscaper::safeUrl($unsafe) === null, 'Unsafe URL rejected: ' . substr($unsafe, 0, 38));
}
$check(AdminEscaper::safeUrl('/admin/sider?mode=edit&id=7') !== null, 'Local admin URL accepted');
$check(AdminEscaper::safeUrl('https://example.test/page') !== null, 'Ordinary HTTPS URL accepted');
$check(AdminEscaper::text('<img src=x onerror=alert(1)>') === '&lt;img src=x onerror=alert(1)&gt;', 'HTML injection escaped');
$check(str_contains(AdminEscaper::attribute('" onfocus="evil'), '&quot;'), 'Attribute quote escaped');
$check($reject(['id'=>['1']], ['id'=>['type'=>'id']]), 'Array-shaped ID rejected');
$check($reject(['id'=>'99999999999999999999999999999'], ['id'=>['type'=>'id']]), 'Integer overflow rejected');
$check($reject(['status'=>'root'], ['status'=>['type'=>'enum','values'=>['draft','published']]]), 'Unknown state rejected');
$check($reject(['date'=>'2026-02-30'], ['date'=>['type'=>'date']]), 'Invalid date rejected');
$check($reject(['active'=>'yes'], ['active'=>['type'=>'bool']]), 'Ambiguous boolean rejected');
$check($reject(['email'=>['admin@example.test']], ['email'=>['type'=>'email']]), 'Array-shaped email rejected');
$check($reject(['link'=>'javascript:alert(1)'], ['link'=>['type'=>'url']]), 'Admin form unsafe link rejected');

foreach ([['anonymous', 'admin.access'], ['editor','users.manage'], ['editor','modules.manage'], ['editor','settings.manage'], ['admin','unknown.capability']] as $pair) {
    $check(!CapabilityRegistry::allows(...$pair), 'Unauthorized capability denied: ' . implode('/', $pair));
}
$check(CapabilityRegistry::allows('editor','content.manage'), 'Editor content permission retained');
$check(!CapabilityRegistry::isAllowedForModule('gallery','forms.manage'), 'Cross-module capability borrowing denied');
$check(CapabilityRegistry::isAllowedForModule('gallery','content.manage'), 'Module may declare Core capability');

$available = DashboardCards::available('editor', ['forms','gallery']);
$check(count($available) >= 4 && isset($available['galleries'],$available['forms']), 'Authorized module dashboard options available');
$check(count(DashboardCards::available('anonymous', ['forms','gallery'])) === 0, 'Anonymous metric registry empty');
$check(!isset(DashboardCards::available('editor',[])['galleries']), 'Inactive Gallery not offered');
$check(DashboardCards::sanitize(null, $available) === DashboardCards::DEFAULTS, 'Fresh preferences use four defaults');
$check($reject(['id'=>[]], ['id'=>['type'=>'id']]), 'Nested array payload rejected');
$valid = ['published','drafts','scheduled','media'];
$check(DashboardCards::validate($valid,$available)===$valid, 'Valid dashboard selection retained');
foreach ([['drafts','drafts','scheduled','media'], ['unregistered','drafts','scheduled','media'], ['drafts']] as $bad) {
    try { DashboardCards::validate($bad,$available); $invalid=false; }
    catch (InvalidArgumentException) { $invalid=true; }
    $check($invalid, 'Invalid dashboard selection rejected');
}

// Defense in depth: endpoint method, capability and CSRF gates, no arbitrary user IDs.
$check($all($admin, ["$" . "mode === 'dashboard-cards-ajax'", "'POST'", 'admin_verify_csrf(', "'Content-Type: application/json", "'Cache-Control: private, no-store'", '$service->save((int)$auth->id()']), 'AJAX dashboard: POST+CSRF+current-user only');
$check($all($admin, ["$" . "mode === 'dashboard-cards'", 'admin_redirect(', 'DashboardCards::validate(']), 'No-JS fallback retains PRG and server validation');
$check($all($auth, ['password_verify(', 'session_regenerate_id(true)', 'validateSessionUser()', '$this->sessions->valid(', '$this->sessions->revoke(', "'httponly' => true", "'samesite' => 'Lax'"]), 'Authentication and persistent-cookie gates retained');
$check($all($adminFns, ['hash_equals(', 'random_bytes(32)', "'admin_csrf'"]), 'Session-bound CSRF generated and verified');
$check($all($admin, ['$auth->canAccessAdmin()', '$auth->mustChangePassword()', 'AdminRequest::csrfValid(', "'capability'"]), 'Module requests pass central authorization and CSRF');
$check($all($galleryCtrl, ['admin_verify_csrf(', 'POST']), 'Gallery mutations validate CSRF');
$check($all($formsCtrl, ['admin_verify_csrf(', 'POST']), 'Forms admin mutations validate CSRF');
$check($all($bar, ['header(\'Vary: Cookie\'', "'Cache-Control: private, no-store", '$auth->canAccessAdmin()', '$auth->mustChangePassword()']), 'Frontend admin bar cache and auth guards retained');
$check($all($admin, ["header('Cache-Control: no-store", "header('X-Robots-Tag: noindex"]), 'Admin responses prohibit caching and indexing');
$check($all($web, ['uploads|assets/uploads', '[F,L,NC]', 'X-Frame-Options', 'X-Content-Type-Options']), 'Apache public upload execution and iframe restrictions');
$check($all($adminHt, ['RewriteRule ^(?:incl|views)', 'RewriteRule ^assets/scss']), 'Admin includes and source directories denied');
$check($all($renderer, ['page_builder_public_url(', 'page_builder_public_image_url(']), 'Legacy frontend URLs sanitized');
$check($all($gallery, ['lightbox_enabled', 'gallery']), 'Gallery Lightbox per-record contract retained');
$check($all($frontend, ['ScheduledContent::runOnTraffic()', 'FrontendAdminBar::prepareRequest']), 'Frontend traffic scheduler and auth bar accurately discovered');

// QA suite may NOT unintentionally execute frontend scheduler on the user's DB.
$check($all($http, ['CMS_TEST_PUBLIC_GET', 'ISOLATED_SITE_SCHEDULER_WRITES_OK', 'SKIP: public homepage']), 'HTTP smoke public GET opt-in exists');
$check(strpos($http,'if [ "${CMS_TEST_PUBLIC_GET:-}"') < strpos($http,"check '/' 200"), 'Homepage GET behind explicit opt-in');
$check($all($runner, ['verify-code)', 'verify-site)', 'verify-production)', 'verify-fixture)', 'boilerplate_integration_only','cms_test_only']), 'Safe test variants retain isolated fixture guard');
$check($all($dbSmoke, ["'user_dashboard_cards'", 'seven expected current PHP migrations', "'lightbox_enabled'", "'schema_migrations'"]), 'DB-smoke updated for RC4b and RC5 schema');
$check($all($workflows, ['new dashboard user receives defaults', 'dashboard cards persist per first user', 'dashboard cards persist per second user', 'Gallery Lightbox choice round-trips', 'synthetic accounts fully rolled back']), 'Cross-module transactional checks include latest releases');
$check($all($workflows, ['$db->beginTransaction()', '$db->rollback()', "require __DIR__ . '/fixture-guard.php'"]), 'Fixture writes remain guarded and rolled back');
$check($all($read('database/updates/20261019_200_dashboard_cards.php'), ['CREATE TABLE IF NOT EXISTS', 'ON DELETE CASCADE']), 'Dashboard migration remains restart-safe and dependent on user');
$check($all($read('modules/gallery/migrations/20261019_100_gallery_lightbox.php'), ['INFORMATION_SCHEMA.COLUMNS','ADD COLUMN lightbox_enabled']), 'Gallery migration remains restart-safe');
$check($read('public_html/admin/assets/scss/admin.scss') === $read('public_html/admin/assets/css/admin.css'), 'Admin SCSS/CSS remains byte-identical');
$check(is_file($root . '/docs/audits/V2.5.0-FINAL-INTEGRATION-SECURITY-QA.md'), 'Final QA findings and limitations documented');

printf("V2.5.0 Final Integration & Security QA: %d PASS / %d FAIL (no DB/HTTP/writes)\n",$pass,$fail);
exit($fail ? 1 : 0);
