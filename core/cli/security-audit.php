<?php

declare(strict_types=1);
/**
 * V2.4.4z22: source-only security and integrity audit.
 *
 * No bootstrap, database connection, HTTP requests, sessions, fixtures, migration,
 * filesystem mutations, or scanning of local .env / runtime user uploads.
 * Structural checks are NOT equivalent to penetration/integration testing.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(2);
}
if (array_diff(array_slice($argv, 1), ['--json', '--help'])) {
    fwrite(STDERR, "Usage: php core/cli/security-audit.php [--json|--help]\n");
    exit(2);
}
if (in_array('--help', $argv, true)) {
    echo "Read-only source audit; does not open a database, send HTTP or change files.\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
$results = [];
$add = static function (bool $ok, string $category, string $label) use (&$results): void {
    $results[] = ['result' => $ok ? 'PASS' : 'FAIL', 'category' => $category, 'name' => $label];
};
$src = static function (string $path) use ($root): string {
    // Deliberately explicit paths: never inspect the installed .env or user data.
    if (str_contains($path, '..') || str_starts_with($path, '/') || !preg_match('~^(?:core|public_html|modules|tests)/[a-zA-Z0-9_./-]+$~D', $path)) {
        throw new LogicException('Unsafe audit source path.');
    }
    $data = @file_get_contents($root . '/' . $path);
    if ($data === false) {
        return '';
    }
    return $data;
};
$includes = static fn(string $value, array $needles): bool => count(array_filter($needles, static fn(string $needle): bool => !str_contains($value, $needle))) === 0;
$exclude = static fn(string $value, array $needles): bool => count(array_filter($needles, static fn(string $needle): bool => str_contains($value, $needle))) === 0;

$runner = $src('tests/integration/run.sh');
$fixture = $src('tests/integration/fixture-guard.php');
$workflow = $src('tests/integration/workflows.php');
$smoke = $src('tests/integration/http-smoke.sh');
$verify = strpos($runner, '    verify)');
$verifyCode = strpos($runner, '    verify-code)');
$verifySite = strpos($runner, '    verify-site)');
$verifyFixture = strpos($runner, '    verify-fixture)');
$verifySecurity = strpos($runner, '    verify-security)');
$normalBranch = ($verify !== false && $verifyCode !== false) ? substr($runner, $verify, $verifyCode - $verify) : '';
$securityBranch = ($verifySecurity !== false && $verifyFixture !== false) ? substr($runner, $verifySecurity, $verifyFixture - $verifySecurity) : '';
$fixtureBranch = strpos($runner, 'fixture_db() {');
$fixtureBody = ($fixtureBranch !== false && $verify !== false) ? substr($runner, $fixtureBranch, $verify - $fixtureBranch) : '';

$add($includes($runner, ['verify-security)', '"$PHP_BIN" core/cli/security-audit.php']), 'Isolation', 'Dedicated security command invokes only source audit');
$add($securityBranch !== '' && $includes($securityBranch, ['security-audit.php']) &&
    $exclude($securityBranch, ['code_tests', 'normal_db', 'fixture_db', 'curl', 'http-smoke.sh', 'migrate.php', '--apply', 'verify-site']), 'Isolation', 'Security command has no database, fixture, migration or HTTP step');
$add($includes($runner, ['verify-code)', 'code_tests() {', 'normal_db() {', 'fixture_db() {']), 'Isolation', 'Existing code, site and fixture operations remain separate');
$add($normalBranch !== '' && $includes($normalBranch, ['code_tests', 'normal_db', 'http-smoke.sh']) && $exclude($normalBranch, ['fixture_db', 'verify-fixture', 'workflows.php', 'migrate.php --apply']), 'Isolation', 'Standard verify never invokes fixture workflow or migrations');
$add($includes($fixture, ["boilerplate_integration_only", "cms_test_only", 'SELECT CURRENT_USER()', 'cms_integration_marker']), 'Isolation', 'Synthetic database guard requires dedicated identity and marker');
$add($fixtureBody !== '' && strpos($fixtureBody, 'fixture-guard.php') !== false && strpos($fixtureBody, 'migrate.php --apply') !== false && strpos($fixtureBody, 'fixture-guard.php') < strpos($fixtureBody, 'migrate.php --apply'), 'Isolation', 'Fixture guard precedes any fixture migration');
$add($includes($workflow, ['$db->beginTransaction()', '$db->rollback()', "require __DIR__ . '/fixture-guard.php'"]) && $exclude($workflow, ['$db->commit(', 'TRUNCATE TABLE']), 'Isolation', 'Synthetic workflow has rollback and no commit');
$add($includes($smoke, ['--request GET', '--max-redirs 0', '--resolve', '--noproxy']) && $exclude($smoke, ['--request POST']), 'Isolation', 'HTTP smoke is GET-only (but normal scheduler side effects remain possible)');

$auth = $src('core/classes/Auth.php');
$index = $src('public_html/admin/index.php');
$init = $src('public_html/incl/init.php');
$adminFns = $src('public_html/admin/incl/functions.php');
$cap = $src('core/classes/CapabilityRegistry.php');
$limiter = $src('core/classes/RateLimiter.php');
$add($includes($auth, ['password_verify(', 'session_regenerate_id(true)', "'active'", "'admin.access'"]), 'Auth', 'Login checks password, active status, role and renews session ID');
$sessions = $src('core/classes/SessionManager.php');
$add($includes($auth, ['SessionManager::newSecret()', 'setRemember(', "'httponly' => true", "'samesite' => 'Lax'", "'secure' => " . '$this->secureCookie()'])
    && $includes($sessions, ['random_bytes(32)', "hash('sha256'", 'rotateRemember(', 'remember_expires_at > NOW()']),
    'Auth', 'Per-device Remember tokens are hashed/rotated and cookies use HttpOnly/SameSite/Secure');
$add($includes($init, ["session.use_strict_mode", "session.use_only_cookies", "'httponly' => true", "'samesite' => 'Lax'"]), 'Auth', 'Session defaults use strict cookies and deny JS access');
$add($includes($auth, ['validateSessionUser()', '$this->sessions->valid(', '$this->sessions->revoke(', 'session_destroy()']) && $includes($sessions, ['DELETE FROM auth_sessions', 'revokeAll(']), 'Auth', 'Session revalidates user and active token; logout and account changes revoke persistent login');
$add($includes($index, ["$" . "mode === 'authenticate'", 'admin_login_throttle(', 'admin_verify_csrf(', 'admin_rotate_csrf()']), 'Auth', 'Login endpoint uses CSRF, throttling and token rotation');
$add($includes($limiter, ['REMOTE_ADDR', 'hash_hmac(', 'INSERT INTO security_rate_limits', 'ON DUPLICATE KEY UPDATE']) && !str_contains($limiter, 'HTTP_X_FORWARDED_FOR'), 'Auth', 'Persistent rate limit uses direct remote address and keyed counters');
$add($includes($cap, ['return false;', 'isAllowedForModule(', 'ModuleManifestValidator::load(', "isset(self::CORE["]), 'Auth', 'Unknown role grants fail closed; module capabilities constrained');
$add($includes($index, ["$" . "auth->canAccessAdmin()", 'mustChangePassword()', "'csrf_token'", 'resolvedRoute', 'AdminRequest::csrfValid(']), 'Auth', 'Admin and module routing checks login, password reset, capabilities and POST CSRF');

$add($includes($adminFns, ['random_bytes(32)', 'hash_equals($stored, $token)', "'admin_csrf'", 'admin_rotate_csrf()']), 'Requests', 'Per-session random CSRF tokens are compared in constant time');
foreach (['pages', 'users', 'media', 'navigation', 'tools', 'settings', 'modules'] as $controller) {
    $content = $src('public_html/admin/' . $controller . '.php');
    $add($includes($content, ['REQUEST_METHOD', 'POST', 'admin_verify_csrf(']), 'Requests', 'Core ' . $controller . ' POST routes retain CSRF verification');
}
$add($includes($src('modules/forms/admin/controllers/FormsAdminController.php'), ['POST', 'admin_verify_csrf(']), 'Requests', 'Forms editor POST path retains CSRF gate');
$add($includes($src('modules/gallery/admin/controllers/GalleryAdminController.php'), ['POST', 'admin_verify_csrf(']), 'Requests', 'Gallery editor POST path retains CSRF gate');
$add($includes($src('core/classes/AdminRequest.php'), ['filter_var(', 'InvalidArgumentException', "'id'", "'enum'", "'bool'", 'csrfValid(']), 'Requests', 'Typed input contract rejects malformed values');
$add($includes($src('core/classes/AdminEscaper.php'), ['htmlspecialchars(', 'ENT_QUOTES', 'safeUrl(', "['http', 'https']"]), 'Requests', 'Escaping separates text from safe URL scheme validation');
$add($includes($adminFns, ["str_starts_with(" . '$path' . ", '//')", 'header(\'Location: \' . $path']), 'Requests', 'Admin redirects require local paths');

$media = $src('core/classes/MediaLibrary.php');
$refs = $src('core/classes/MediaReferenceIndex.php');
$add($includes($media, ['is_uploaded_file(', 'new finfo(FILEINFO_MIME_TYPE)', 'MAX_UPLOAD_BYTES', 'ALLOWED_MIME_TYPES', 'move_uploaded_file(']), 'Media', 'Uploads limit bytes and verify actual MIME before storage');
$add($includes($media, ['$this->db->beginTransaction()', '$this->db->rollback()', 'absolutePublicPath(', 'unlink(']), 'Media', 'Media update/delete include transactional and filesystem guards');
$add($includes($refs, ['FOR UPDATE', 'assertTriggersInstalled()', 'buildSnapshot()', 'Db::transactionActive()']), 'Media', 'Reference index checks trigger state under transactional lock');
$add($includes($refs, ['invalid_block_ids', 'missing_media_ids', 'unresolved_image_paths', 'missing_media_files']), 'Media', 'Read-only media integrity audit reports missing references and files');
$add($includes($src('core/classes/ModuleMediaReferences.php'), ['interface']) || str_contains($src('core/classes/ModuleMediaReferences.php'), 'static function'), 'Media', 'Module media reference contract is available');

$db = $src('core/classes/Db.php');
$backup = $src('core/classes/SiteBackup.php');
$zipper = $src('tests/release/release_zip.py');
$add($includes($db, ['$this->db->prepare($sql)', 'bind_param(', 'set_charset(\'utf8mb4\')']), 'Integrity', 'Database wrapper uses prepared statements and UTF-8');
$add($includes($db, ["PHP_SAPI !== 'cli'", 'createMigrationTrigger(']), 'Integrity', 'Raw trigger DDL restricted to CLI migration helper');
$add($includes($backup, ["'excludes' => ['.env'", "'uploads', 'assets/uploads'", 'upload_sha256', 'manifest.json']), 'Integrity', 'Backup manifest omits code/credentials and records upload checksums');
$add($includes($backup, ['is_link(', 'RuntimeException', 'create(', 'finally']), 'Integrity', 'Backup checks unsafe upload paths and has cleanup guard');
$add($includes($zipper, ["EXCLUDED_FILES = {'.env'", "'uploads', 'storage'", 'if p.is_symlink()', 'z.testzip()', 'FILE_MODE']), 'Integrity', 'Release packager excludes user data and rejects symlinks/CRC errors');
$add($includes($src('core/classes/MediaLibrary.php'), ['usage(', "'count' =>", 'DELETE FROM media']), 'Integrity', 'Media deletion checks usage before removing DB row');
$add($includes($src('public_html/incl/init.php'), ['set_exception_handler(', "ini_set('display_errors', '0')", "header_remove('X-Powered-By')"]), 'Integrity', 'Production exceptions hide internals from visitors');

$publicHt = $src('public_html/.htaccess');
$adminHt = $src('public_html/admin/.htaccess');
$add($includes($publicHt, ['RewriteRule ^(?:uploads|assets/uploads)', '[F,L,NC]', 'RewriteRule ^incl', 'RewriteRule ^admin/(?:incl|views)']), 'Web', 'Apache blocks executable uploads and internal PHP source paths');
$add($includes($publicHt, ['X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy']), 'Web', 'Default Apache security headers declared');
$add($includes($adminHt, ['RewriteRule ^(?:incl|views)', 'RewriteRule ^assets/scss']), 'Web', 'Admin .htaccess denies direct view/include/source access');
$add($includes($src('public_html/module-assets.php'), ['resolvePublicScript(', 'X-Content-Type-Options', 'readfile($path)']), 'Web', 'Module static file endpoint delegates allowlist lookup');
$add($includes($src('public_html/admin/index.php'), ["header('Cache-Control: no-store", "header('X-Robots-Tag: noindex"]), 'Web', 'Admin login/response disables caches and search indexing');

// Safe direct behavior probes: pure statics, do not bootstrap the CMS or construct DB objects.
require_once $root . '/core/classes/AdminEscaper.php';
require_once $root . '/core/classes/AdminRequest.php';
require_once $root . '/core/classes/CapabilityRegistry.php';
$reject = static function (array $source, array $schema): bool {
    try { AdminRequest::validate($source, $schema); return false; }
    catch (InvalidArgumentException) { return true; }
};
$add($reject(['id' => ['5']], ['id' => ['type' => 'id']]), 'Behavior', 'Array-shaped ID is rejected');
$add($reject(['id' => '-4'], ['id' => ['type' => 'id']]), 'Behavior', 'Negative ID is rejected');
$add($reject(['status' => 'deleted'], ['status' => ['type' => 'enum', 'values' => ['active', 'inactive']]]), 'Behavior', 'Unknown enum is rejected');
$add($reject(['visible' => 'yes'], ['visible' => ['type' => 'bool']]), 'Behavior', 'Ambiguous boolean is rejected');
$add($reject(['date' => '2026-02-30'], ['date' => ['type' => 'date']]), 'Behavior', 'Invalid calendar date is rejected');
$add($reject(['url' => 'javascript:alert(1)'], ['url' => ['type' => 'url']]), 'Behavior', 'Unsafe URL field is rejected');
$add(AdminEscaper::safeUrl('//attacker.test') === null && AdminEscaper::safeUrl("/admin\r\nLocation: bad") === null, 'Behavior', 'Protocol-relative and header-injection URLs rejected');
$add(AdminEscaper::text('<script>') === '&lt;script&gt;', 'Behavior', 'HTML output is escaped');
$add(CapabilityRegistry::allows('editor', 'users.manage') === false && CapabilityRegistry::allows('anonymous', 'admin.access') === false, 'Behavior', 'Editor and anonymous roles cannot manage users/admin');
$add(CapabilityRegistry::allows('admin', 'unknown.capability') === false, 'Behavior', 'Unregistered capabilities fail closed');

$add(preg_match("~define\\('BOILERPLATE_VERSION', '([0-9.]+)'\\)~", $init, $versionMatch) && version_compare($versionMatch[1], '2.4.4.108', '>='), 'Release', 'Internal version 2.4.4.108');
$add($src('public_html/admin/assets/scss/admin.scss') === $src('public_html/admin/assets/css/admin.css'), 'Release', 'Core SCSS/CSS byte-identical');
$add(is_file($root . '/docs/audits/SECURITY-DATA-INTEGRITY-AUDIT-V2.4.4z22.md'), 'Release', 'Prioritized security findings documented');
$add(is_file($root . '/docs/releases/SECURITY-DATA-INTEGRITY-AUDIT-V2.4.4z22.md'), 'Release', 'Release notes present');

if (in_array('--json', $argv, true)) {
    echo json_encode(['checks' => $results, 'summary' => [
        'passed' => count(array_filter($results, static fn(array $r): bool => $r['result'] === 'PASS')),
        'failed' => count(array_filter($results, static fn(array $r): bool => $r['result'] === 'FAIL')),
        'scope' => 'source-only; no DB / HTTP / fixture / mutation',
    ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} else {
    foreach ($results as $item) echo '[' . $item['result'] . '] ' . $item['category'] . ' — ' . $item['name'] . "\n";
    $failed = count(array_filter($results, static fn(array $r): bool => $r['result'] === 'FAIL'));
    echo 'Security & Data Integrity source audit: ' . (count($results) - $failed) . ' PASS / ' . $failed . ' FAIL (no DB / HTTP / writes)' . "\n";
}
exit(in_array('FAIL', array_column($results, 'result'), true) ? 1 : 0);
