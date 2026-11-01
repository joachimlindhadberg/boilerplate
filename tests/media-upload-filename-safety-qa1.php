<?php

declare(strict_types=1);

/** Independent, filesystem-only test. No DB, PHP upload or CMS mutation. */
$root = dirname(__DIR__);
require_once $root . '/core/classes/MediaLibrary.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
    $checks++;
    echo 'PASS: ' . $message . "\n";
};

$dir = sys_get_temp_dir() . '/bp-media-names-' . bin2hex(random_bytes(8));
if (!mkdir($dir, 0700)) {
    throw new RuntimeException('Could not create test fixture.');
}

try {
    // Work through the real class methods. No constructor/Db required.
    $reflection = new ReflectionClass(MediaLibrary::class);
    $library = $reflection->newInstanceWithoutConstructor();
    $unique = $reflection->getMethod('uniqueStoredName');
    $occupied = $reflection->getMethod('pathOccupied');
    $safe = $reflection->getMethod('safeFileBase');

    $assert($safe->invoke($library, 'hello.PHP') === 'hello-php', 'Original basename normalized, including script-like fragments');

    $names = [];
    for ($i = 0; $i < 128; ++$i) {
        $name = $unique->invoke($library, $dir, 'test-image', 'jpg');
        if (!preg_match('/^test-image-[a-f0-9]{32}\.jpg$/D', $name)) {
            throw new RuntimeException('Unexpected generated filename: ' . $name);
        }
        if (isset($names[$name])) {
            throw new RuntimeException('Duplicate random filename.');
        }
        $names[$name] = true;
    }
    $assert(count($names) === 128, '128 random names have 128-bit hex suffix and are unique in fixture');

    $file = $dir . '/existing.jpg';
    file_put_contents($file, 'PRESERVE');
    $assert($occupied->invoke(null, $file) === true, 'Existing file is treated as occupied');
    $assert(file_get_contents($file) === 'PRESERVE', 'Collision check does not alter existing file');
    $assert($occupied->invoke(null, $dir . '/missing.jpg') === false, 'Unused filename is available');

    $directory = $dir . '/existing-directory.jpg';
    mkdir($directory, 0700);
    $assert($occupied->invoke(null, $directory) === true, 'Existing directory is treated as occupied');

    if (function_exists('symlink')) {
        $link = $dir . '/existing-link.jpg';
        if (@symlink($dir . '/nonexistent-target', $link)) {
            $assert($occupied->invoke(null, $link) === true, 'Dangling symlink is treated as occupied');
            unlink($link);
        }
    }

    $assert(str_contains(file_get_contents($root . '/core/classes/MediaLibrary.php'), 'if (self::pathOccupied($absolutePath))'), 'Upload path rechecks occupancy before moving file');
    $assert(str_contains(file_get_contents($root . '/core/classes/MediaLibrary.php'), 'move_uploaded_file($tmp, $absolutePath)'), 'Native PHP upload validation preserved');
    $assert(str_contains(file_get_contents($root . '/core/classes/MediaLibrary.php'), 'new finfo(FILEINFO_MIME_TYPE)'), 'Existing content MIME validation preserved');
    $assert(str_contains(file_get_contents($root . '/core/classes/MediaLibrary.php'), 'getimagesize($absolutePath)'), 'Existing image structural validation preserved');
    echo "RESULT: $checks PASS / 0 FAIL (filesystem-only, NO MySQL/HTTP)\n";
} finally {
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . '/' . $entry;
        if (is_dir($path) && !is_link($path)) rmdir($path);
        else unlink($path);
    }
    rmdir($dir);
}
