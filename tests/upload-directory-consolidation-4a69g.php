<?php
/** 4A.6.9g safe source / isolated filesystem tests — no real DB/network/sites. */
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/core/classes/SiteBackup.php';
require_once $root . '/core/classes/MediaReferenceIndex.php';
require_once $root . '/core/classes/InstallerPreflight.php';
$tests = 0;
$check = static function (bool $ok, string $name) use (&$tests): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    $tests++; echo "PASS: $name\n";
};
$check(is_dir($root . '/public_html/uploads'), 'Current upload root exists');
$check(is_file($root . '/public_html/uploads/.htaccess'), 'Current root retains security policy');
$check(!file_exists($root . '/public_html/assets/uploads'), 'Fresh source does not ship old upload root');
$media = file_get_contents($root . '/core/classes/MediaLibrary.php');
$check(str_contains($media, "'/uploads/' . \$year . '/' . \$month"), 'Media writer targets current year/month folders');
$check(str_contains($media, "'/assets/uploads/'"), 'Media resolver retains legacy file compatibility');
$aliases = MediaReferenceIndex::pathAliases('/assets/uploads/2019/01/old.webp');
$check(in_array('/uploads/2019/01/old.webp', $aliases, true), 'Old image paths have current aliases');
$reverse = MediaReferenceIndex::pathAliases('/uploads/2026/10/new.webp');
$check(in_array('/assets/uploads/2026/10/new.webp', $reverse, true), 'Current images retain legacy reference protection');
$temp = sys_get_temp_dir() . '/cms-single-upload-' . bin2hex(random_bytes(8));
try {
    if (!mkdir($temp . '/uploads', 0700, true)) throw new RuntimeException('Fixture failed');
    file_put_contents($temp . '/uploads/.htaccess', '# local fixture');
    file_put_contents($temp . '/uploads/new.webp', 'CURRENT');
    $files = SiteBackup::uploadFiles($temp);
    $check(count($files) === 1 && isset($files['public_html/uploads/new.webp']), 'Fresh site backup contains only current media');
    $check(!isset($files['public_html/uploads/.htaccess']), 'Backup never includes current security policy');
    mkdir($temp . '/assets/uploads', 0700, true);
    file_put_contents($temp . '/assets/uploads/.htaccess', '# local fixture');
    file_put_contents($temp . '/assets/uploads/legacy.webp', 'OLD');
    $files = SiteBackup::uploadFiles($temp);
    $check(count($files) === 2 && isset($files['public_html/assets/uploads/legacy.webp']), 'Existing legacy files remain in full site backup');
    $check(!isset($files['public_html/assets/uploads/.htaccess']), 'Legacy security policy excluded from backup');
    $check(file_get_contents($files['public_html/uploads/new.webp']) === 'CURRENT', 'Current media remains unmodified');
    $check(file_get_contents($files['public_html/assets/uploads/legacy.webp']) === 'OLD', 'Legacy media remains unmodified');
} finally {
    foreach (['uploads/.htaccess', 'uploads/new.webp', 'assets/uploads/.htaccess', 'assets/uploads/legacy.webp'] as $relative) @unlink($temp . '/' . $relative);
    foreach (['assets/uploads', 'assets', 'uploads'] as $relative) @rmdir($temp . '/' . $relative);
    @rmdir($temp);
}
$check(str_contains(file_get_contents($root . '/tests/release/release_zip.py'), "EMPTY_RUNTIME_DIRS = ('public_html/uploads',)"), 'Packaging ships exactly one runtime upload directory');
$check(is_file($root . '/docs/deployment/UPLOAD-DIRECTORY-4A.6.9g.md'), 'Restore safety instructions present');
echo "4A.6.9g upload consolidation: $tests PASS / 0 FAIL\n";
