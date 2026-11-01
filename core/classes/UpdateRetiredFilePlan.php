<?php

declare(strict_types=1);

/**
 * Explicit retirement instructions shipped inside an authenticated official release.
 * No directory-diff cleanup. A retired file must match its previous published hash.
 * The manifest contains no deletion instructions until a release deliberately retires code.
 */
final class UpdateRetiredFilePlan
{
    public const MEMBER = 'core/update-retired-files.json';
    private const MAX_BYTES = 16384;
    private const MAX_FILES = 50;

    /** @param array<string,int> $archiveFiles @return array<string,string> path => old sha256 */
    public static function fromZip(ZipArchive $zip, array $archiveFiles): array
    {
        // Older code-only releases may omit this optional manifest.
        // Absence never authorizes implicit cleanup.
        if (!isset($archiveFiles[self::MEMBER])) return [];
        $index = $archiveFiles[self::MEMBER];
        $info = $zip->statIndex($index);
        if (!is_array($info) || !is_int($info['size'] ?? null)
            || $info['size'] < 1 || $info['size'] > self::MAX_BYTES) {
            throw new RuntimeException('Invalid retired-files manifest size.');
        }
        $raw = $zip->getFromIndex($index, self::MAX_BYTES);
        if (!is_string($raw) || strlen($raw) !== $info['size']) {
            throw new RuntimeException('Cannot read retired-files manifest.');
        }
        return self::parse($raw, array_keys($archiveFiles));
    }

    /** @param list<string> $targetFiles @return array<string,string> path => old sha256 */
    public static function parse(string $raw, array $targetFiles): array
    {
        if (strlen($raw) < 1 || strlen($raw) > self::MAX_BYTES) {
            throw new RuntimeException('Invalid retired-files manifest.');
        }
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid retired-files JSON.', 0, $e);
        }
        if (!is_array($data) || count($data) !== 2 || ($data['schema'] ?? null) !== 1
            || !is_array($data['retire'] ?? null) || !array_is_list($data['retire'])
            || count($data['retire']) > self::MAX_FILES) {
            throw new RuntimeException('Invalid retired-files manifest format.');
        }
        $target = [];
        foreach ($targetFiles as $name) $target[strtolower($name)] = true;
        $retired = [];
        $seen = [];
        foreach ($data['retire'] as $item) {
            if (!is_array($item) || count($item) !== 2
                || !is_string($item['path'] ?? null)
                || !is_string($item['sha256'] ?? null)
                || !preg_match('/^[a-f0-9]{64}$/D', $item['sha256'])
                || !self::isOwnedCodePath($item['path'])) {
                throw new RuntimeException('Invalid or unowned retired-file entry.');
            }
            $path = $item['path'];
            $key = strtolower($path);
            if (isset($seen[$key]) || isset($target[$key])) {
                throw new RuntimeException('Conflicting retired-file path.');
            }
            $seen[$key] = true;
            $retired[$path] = $item['sha256'];
        }
        return $retired;
    }

    public static function isOwnedCodePath(string $path): bool
    {
        if (!UpdatePackageReview::isSafeMember('boilerplate/' . $path, false)) return false;
        // Only CMS-owned, executable PHP. Never uploads, user plugins, SQL,
        // .htaccess, hosting config, data, documentation or unknown directories.
        if (!preg_match('~^(?:core/(?:classes|bootstrap)/|public_html/(?:admin|incl)/|modules/(?:example|forms|gallery)/(?:admin|classes|core)/)(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.php$~D', $path)) return false;
        // Core entry points and the recovery engine must never be retired by
        // manifest, even when their old content hash matches.
        $protected = [
            'public_html/admin/index.php', 'public_html/incl/init.php',
            'core/classes/Autoload.php', 'core/classes/UpdateOneClickInstaller.php',
            'core/classes/UpdatePackageReview.php', 'core/classes/UpdateRetiredFilePlan.php',
            'core/classes/UpdateRecoveryBundle.php',
        ];
        return !in_array($path, $protected, true)
            && !str_starts_with($path, 'core/classes/Update');
    }
}
