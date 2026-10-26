<?php
/** Source and isolated filesystem regression for 4A.6.6a (no DB, network or site writes). */
$root = dirname(__DIR__);
require_once $root . '/core/classes/SiteBackup.php';
$checks = 0;
$assert = static function (bool $ok, string $name) use (&$checks): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    ++$checks;
};
$policies = [];
foreach (['public_html/uploads/.htaccess'] as $relative) {
    $path = $root . '/' . $relative;
    $policy = (string)file_get_contents($path);
    $policies[] = $policy;
    $assert(str_contains($policy, 'Options -Indexes -ExecCGI'), 'deny indexing and CGI');
    $assert(str_contains($policy, 'SetHandler default-handler'), 'force static handler');
    $assert(str_contains($policy, 'Require all denied'), 'reject executable extensions');
    $assert(str_contains($policy, '<FilesMatch "^\\.">'), 'reject dotfiles without depending on root rewrite');
}
$assert(count($policies) === 1, 'new installations ship a single protected upload root');
$assert(!file_exists($root . '/public_html/assets/uploads'), 'fresh source checkout has no legacy upload directory');
$rootRules = (string)file_get_contents($root . '/public_html/.htaccess');
$assert(str_contains($rootRules, '(?:\\.|$)'), 'root rewrite handles multi-extension filenames');
$dir = sys_get_temp_dir() . '/cms-upload-defense-' . bin2hex(random_bytes(8));
try {
    if (!mkdir($dir . '/uploads', 0700, true) || !mkdir($dir . '/assets/uploads', 0700, true)) {
        throw new RuntimeException('Cannot create test fixture directories');
    }
    file_put_contents($dir . '/uploads/.htaccess', 'Options -Indexes');
    file_put_contents($dir . '/assets/uploads/.htaccess', 'Options -Indexes');
    file_put_contents($dir . '/uploads/a.jpg', 'DUMMY');
    file_put_contents($dir . '/assets/uploads/b.png', 'DUMMY');
    $files = SiteBackup::uploadFiles($dir);
    $assert(count($files) === 2, 'backup excludes security config but includes two media files');
    $assert(!isset($files['public_html/uploads/.htaccess']), 'current upload policy excluded from backup');
    $assert(!isset($files['public_html/assets/uploads/.htaccess']), 'legacy upload policy excluded from backup');
} finally {
    foreach (['uploads/.htaccess', 'assets/uploads/.htaccess', 'uploads/a.jpg', 'assets/uploads/b.png'] as $rel) {
        @unlink($dir . '/' . $rel);
    }
    @rmdir($dir . '/uploads'); @rmdir($dir . '/assets/uploads'); @rmdir($dir . '/assets'); @rmdir($dir);
}
echo "Upload Directory Defense 4A.6.6a: {$checks} PASS / 0 FAIL\n";
