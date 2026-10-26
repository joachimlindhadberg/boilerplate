<?php

declare(strict_types=1);

/**
 * Core-owned, additive responsive derivatives. Original uploads are immutable.
 * The adjacent JSON manifest is ordinary backed-up upload data, NOT executable PHP.
 * No database migration; older images without manifests continue to render normally.
 */
final class ImageVariants
{
    private const PRESETS = ['thumbnail' => 320, 'medium' => 768, 'large' => 1536];
    private const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Return generated metadata, or [] if no safe/available image editor. */
    public static function generate(string $absolute, string $publicPath, string $mime, int $width, int $height): array
    {
        if (!isset(self::MIME[$mime]) || !self::safePath($publicPath)
            || $width < 1 || $height < 1 || $width > 50000 || $height > 50000
            || $width * $height > 40000000 || !is_file($absolute) || is_link($absolute)) return [];
        $dir = dirname($absolute);
        $stem = pathinfo($absolute, PATHINFO_FILENAME);
        if ($stem === '' || !is_dir($dir) || is_link($dir)) return [];
        $available = (function_exists('imagecreatetruecolor') && self::gdSupported($mime)) || class_exists('Imagick');
        if (!$available) return [];

        // JPEG EXIF rotation is not baked into the original bitmap. Skipping
        // transformed sources avoids inconsistent aspect ratios in srcset.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($absolute, 'IFD0');
            if (is_array($exif) && (int)($exif['Orientation'] ?? 1) !== 1) return [];
        }
        $generated = [];
        try {
            foreach (self::PRESETS as $name => $max) {
                if (max($width, $height) <= $max) continue;
                $scale = $max / max($width, $height);
                $w = max(1, (int)round($width * $scale));
                $h = max(1, (int)round($height * $scale));
                $file = $stem . '-' . $max . 'px.' . self::MIME[$mime];
                $target = $dir . '/' . $file;
                if (file_exists($target) || is_link($target)) throw new RuntimeException('Unexpected image variant filename collision.');
                // Keep all intermediate writes within the same filesystem.
                $temp = tempnam($dir, '.img-');
                if ($temp === false) throw new RuntimeException('Cannot create image variant temporary file.');
                try {
                    if (!self::resize($absolute, $temp, $mime, $width, $height, $w, $h)) {
                        @unlink($temp);
                        throw new RuntimeException('Image editor could not resize image.');
                    }
                    $info = @getimagesize($temp);
                    if (!is_array($info) || (int)$info[0] !== $w || (int)$info[1] !== $h
                        || (string)($info['mime'] ?? '') !== $mime) {
                        throw new RuntimeException('Generated image failed dimension or MIME validation.');
                    }
                    @chmod($temp, 0644);
                    if (!rename($temp, $target)) throw new RuntimeException('Cannot publish resized image.');
                } finally {
                    if (is_file($temp)) @unlink($temp);
                }
                $generated[$name] = [
                    'path' => dirname($publicPath) . '/' . $file,
                    'width' => $w, 'height' => $h,
                    'size' => (int)(filesize($target) ?: 0),
                ];
            }
            if ($generated) {
                $manifest = ['version' => 1, 'original' => $publicPath, 'variants' => $generated];
                $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $temp = tempnam($dir, '.imgmeta-');
                if ($temp === false) throw new RuntimeException('Cannot create media metadata.');
                try {
                    if (file_put_contents($temp, $json, LOCK_EX) !== strlen($json)) throw new RuntimeException('Cannot save media metadata.');
                    @chmod($temp, 0644);
                    if (!rename($temp, self::manifestPath($absolute))) throw new RuntimeException('Cannot publish media metadata.');
                } finally {
                    if (is_file($temp)) @unlink($temp);
                }
            }
            return $generated;
        } catch (Throwable $error) {
            foreach ($generated as $item) {
                $file = $dir . '/' . basename((string)$item['path']);
                if (is_file($file) && !is_link($file)) @unlink($file);
            }
            error_log('Image variants skipped: ' . $error->getMessage());
            return [];
        }
    }

    /** Read only same-directory, genuinely existing variants; never trust JSON paths. */
    public static function forPath(string $publicPath): array
    {
        if (!self::safePath($publicPath) || !defined('PUBLICPATH')) return [];
        $absolute = rtrim(PUBLICPATH, '/') . $publicPath;
        if (!is_file($absolute) || is_link($absolute)) return [];
        $manifestFile = self::manifestPath($absolute);
        if (!is_file($manifestFile) || is_link($manifestFile) || filesize($manifestFile) > 8192) return [];
        $data = json_decode((string)@file_get_contents($manifestFile), true);
        if (!is_array($data) || ($data['version'] ?? null) !== 1
            || ($data['original'] ?? null) !== $publicPath || !is_array($data['variants'] ?? null)) return [];
        $result = [];
        $stem = pathinfo($absolute, PATHINFO_FILENAME);
        $extension = pathinfo($absolute, PATHINFO_EXTENSION);
        foreach (self::PRESETS as $name => $max) {
            $variant = $data['variants'][$name] ?? null;
            if (!is_array($variant)) continue;
            $expected = dirname($publicPath) . '/' . $stem . '-' . $max . 'px.' . $extension;
            $width = $variant['width'] ?? null;
            $height = $variant['height'] ?? null;
            if (($variant['path'] ?? null) !== $expected || !is_int($width) || !is_int($height)
                || $width < 1 || $height < 1 || max($width, $height) !== $max) continue;
            $candidate = dirname($absolute) . '/' . basename($expected);
            if (!is_file($candidate) || is_link($candidate)) continue;
            $result[$name] = ['path' => $expected, 'width' => $width, 'height' => $height];
        }
        return $result;
    }

    /** Browser chooses the smallest suitable resource; original stays available. */
    public static function srcset(string $publicPath, int $originalWidth, int $originalHeight): string
    {
        if ($originalWidth < 1 || $originalHeight < 1) return '';
        $variants = self::forPath($publicPath);
        if (!$variants) return '';
        $items = [];
        foreach ($variants as $variant) {
            // Same aspect ratio within rounding tolerance; no cross-crop mix.
            if (abs($variant['width'] * $originalHeight - $variant['height'] * $originalWidth)
                > max($originalWidth, $originalHeight)) continue;
            $items[(int)$variant['width']] = $variant['path'] . ' ' . $variant['width'] . 'w';
        }
        if (!$items) return '';
        $items[$originalWidth] = $publicPath . ' ' . $originalWidth . 'w';
        ksort($items, SORT_NUMERIC);
        return implode(', ', $items);
    }

    public static function preferred(string $publicPath, string $preset = 'medium'): string
    {
        $variants = self::forPath($publicPath);
        return (string)($variants[$preset]['path'] ?? $publicPath);
    }

    /** Clean only predictable sibling files. Called after DB delete, or on rollback. */
    public static function deleteArtifacts(string $publicPath): void
    {
        if (!self::safePath($publicPath) || !defined('PUBLICPATH')) return;
        $absolute = rtrim(PUBLICPATH, '/') . $publicPath;
        $stem = pathinfo($absolute, PATHINFO_FILENAME);
        $extension = pathinfo($absolute, PATHINFO_EXTENSION);
        foreach (array_key_exists(strtolower($extension), ['jpg'=>1,'jpeg'=>1,'png'=>1,'webp'=>1]) ? self::PRESETS : [] as $max) {
            $file = dirname($absolute) . '/' . $stem . '-' . $max . 'px.' . $extension;
            if (is_file($file) && !is_link($file) && !@unlink($file)) error_log('Could not remove generated image: ' . $file);
        }
        $manifest = self::manifestPath($absolute);
        if (is_file($manifest) && !is_link($manifest) && !@unlink($manifest)) error_log('Could not remove generated metadata: ' . $manifest);
        if (is_file($absolute) && !is_link($absolute) && !@unlink($absolute)) error_log('Could not remove media original: ' . $absolute);
    }

    private static function manifestPath(string $absolute): string
    {
        return $absolute . '.variants.json';
    }

    private static function safePath(string $path): bool
    {
        return (str_starts_with($path, '/uploads/') || str_starts_with($path, '/assets/uploads/'))
            && !str_contains($path, '..') && !str_contains($path, '\\')
            && preg_match('~^/(?:uploads|assets/uploads)/[a-zA-Z0-9_./-]+\.(?:jpe?g|png|webp|gif|mp4|webm|mov)$~D', $path) === 1;
    }

    private static function gdSupported(string $mime): bool
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            'image/png' => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            'image/webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            default => false,
        };
    }

    private static function resize(string $source, string $target, string $mime, int $width, int $height, int $w, int $h): bool
    {
        if (self::gdSupported($mime)) {
            // GD may allocate 4+ bytes per pixel per bitmap. Fail safely on hosts
            // with small memory_limit rather than crashing their PHP-FPM worker.
            $limit = ini_get('memory_limit');
            if (is_string($limit) && $limit !== '-1') {
                $bytes = (int)$limit;
                $unit = strtolower(substr(trim($limit), -1));
                if ($unit === 'g') $bytes *= 1024 * 1024 * 1024;
                elseif ($unit === 'm') $bytes *= 1024 * 1024;
                elseif ($unit === 'k') $bytes *= 1024;
                if ($bytes > 0 && ($bytes - memory_get_usage(true)) < (int)(($width * $height + $w * $h) * 6 + 16777216)) return false;
            }
            $from = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($source),
                'image/png' => @imagecreatefrompng($source),
                'image/webp' => @imagecreatefromwebp($source),
            };
            if (!$from) return false;
            $to = imagecreatetruecolor($w, $h);
            if (!$to) return false;
            try {
                if ($mime !== 'image/jpeg') {
                    imagealphablending($to, false);
                    imagesavealpha($to, true);
                    $transparent = imagecolorallocatealpha($to, 0, 0, 0, 127);
                    imagefilledrectangle($to, 0, 0, $w, $h, $transparent);
                }
                if (!imagecopyresampled($to, $from, 0, 0, 0, 0, $w, $h, $width, $height)) return false;
                return match ($mime) {
                    'image/jpeg' => imagejpeg($to, $target, 82),
                    'image/png' => imagepng($to, $target, 6),
                    'image/webp' => imagewebp($to, $target, 82),
                };
            } finally {
                imagedestroy($to);
                imagedestroy($from);
            }
        }
        if (class_exists('Imagick')) {
            $img = new Imagick();
            try {
                $img->readImage($source);
                if ($img->getNumberImages() !== 1) return false;
                $img->thumbnailImage($w, $h, true, true);
                $img->setImageFormat(self::MIME[$mime] === 'jpg' ? 'jpeg' : self::MIME[$mime]);
                $img->setImageCompressionQuality(82);
                return $img->writeImage($target);
            } finally {
                $img->clear();
            }
        }
        return false;
    }
}
