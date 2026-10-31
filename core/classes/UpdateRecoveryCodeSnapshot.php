<?php

declare(strict_types=1);

/**
 * Internal, private update-recovery building block (QA1).
 * Archives the installed code and local private configuration, NOT site data.
 * Use SiteBackup separately for SQL and media; neither alone is full recovery.
 *
 * This service never reads a database, restores files, downloads releases, or
 * exposes archives through the admin. Caller MUST call discard() on the result.
 */
final class UpdateRecoveryCodeSnapshot
{
    private const MAX_FILES = 25000;
    private const MAX_TOTAL_BYTES = 2147483648; // 2 GiB of uncompressed code
    private const MAX_FILE_BYTES = 536870912; // 512 MiB per code file
    private const MAX_TAR_BYTES = 2281701376; // 2 GiB of files + 128 MiB TAR overhead

    private string $root;

    public function __construct(?string $root = null)
    {
        $real = realpath($root ?? dirname(__DIR__, 2));
        if ($real === false || !is_dir($real) || is_link($root ?? dirname(__DIR__, 2))) {
            throw new RuntimeException('Invalid installed application root.');
        }
        $this->root = rtrim($real, DIRECTORY_SEPARATOR);
    }

    /**
     * Create a private temporary tar.gz and verify every archived payload hash.
     * No destination is accepted from a request or admin action.
     * @return array{path:string,sha256:string,files:int,bytes:int,has_env:bool}
     */
    public function create(): array
    {
        if (!class_exists(PharData::class) || !extension_loaded('zlib')) {
            throw new RuntimeException('Phar and zlib are required for private recovery snapshots.');
        }
        [$files, $dirs, $bytes] = $this->inventory();
        $work = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/cms-recovery-' . bin2hex(random_bytes(16));
        if (!mkdir($work, 0700) || !is_dir($work)) {
            throw new RuntimeException('Unable to create a private recovery work directory.');
        }
        $tar = $work . '/code.tar';
        $gz = $tar . '.gz';
        $manifestPath = $work . '/manifest.json';
        $complete = false;
        try {
            $manifest = [
                'format' => 'boilerplate-update-code-snapshot',
                'version' => 1,
                'created_utc' => gmdate('c'),
                'scope' => 'installed code and locally stored configuration; excludes SQL and media',
                'contains_sensitive_data' => true,
                'has_env' => isset($files['.env']),
                'file_sha256' => array_map(static fn(array $v): string => $v['hash'], $files),
                'directories' => $dirs,
                'excluded' => ['storage/', '.git/', 'public_html/uploads/* except .htaccess',
                    'public_html/assets/uploads/* except .htaccess', 'database contents and external server configuration'],
            ];
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($manifestPath, $json, LOCK_EX) === false) {
                throw new RuntimeException('Could not create recovery snapshot manifest.');
            }
            @chmod($manifestPath, 0600);
            $archive = new PharData($tar);
            $archive->addFile($manifestPath, 'manifest.json');
            foreach ($dirs as $dir) $archive->addEmptyDir('code/' . $dir);
            foreach ($files as $relative => $item) {
                $archive->addFile($item['path'], 'code/' . $relative);
            }
            // Do not inherit an overly permissive local .env mode in the archive.
            if (isset($files['.env'])) $archive['code/.env']->chmod(0600);
            unset($archive);
            // Stream the completed TAR through zlib. Do not ask Phar to rewrite
            // the compressed archive: some PHP runtimes return incorrect entry
            // offsets when accessing a newly compressed PharData TAR.GZ.
            self::gzipTar($tar, $gz);
            if (!is_file($gz) || filesize($gz) < 1) {
                throw new RuntimeException('Could not compress recovery snapshot.');
            }
            @chmod($gz, 0600);
            // Verify against the source inventory as well as the archive manifest.
            $this->verify($gz, $manifest['file_sha256'], $dirs);
            $sha = hash_file('sha256', $gz);
            if (!is_string($sha)) throw new RuntimeException('Unable to hash recovery snapshot.');
            $complete = true;
            return ['path' => $gz, 'sha256' => $sha, 'files' => count($files),
                'bytes' => $bytes, 'has_env' => isset($files['.env'])];
        } finally {
            foreach ([$manifestPath, $tar] as $path) if (is_file($path)) @unlink($path);
            if (!$complete) {
                if (is_file($gz)) @unlink($gz);
                @rmdir($work);
            }
        }
    }

    /** Secure cleanup of the specific private temporary snapshot created by this service. */
    public function discard(string $path): void
    {
        $tmp = realpath(sys_get_temp_dir());
        $dir = dirname($path);
        $realDir = realpath($dir);
        if ($tmp === false || $realDir === false || dirname($realDir) !== $tmp
            || preg_match('/^cms-recovery-[0-9a-f]{32}$/D', basename($realDir)) !== 1
            || basename($path) !== 'code.tar.gz' || is_link($path) || !is_file($path)) {
            throw new RuntimeException('Refusing to delete an unrecognized recovery path.');
        }
        if (!unlink($path) || !rmdir($realDir)) {
            throw new RuntimeException('Could not remove temporary recovery snapshot.');
        }
    }

    /**
     * Read-only integrity check; does not extract or execute archive members.
     * The hashes are not proof of an independently authenticated publisher.
     * @param array<string,string>|null $expectedFiles Exact expected hashes from live source when creating.
     * @param list<string>|null $expectedDirs
     * @return array{files:int,has_env:bool}
     */
    public function verify(string $path, ?array $expectedFiles = null, ?array $expectedDirs = null): array
    {
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            throw new RuntimeException('Recovery snapshot is not a readable regular file.');
        }
        $archivePath = realpath($path);
        if ($archivePath === false || !is_file($archivePath)) {
            throw new RuntimeException('Cannot resolve recovery snapshot archive.');
        }
        // PharData can report invalid bytes when reading individual members
        // directly from gzip TARs on some PHP runtimes. Decompress to a private,
        // bounded temporary TAR and validate its complete content with Phar.
        // Never extract archive members to the filesystem.
        [$archivePath, $work] = self::privateTarCopy($archivePath);
        try {
            // Keep the Phar iterator and hash reads on the same resolved path.
            $archive = new PharData($archivePath);
            if (!isset($archive['manifest.json']) || !$archive['manifest.json']->isFile()) {
                throw new RuntimeException('Recovery manifest is missing.');
            }
            if ($archive['manifest.json']->getSize() > 4194304) {
                throw new RuntimeException('Oversized recovery manifest.');
            }
            $manifestRaw = $archive['manifest.json']->getContent();
            $manifest = json_decode($manifestRaw, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'boilerplate-update-code-snapshot'
                || ($manifest['version'] ?? null) !== 1 || !is_array($manifest['file_sha256'] ?? null)
                || !is_array($manifest['directories'] ?? null) || !is_bool($manifest['has_env'] ?? null)) {
                throw new RuntimeException('Invalid recovery snapshot manifest.');
            }
            $files = $manifest['file_sha256'];
            $dirs = $manifest['directories'];
            if (count($files) > self::MAX_FILES || count($dirs) > self::MAX_FILES * 2
                || $manifest['has_env'] !== array_key_exists('.env', $files)) {
                throw new RuntimeException('Invalid recovery snapshot inventory.');
            }
            if ($expectedFiles !== null && $expectedFiles !== $files) {
                throw new RuntimeException('Recovery snapshot differs from original file hashes.');
            }
            if ($expectedDirs !== null && $expectedDirs !== $dirs) {
                throw new RuntimeException('Recovery snapshot differs from original directories.');
            }
            $total = 0;
            foreach ($files as $relative => $hash) {
                if (!is_string($relative) || !self::safeRelative($relative) || !is_string($hash)
                    || !preg_match('/^[0-9a-f]{64}$/D', $hash)) {
                    throw new RuntimeException('Invalid recovery file entry.');
                }
                $member = 'code/' . $relative;
                if (!isset($archive[$member]) || !$archive[$member]->isFile()
                    || $archive[$member]->isLink()) {
                    throw new RuntimeException('Recovery snapshot file missing or unsafe.');
                }
                $size = $archive[$member]->getSize();
                $total += $size;
                if ($size > self::MAX_FILE_BYTES || $total > self::MAX_TOTAL_BYTES) {
                    throw new RuntimeException('Recovery snapshot exceeds size limit.');
                }
                $actual = hash_file('sha256', 'phar://' . $archivePath . '/' . $member);
                if (!is_string($actual) || !hash_equals($hash, $actual)) {
                    throw new RuntimeException('Recovery snapshot checksum mismatch.');
                }
            }
            foreach ($dirs as $dir) {
                if (!is_string($dir) || !self::safeRelative($dir) || !isset($archive['code/' . $dir])
                    || !$archive['code/' . $dir]->isDir()) {
                    throw new RuntimeException('Invalid recovery snapshot directory.');
                }
            }
            // Detect extra archive members, including unexpected data and config paths.
            $members = [];
            $iter = new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iter as $member) {
                $entryPath = str_replace('\\', '/', $member->getPathname());
                $prefix = 'phar://' . $archivePath . '/';
                if (!str_starts_with($entryPath, $prefix)) {
                    throw new RuntimeException('Unexpected recovery snapshot archive location.');
                }
                $name = substr($entryPath, strlen($prefix));
                if ($name === 'code' && $member->isDir()) continue;
                if (isset($members[$name])) throw new RuntimeException('Duplicate recovery snapshot member.');
                $members[$name] = true;
            }
            $allowed = ['manifest.json' => true];
            foreach ($dirs as $dir) $allowed['code/' . $dir] = true;
            foreach ($files as $rel => $_) $allowed['code/' . $rel] = true;
            if (array_diff_key($members, $allowed) || array_diff_key($allowed, $members)) {
                throw new RuntimeException('Unexpected or missing recovery snapshot members.');
            }
            return ['files' => count($files), 'has_env' => $manifest['has_env']];
        } catch (JsonException | UnexpectedValueException $e) {
            throw new RuntimeException('Unreadable or invalid recovery snapshot archive.', 0, $e);
        } finally {
            unset($archive);
            @unlink($archivePath);
            @rmdir($work);
        }
    }

    /**
     * Gzip the already-built TAR without PharData::compress(). All temporary
     * content resides in a private work directory and is removed by create().
     */
    private static function gzipTar(string $tar, string $gzipPath): void
    {
        $source = fopen($tar, 'rb');
        if ($source === false) throw new RuntimeException('Cannot open recovery TAR for compression.');
        $destination = gzopen($gzipPath, 'wb6');
        if ($destination === false) {
            fclose($source);
            throw new RuntimeException('Cannot create compressed recovery snapshot.');
        }
        $success = false;
        try {
            while (!feof($source)) {
                $chunk = fread($source, 65536);
                if ($chunk === false || ($chunk === '' && !feof($source))) {
                    throw new RuntimeException('Cannot read uncompressed recovery TAR.');
                }
                for ($offset = 0, $size = strlen($chunk); $offset < $size;) {
                    $n = gzwrite($destination, substr($chunk, $offset));
                    if (!is_int($n) || $n < 1) {
                        throw new RuntimeException('Cannot write compressed recovery TAR.');
                    }
                    $offset += $n;
                }
            }
            $success = true;
        } finally {
            fclose($source);
            if (!gzclose($destination)) $success = false;
        }
        if (!$success) throw new RuntimeException('Recovery snapshot compression failed.');
    }

    /**
     * Inflate gzip into a fresh owner-only TAR for read-only validation.
     * Enforce archive bounds during streaming to prevent gzip bombs. The
     * returned directory is removed by verify() even if validation fails.
     *
     * @return array{0:string,1:string}
     */
    private static function privateTarCopy(string $source): array
    {
        $size = filesize($source);
        if (!is_int($size) || $size < 3 || $size > self::MAX_TAR_BYTES) {
            throw new RuntimeException('Invalid compressed recovery snapshot size.');
        }
        $header = fopen($source, 'rb');
        if ($header === false) throw new RuntimeException('Cannot read recovery snapshot header.');
        try { $signature = fread($header, 3); }
        finally { fclose($header); }
        if ($signature !== "\x1f\x8b\x08") {
            throw new RuntimeException('Recovery snapshot is not a gzip TAR.');
        }
        $digestBefore = hash_file('sha256', $source);
        if (!is_string($digestBefore)) throw new RuntimeException('Cannot hash recovery snapshot.');
        $work = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/cms-recovery-check-' . bin2hex(random_bytes(16));
        if (!mkdir($work, 0700) || !is_dir($work)) {
            throw new RuntimeException('Cannot create private snapshot validation directory.');
        }
        $tarPath = $work . '/archive.tar';
        $gz = null;
        $tar = null;
        $success = false;
        try {
            $gz = gzopen($source, 'rb');
            $tar = fopen($tarPath, 'xb');
            if ($gz === false || $tar === false) {
                throw new RuntimeException('Cannot open private snapshot validation streams.');
            }
            @chmod($tarPath, 0600);
            $total = 0;
            while (!gzeof($gz)) {
                $chunk = gzread($gz, 65536);
                if ($chunk === false || ($chunk === '' && !gzeof($gz))) {
                    throw new RuntimeException('Cannot decompress recovery snapshot.');
                }
                $length = strlen($chunk);
                if ($length > self::MAX_TAR_BYTES - $total) {
                    throw new RuntimeException('Expanded recovery snapshot exceeds size limit.');
                }
                $total += $length;
                for ($offset = 0; $offset < $length;) {
                    $written = fwrite($tar, substr($chunk, $offset));
                    if (!is_int($written) || $written < 1) {
                        throw new RuntimeException('Cannot write private recovery TAR.');
                    }
                    $offset += $written;
                }
            }
            if ($total < 1024 || !fflush($tar)) {
                throw new RuntimeException('Incomplete recovery TAR.');
            }
            $digestAfter = hash_file('sha256', $source);
            if (!is_string($digestAfter) || !hash_equals($digestBefore, $digestAfter)) {
                throw new RuntimeException('Recovery snapshot changed during validation.');
            }
            $success = true;
        } finally {
            if (is_resource($tar)) fclose($tar);
            if ($gz !== null && $gz !== false) gzclose($gz);
            if (!$success) {
                @unlink($tarPath);
                @rmdir($work);
            }
        }
        // macOS resolves /var/folders to /private/var/folders. Phar's
        // iterator uses that canonical spelling; validation must match it.
        $resolved = realpath($tarPath);
        if ($resolved === false) {
            @unlink($tarPath);
            @rmdir($work);
            throw new RuntimeException('Cannot resolve private recovery TAR.');
        }
        return [$resolved, $work];
    }

    /** @return array{0:array<string,array{path:string,hash:string}>,1:list<string>,2:int} */
    private function inventory(): array
    {
        foreach (['core/classes/Autoload.php', 'public_html/incl/init.php'] as $required) {
            if (!is_file($this->root . '/' . $required) || is_link($this->root . '/' . $required)) {
                throw new RuntimeException('Required installed CMS source is missing or unsafe.');
            }
        }
        $files = []; $dirs = []; $bytes = 0;
        $this->scan($this->root, '', $files, $dirs, $bytes);
        ksort($files, SORT_STRING);
        sort($dirs, SORT_STRING);
        return [$files, $dirs, $bytes];
    }

    /** @param array<string,array{path:string,hash:string}> $files @param list<string> $dirs */
    private function scan(string $absolute, string $prefix, array &$files, array &$dirs, int &$bytes): void
    {
        $iterator = new DirectoryIterator($absolute);
        foreach ($iterator as $item) {
            if ($item->isDot()) continue;
            $name = $item->getFilename();
            $rel = ltrim($prefix . '/' . $name, '/');
            if (!self::safeRelative($rel)) throw new RuntimeException('Unsafe local recovery path.');
            if ($item->isLink()) throw new RuntimeException('Symbolic links cannot be snapshotted safely.');
            if ($prefix === '' && in_array($name, ['storage', '.git'], true)) continue;
            if (in_array($rel, ['public_html/uploads', 'public_html/assets/uploads'], true)) {
                if (!$item->isDir()) throw new RuntimeException('Unsafe media directory.');
                $policy = $item->getPathname() . '/.htaccess';
                if (is_link($policy)) throw new RuntimeException('Unsafe upload security policy.');
                if (is_file($policy)) $this->addFile($policy, $rel . '/.htaccess', $files, $bytes);
                $dirs[] = $rel;
                continue;
            }
            if ($item->isDir()) {
                if (!$item->isReadable()) throw new RuntimeException('Unreadable code directory.');
                $dirs[] = $rel;
                if (count($dirs) > self::MAX_FILES * 2) throw new RuntimeException('Too many code directories.');
                $this->scan($item->getPathname(), $rel, $files, $dirs, $bytes);
            } elseif ($item->isFile() && $item->isReadable()) {
                if ($name === '.DS_Store' || str_ends_with($name, '.log')) continue;
                $this->addFile($item->getPathname(), $rel, $files, $bytes);
            } else {
                throw new RuntimeException('Unreadable or non-regular installed source file.');
            }
        }
    }

    /** @param array<string,array{path:string,hash:string}> $files */
    private function addFile(string $path, string $relative, array &$files, int &$bytes): void
    {
        if (!self::safeRelative($relative) || is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Unsafe installed source file.');
        }
        $size = filesize($path);
        if (!is_int($size) || $size > self::MAX_FILE_BYTES || $size < 0
            || count($files) >= self::MAX_FILES || $bytes + $size > self::MAX_TOTAL_BYTES) {
            throw new RuntimeException('Installed CMS source exceeds recovery snapshot limits.');
        }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) throw new RuntimeException('Unable to hash installed source file.');
        $files[$relative] = ['path' => $path, 'hash' => $hash];
        $bytes += $size;
    }

    private static function safeRelative(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0")) return false;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || preg_match('/[\x00-\x1f\x7f]/', $part)) return false;
        }
        return true;
    }
}
