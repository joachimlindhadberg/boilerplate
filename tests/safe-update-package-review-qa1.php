<?php
/** Package review regression: never accesses a live DB or modifies CMS runtime files. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$pass=0; $fail=0;
$assert = static function(bool $condition, string $name) use (&$pass, &$fail):void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $name . "\n";
    $condition ? $pass++ : $fail++;
};
$valid = [
    'boilerplate/', 'boilerplate/README.md',
    'boilerplate/public_html/uploads/', 'boilerplate/public_html/uploads/.htaccess',
    'boilerplate/core/classes/MigrationRunner.php', 'boilerplate/database/updates/20261013_000_core_schema.php',
    'boilerplate/public_html/.user.ini',
];
foreach ($valid as $name) $assert(UpdatePackageReview::isSafeMember($name, str_ends_with($name, '/')), 'Valid source path: ' . $name);
$invalid = [
    'boilerplate/../../evil.php', 'boilerplate/core/../.env',
    'boilerplate/core//bad.php', 'boilerplate/core\\bad.php',
    'boilerplate/.env', 'boilerplate/.env.production',
    'boilerplate/public_html/uploads/shell.php', 'boilerplate/public_html/uploads/user.jpg',
    'boilerplate/tests/test.php', 'boilerplate/docs/releases/old.md',
    'boilerplate/core/cli/code-audit.php', 'boilerplate/public_html/assets/uploads/.htaccess',
    'boilerplate/core/.user.ini', 'boilerplate/public_html/admin/.user.ini', 'boilerplate/public_html/.user.ini/',
    'other/core/classes/Db.php', 'boilerplate/core/.git/config',
    "boilerplate/core/evil\0name.php", 'boilerplate/README.md/',
];
foreach ($invalid as $name) $assert(!UpdatePackageReview::isSafeMember($name, str_ends_with($name, '/')), 'Blocked source path: ' . str_replace("\0", '(NUL)', $name));
$review = new UpdatePackageReview($root);
$invalidSize = $review->inspect($root . '/README.md', UpdatePackageReview::MAX_ARCHIVE_BYTES + 1);
$assert($invalidSize['status'] === 'blocked', 'Size limit blocks before opening archive');
$nonexistent = $review->inspect('/not-a-real-zip-file.zip', 1);
$assert($nonexistent['status'] === 'blocked', 'Missing archive is blocked');
$assert($invalidSize['new_migrations'] === 0 && $invalidSize['sha256'] === '', 'Blocked archive never has trusted results');
$controller = file_get_contents($root . '/public_html/admin/tools.php');
$view = file_get_contents($root . '/public_html/admin/views/tools/partials/update-package-review.php');
$assert(is_string($controller) && str_contains($controller, "define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage')"), 'Central capability enforcement unchanged');
$assert(is_string($controller) && str_contains($controller, "admin_verify_csrf(\$_POST['csrf_token'] ?? null)"), 'Review POST requires CSRF');
$assert(is_string($controller) && str_contains($controller, 'is_uploaded_file('), 'Only actual PHP uploads are inspected');
$assert(is_string($controller) && !str_contains(substr($controller, strpos($controller, "if (\$mode === 'update')"), strpos($controller, "if (\$mode === 'activity')") - strpos($controller, "if (\$mode === 'update')")), '->apply('), 'Review action never invokes migrations apply');
$assert(is_string($view) && str_contains($view, 'admin_csrf_token()') && str_contains($view, 'class="admin-button admin-button--secondary"'), 'Review form uses shared Core CSRF and button');
$assert(is_string($view) && str_contains($view, "admin_icon('search', 'admin-button__icon')"), 'Search button SVG inherits Core sizing');
$assert(!str_contains((string)file_get_contents($root . '/core/classes/UpdatePackageReview.php'), 'extractTo('), 'No ZIP extraction API is used');
$assert(is_file($root . '/docs/deployment/SAFE-UPDATE-WORKFLOW.md'), 'Explicit safety documentation included');
$assert(str_contains((string)file_get_contents($root . '/tests/release/production_zip.py'), 'SAFE-UPDATE-WORKFLOW.md'), 'Production packaging includes safety documentation');
$locale=json_decode((string)file_get_contents($root.'/core/lang/translations.json'),true);
$assert(is_array($locale) && isset($locale['update_package.title']['da'],$locale['update_package.title']['en']), 'Translation keys registered');
if (!class_exists('ZipArchive')) {
    $report=$review->inspect($root . '/README.md', filesize($root . '/README.md'));
    $assert($report['status'] === 'blocked' && ($report['checks'][0]['label'] ?? '') === 'PHP ZipArchive', 'Missing PHP zip extension causes fail-closed report');
    echo "NOTE: ZIP-archive content tests require the optional PHP zip extension on a test host.\n";
}
echo "Safe Update Package Review: {$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
