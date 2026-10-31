<?php

declare(strict_types=1);

/**
 * Read-only validation of SiteBackup's SQL + media archive.
 * Does not extract SQL, run a migration, restore files, or access the database.
 */
final class UpdateRecoveryDataArchive
{
    private const MAX_ARCHIVE_BYTES = 4294967296; // 4 GiB compressed
    private const MAX_TOTAL_BYTES = 8589934592; // 8 GiB expanded
    private const MAX_MEMBERS = 100002;
    private const MAX_MANIFEST_BYTES = 8388608;

    /** @return array{sha256:string,files:int,sql_sha256:string,tables:int,triggers:int} */
    public function verify(string $path): array
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Missing or unsafe site data recovery archive.');
        }
        $compressed = filesize($path);
        if (!is_int($compressed) || $compressed < 1 || $compressed > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Data recovery archive exceeds permitted size.');
        }
        // Phar canonicalizes symlinked parent paths (macOS /var -> /private/var).
        // Anchor every member check to the same canonical archive location.
        $archivePath = realpath($path);
        if ($archivePath === false || !is_file($archivePath)) {
            throw new RuntimeException('Cannot resolve site data recovery archive.');
        }
        try {
            $tar = new PharData($archivePath);
            if (!isset($tar['manifest.json']) || !isset($tar['database.sql'])
                || !$tar['manifest.json']->isFile() || !$tar['database.sql']->isFile()
                || $tar['manifest.json']->getSize() > self::MAX_MANIFEST_BYTES
                || $tar['database.sql']->getSize() === 0) {
                throw new RuntimeException('Data recovery archive lacks SQL or manifest.');
            }
            $manifest = json_decode($tar['manifest.json']->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'boilerplate-site-data-backup'
                || ($manifest['version'] ?? null) !== 1
                || ($manifest['contains_sensitive_data'] ?? null) !== true
                || !is_array($manifest['upload_sha256'] ?? null)
                || !is_array($manifest['database_tables'] ?? null)
                || !is_int($manifest['database_triggers'] ?? null)
                || !is_string($manifest['database_sha256'] ?? null)
                || !self::isDigest($manifest['database_sha256'])
                || !is_int($manifest['upload_files'] ?? null)
                || $manifest['upload_files'] !== count($manifest['upload_sha256'])) {
                throw new RuntimeException('Invalid site data backup manifest.');
            }
            $uploadHashes = $manifest['upload_sha256'];
            if (count($uploadHashes) + 2 > self::MAX_MEMBERS) {
                throw new RuntimeException('Too many recovery archive members.');
            }
            foreach ($manifest['database_tables'] as $table => $count) {
                if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/D', $table)
                    || !is_int($count) || $count < 0) {
                    throw new RuntimeException('Invalid database table inventory.');
                }
            }
            if ($manifest['database_tables'] === [] || $manifest['database_triggers'] < 0) {
                throw new RuntimeException('Empty database backup inventory.');
            }
            $expected = ['manifest.json' => true, 'database.sql' => true];
            foreach ($uploadHashes as $member => $digest) {
                if (!is_string($member) || !self::safeUploadPath($member) || !self::isDigest($digest)) {
                    throw new RuntimeException('Invalid media inventory path or digest.');
                }
                $expected[$member] = true;
            }
            $seen = [];
            $expanded = 0;
            $iterator = new RecursiveIteratorIterator($tar, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $entry) {
                $pathName = str_replace('\\', '/', $entry->getPathname());
                $prefix = 'phar://' . $archivePath . '/';
                if (!str_starts_with($pathName, $prefix)) {
                    throw new RuntimeException('Unexpected recovery archive location.');
                }
                $name = substr($pathName, strlen($prefix));
                if (!self::safePath($name) || $entry->isLink()) {
                    throw new RuntimeException('Unsafe path in data recovery archive.');
                }
                if ($entry->isDir()) {
                    if ($name !== 'public_html' && $name !== 'public_html/uploads'
                        && $name !== 'public_html/assets' && $name !== 'public_html/assets/uploads'
                        && !self::isMediaDirectory($name)) {
                        throw new RuntimeException('Unexpected directory in recovery archive.');
                    }
                    continue;
                }
                if (!$entry->isFile() || !isset($expected[$name]) || isset($seen[$name])) {
                    throw new RuntimeException('Unexpected or duplicate recovery archive member.');
                }
                $size = $entry->getSize();
                if (!is_int($size) || $size < 0 || $size > self::MAX_TOTAL_BYTES - $expanded) {
                    throw new RuntimeException('Expanded recovery archive is too large.');
                }
                $expanded += $size;
                $seen[$name] = true;
                if (count($seen) > self::MAX_MEMBERS) {
                    throw new RuntimeException('Too many recovery archive entries.');
                }
            }
            if (count($seen) !== count($expected)) {
                throw new RuntimeException('Missing recovery archive files.');
            }
            foreach ($expected as $member => $_) {
                if (!isset($seen[$member])) throw new RuntimeException('Missing recovery archive member.');
                if ($member === 'manifest.json') continue;
                $actual = @hash_file('sha256', 'phar://' . $archivePath . '/' . $member);
                $wanted = $member === 'database.sql'
                    ? $manifest['database_sha256'] : $uploadHashes[$member];
                if (!is_string($actual) || !hash_equals($wanted, $actual)) {
                    throw new RuntimeException('Data recovery archive content mismatch.');
                }
            }
            $sha = hash_file('sha256', $path);
            if (!is_string($sha)) throw new RuntimeException('Cannot hash recovery archive.');
            return [
                'sha256' => $sha,
                'files' => count($uploadHashes),
                'sql_sha256' => $manifest['database_sha256'],
                'tables' => count($manifest['database_tables']),
                'triggers' => $manifest['database_triggers'],
            ];
        } catch (Throwable $e) {
            throw new RuntimeException('Data recovery integrity verification failed.', 0, $e);
        }
    }

    private static function isDigest(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{64}$/D', $value) === 1;
    }

    private static function safePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')
            || str_contains($path, "\0")) return false;
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || preg_match('/[\x00-\x1f\x7f]/', $segment)) return false;
        }
        return true;
    }

    private static function safeUploadPath(string $path): bool
    {
        return self::safePath($path)
            && (str_starts_with($path, 'public_html/uploads/')
                || str_starts_with($path, 'public_html/assets/uploads/'))
            && basename($path) !== '.htaccess';
    }

    private static function isMediaDirectory(string $path): bool
    {
        return str_starts_with($path, 'public_html/uploads/')
            || str_starts_with($path, 'public_html/assets/uploads/');
    }
}
