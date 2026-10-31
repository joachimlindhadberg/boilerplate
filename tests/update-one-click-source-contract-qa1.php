<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$assert = static function (bool $condition, string $label) use (&$count): void {
    if (!$condition) { fwrite(STDERR, "FAIL $label\n"); exit(1); }
    $count = ($count ?? 0) + 1;
    echo "PASS $label\n";
};
$controller = $read('public_html/admin/tools.php');
$view = $read('public_html/admin/views/tools/update.php');
$installer = $read('core/classes/UpdateOneClickInstaller.php');
$packager = $read('tests/release/production_zip.py');
$assert(str_contains($controller, "define('ADMIN_REQUIRED_CAPABILITY', 'tools.manage')"), 'tools.manage authorization retained');
$assert(str_contains($controller, "'install_official_update'") && str_contains($controller, 'admin_verify_csrf'), 'installer action is CSRF guarded');
$assert(str_contains($controller, 'UpdateOfficialReleasePackage())->prepareSelected($expected, $kind)'), 'official pinned-source preparation reused');
$assert(str_contains($controller, 'releaseCurrentHttpReadersForUpdate') && str_contains($controller, 'acquireExclusive'), 'exclusive maintenance lease acquired');
$assert(str_contains($installer, '(new UpdateRecoveryBundle($this->root))->create()'), 'recovery backup before application');
$assert(str_contains($installer, "'new_migrations'") && str_contains($installer, "'unsupported_release'"), 'new migrations fail closed');
$assert(str_contains($installer, 'hash_file') && str_contains($installer, 'restoreInterrupted'), 'digest verification and explicit recovery included');
$assert(str_contains($view, 'updates.update_selected') && !str_contains($view, 'type="file"'), 'minimal one-click Core UI without ZIP upload');
$assert(str_contains($packager, "core/classes/UpdateRecoveryBundle.php") && str_contains($packager, "core/classes/UpdateOneClickInstaller.php"), 'required runtime classes shipped');
require_once $root . '/core/classes/UpdatePackageReview.php';
$assert(UpdatePackageReview::isSafeMember('boilerplate/LICENSE', false), 'distribution LICENSE allowed');
$assert(!UpdatePackageReview::isSafeMember('boilerplate/.env', false), 'private config refused in release');
echo "RESULT: $count PASS / 0 FAIL (SOURCE CONTRACT ONLY)\n";
