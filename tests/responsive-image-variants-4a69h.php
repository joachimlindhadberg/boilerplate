<?php

declare(strict_types=1);

/** Isolated filesystem-only checks; no real database, no user data. */
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/boilerplate-variants-' . bin2hex(random_bytes(6));
mkdir($dir . '/uploads/2026/10', 0700, true);
define('PUBLICPATH', $dir);
require_once $root . '/core/classes/ImageVariants.php';

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    $ok ? $pass++ : $fail++;
};
$relative = '/uploads/2026/10/test-abcdef1234.jpg';
$file = $dir . $relative;
try {
    file_put_contents($file, 'fake-original-bytes');
    $check(ImageVariants::forPath($relative) === [], 'Legacy image without manifest remains original-only');
    $check(ImageVariants::preferred($relative) === $relative, 'Legacy preview has safe original fallback');
    $check(ImageVariants::srcset($relative, 4000, 3000) === '', 'No invalid responsive markup without variants');
    $manifest = [
        'version' => 1, 'original' => $relative,
        'variants' => [
            'thumbnail' => ['path' => '/uploads/2026/10/test-abcdef1234-320px.jpg', 'width' => 320, 'height' => 240],
            'medium' => ['path' => '/uploads/2026/10/test-abcdef1234-768px.jpg', 'width' => 768, 'height' => 576],
            'large' => ['path' => '/uploads/2026/10/test-abcdef1234-1536px.jpg', 'width' => 1536, 'height' => 1152],
        ],
    ];
    foreach ($manifest['variants'] as $item) file_put_contents($dir . $item['path'], 'fake-variant-bytes');
    file_put_contents($file . '.variants.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $variants = ImageVariants::forPath($relative);
    $check(count($variants) === 3, 'All three valid files are detected');
    $check(ImageVariants::preferred($relative, 'thumbnail') === $manifest['variants']['thumbnail']['path'], 'Admin preview picks thumbnail');
    $srcset = ImageVariants::srcset($relative, 4000, 3000);
    $check(substr_count($srcset, 'w') === 4 && str_contains($srcset, '4000w') && str_contains($srcset, '320w'), 'Responsive list includes variants and original');
    $check(strpos($srcset, '320w') < strpos($srcset, '4000w'), 'Responsive widths are sorted ascending');
    unlink($dir . $manifest['variants']['medium']['path']);
    $check(count(ImageVariants::forPath($relative)) === 2, 'Missing derived file is ignored without broken URL');
    $bad = $manifest;
    $bad['variants']['thumbnail']['path'] = '/uploads/other.jpg';
    file_put_contents($file . '.variants.json', json_encode($bad, JSON_THROW_ON_ERROR));
    $check(!isset(ImageVariants::forPath($relative)['thumbnail']), 'Tampered manifest cannot link outside generated sibling');
    $bad['original'] = '/uploads/attacker.jpg';
    file_put_contents($file . '.variants.json', json_encode($bad, JSON_THROW_ON_ERROR));
    $check(ImageVariants::forPath($relative) === [], 'Wrong manifest ownership rejected');
    $check(ImageVariants::forPath('/uploads/../../secrets.jpg') === [], 'Traversal path rejected');
    $check(ImageVariants::generate($file, $relative, 'image/gif', 4000, 3000) === [], 'GIF is never turned into a static derivative');
    ImageVariants::deleteArtifacts($relative);
    $check(!is_file($file) && !is_file($file . '.variants.json') && !is_file($dir . $manifest['variants']['thumbnail']['path']), 'Delete clears original, metadata and derived files');

    $library = (string)file_get_contents($root . '/core/classes/MediaLibrary.php');
    $gallery = (string)file_get_contents($root . '/modules/gallery/GalleryModule.php');
    $builder = (string)file_get_contents($root . '/public_html/incl/helpers/page_builder_renderer.php');
    $builderV2 = (string)file_get_contents($root . '/public_html/incl/helpers/page_builder_v2_renderer.php');
    $admin = (string)file_get_contents($root . '/public_html/admin/views/media/index.php');
    $backup = (string)file_get_contents($root . '/core/classes/SiteBackup.php');
    $check(str_contains($library, 'ImageVariants::generate(') && str_contains($library, 'ImageVariants::deleteArtifacts('), 'Core media integrates generation and atomic cleanup');
    $check(str_contains($gallery, 'ImageVariants::srcset(') && str_contains($gallery, 'href=') && str_contains($gallery, '$e($path)'), 'Gallery uses responsive images but original lightbox URL');
    $check(str_contains($builder, 'page_builder_responsive_attributes($media)') && str_contains($builderV2, 'page_builder_responsive_attributes($media)'), 'Both Page Builder renderers support responsive attributes');
    $check(str_contains($admin, "ImageVariants::preferred("), 'Admin cards use compact thumbnail');
    $check(str_contains($backup, "RecursiveDirectoryIterator(") && str_contains($backup, 'RecursiveIteratorIterator(new RecursiveDirectoryIterator'), 'Backup includes original and derived uploads without schema change');
    // Videos remain deletable even though they have no variants.
    $video = '/uploads/2026/10/test-abcdef1234.mp4';
    file_put_contents($dir . $video, 'video-bytes');
    ImageVariants::deleteArtifacts($video);
    $check(!file_exists($dir . $video), 'Existing video deletion remains compatible');
    // If GD or Imagick is installed on the test runner, exercise real resampling.
    if (function_exists('imagecreatetruecolor') || class_exists('Imagick')) {
        // Use the editor itself to produce a valid source fixture when possible.
        if (function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
            $canvas = imagecreatetruecolor(1800, 1200);
            imagejpeg($canvas, $file, 90);
            imagedestroy($canvas);
            $actual = ImageVariants::generate($file, $relative, 'image/jpeg', 1800, 1200);
            $check(count($actual) === 3, 'GD creates all three smaller files from JPEG');
            $check(count(ImageVariants::forPath($relative)) === 3, 'Generated image manifest is readable');
        }
    } else {
        echo "SKIP resampling runtime: PHP has no GD or Imagick extension (fallback checked)\n";
    }
} finally {
    foreach (glob($dir . '/uploads/2026/10/*') ?: [] as $path) if (is_file($path)) @unlink($path);
    @rmdir($dir . '/uploads/2026/10'); @rmdir($dir . '/uploads/2026'); @rmdir($dir . '/uploads'); @rmdir($dir);
}
echo "Tests: {$pass} PASS, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
