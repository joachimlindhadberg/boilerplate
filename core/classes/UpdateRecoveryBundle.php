<?php

declare(strict_types=1);

/**
 * Internal recovery bundle foundation. Never triggered by web requests in QA1.
 * Bundles independently verified code/config and SQL/media in a private vault.
 * NOT a consistency barrier, a restore procedure, or permission to update.
 */
final class UpdateRecoveryBundle
{
    private const MAX_RETAINED = 3;
    private string $root;
    private string $vault;
    /** @var null|Closure():string */
    private ?Closure $dataFactory;

    public function __construct(?string $root = null, ?callable $dataFactory = null)
    {
        $source = $root ?? dirname(__DIR__, 2);
        $resolved = realpath($source);
        if ($resolved === false || !is_dir($resolved) || is_link($source)) {
            throw new RuntimeException('Unsafe application root for recovery bundle.');
        }
        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
        $this->vault = $this->root . '/storage/update-recovery';
        $this->dataFactory = $dataFactory === null ? null : Closure::fromCallable($dataFactory);
    }

    /**
     * Restricted internal operation. Each completed bundle is immutable by contract.
     * Caller must enforce a separately tested write-quiescence barrier before
     * treating an SQL/media backup as a point-in-time recovery set.
     * @return array{id:string,location:string,files:int,code_sha256:string,data_sha256:string,status:string}
     */
    public function create(): array
    {
        $this->prepareVault();
        $lock = $this->lock();
        $stage = null;
        $code = null;
        $data = null;
        try {
            if (count($this->completeBundles()) >= self::MAX_RETAINED) {
                throw new RuntimeException('Private recovery vault is full; existing backups were preserved.');
            }
            $id = 'recovery-' . bin2hex(random_bytes(16));
            $stage = $this->vault . '/.pending-' . substr($id, strlen('recovery-'));
            if (!mkdir($stage, 0700)) throw new RuntimeException('Cannot create private recovery staging directory.');
            @chmod($stage, 0700);
            $codeService = new UpdateRecoveryCodeSnapshot($this->root);
            $code = $codeService->create();
            $this->copyRestricted($code['path'], $stage . '/code.tar.gz');
            $codeService->verify($stage . '/code.tar.gz');
            $codeDigest = hash_file('sha256', $stage . '/code.tar.gz');
            if (!is_string($codeDigest) || !hash_equals($code['sha256'], $codeDigest)) {
                throw new RuntimeException('Copied code snapshot integrity mismatch.');
            }
            $data = $this->dataFactory !== null ? ($this->dataFactory)() : (new SiteBackup())->create();
            if (!is_string($data) || is_link($data) || !is_file($data)) {
                throw new RuntimeException('Unsafe site data backup output.');
            }
            $verifier = new UpdateRecoveryDataArchive();
            // SiteBackup deliberately returns a private extensionless tempfile.
            // PharData only opens recognized archive extensions, so inspect the
            // copy in our private staging directory and compare source bytes.
            $sourceDigest = hash_file('sha256', $data);
            if (!is_string($sourceDigest)) throw new RuntimeException('Cannot hash site data source.');
            $this->copyRestricted($data, $stage . '/data.tar.gz');
            $dataReport = $verifier->verify($stage . '/data.tar.gz');
            if (!hash_equals($sourceDigest, $dataReport['sha256'])) {
                throw new RuntimeException('Copied site data integrity mismatch.');
            }
            $manifest = [
                'format' => 'boilerplate-recovery-bundle', 'version' => 1,
                'created_utc' => gmdate('c'),
                'contains_sensitive_data' => true,
                'verified' => ['code_archive' => true, 'data_archive' => true],
                'code_sha256' => $codeDigest, 'data_sha256' => $dataReport['sha256'],
                'code_files' => $code['files'], 'media_files' => $dataReport['files'],
                'database_tables' => $dataReport['tables'],
                'database_triggers' => $dataReport['triggers'],
                'has_env' => $code['has_env'],
                'requires_write_quiescence' => true,
                'requires_restore_rehearsal' => true,
                'approved_for_automatic_update' => false,
            ];
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->writeRestricted($stage . '/manifest.json', $json);
            $this->inspectDirectory($stage);
            $final = $this->vault . '/' . $id;
            if (!rename($stage, $final)) throw new RuntimeException('Cannot finalize private recovery bundle.');
            $stage = null;
            return [
                'id' => $id, 'location' => $final, 'files' => $code['files'],
                'code_sha256' => $codeDigest, 'data_sha256' => $dataReport['sha256'],
                'status' => 'verified_archives_restore_not_rehearsed',
            ];
        } finally {
            if (is_array($code) && is_string($code['path'] ?? null)) {
                (new UpdateRecoveryCodeSnapshot($this->root))->discard($code['path']);
            }
            if (is_string($data)) $this->discardDataTemporary($data);
            if ($stage !== null && is_dir($stage)) $this->removePending($stage);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{status:string,code_files:int,media_files:int,has_env:bool} */
    public function verify(string $id): array
    {
        if (!preg_match('/^recovery-[0-9a-f]{32}$/D', $id)) {
            throw new RuntimeException('Unknown recovery bundle identifier.');
        }
        $dir = $this->vault . '/' . $id;
        if (is_link($this->root . '/storage') || is_link($this->vault) || !is_dir($this->vault)
            || (fileperms($this->vault) & 0077) !== 0) {
            throw new RuntimeException('Unsafe recovery vault.');
        }
        $this->inspectDirectory($dir);
        $raw = file_get_contents($dir . '/manifest.json');
        if (!is_string($raw) || strlen($raw) > 65536) throw new RuntimeException('Invalid bundle manifest size.');
        $manifest = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['format'] ?? null) !== 'boilerplate-recovery-bundle'
            || ($manifest['version'] ?? null) !== 1
            || ($manifest['approved_for_automatic_update'] ?? null) !== false
            || ($manifest['requires_restore_rehearsal'] ?? null) !== true
            || ($manifest['requires_write_quiescence'] ?? null) !== true
            || !is_int($manifest['code_files'] ?? null) || !is_int($manifest['media_files'] ?? null)
            || !is_bool($manifest['has_env'] ?? null)) {
            throw new RuntimeException('Unrecognized recovery bundle manifest.');
        }
        $codeHash = hash_file('sha256', $dir . '/code.tar.gz');
        $dataHash = hash_file('sha256', $dir . '/data.tar.gz');
        foreach (['code' => $codeHash, 'data' => $dataHash] as $type => $digest) {
            if (!is_string($digest) || !is_string($manifest[$type . '_sha256'] ?? null)
                || !hash_equals($manifest[$type . '_sha256'], $digest)) {
                throw new RuntimeException('Modified recovery bundle archive: ' . $type);
            }
        }
        $codeReport = (new UpdateRecoveryCodeSnapshot($this->root))->verify($dir . '/code.tar.gz');
        $dataReport = (new UpdateRecoveryDataArchive())->verify($dir . '/data.tar.gz');
        if ($manifest['code_files'] !== $codeReport['files']
            || $manifest['media_files'] !== $dataReport['files']
            || $manifest['database_tables'] !== $dataReport['tables']
            || $manifest['database_triggers'] !== $dataReport['triggers']
            || $manifest['has_env'] !== $codeReport['has_env']) {
            throw new RuntimeException('Recovery bundle inventory changed.');
        }
        return ['status' => 'verified_archives_restore_not_rehearsed',
            'code_files' => $codeReport['files'],
            'media_files' => $dataReport['files'], 'has_env' => $codeReport['has_env']];
    }

    private function prepareVault(): void
    {
        $storage = $this->root . '/storage';
        if (is_link($storage) || (file_exists($storage) && !is_dir($storage))) {
            throw new RuntimeException('Unsafe recovery storage root.');
        }
        if (!is_dir($storage) && !mkdir($storage, 0700)) {
            throw new RuntimeException('Cannot prepare private recovery storage.');
        }
        if (is_link($this->vault) || (file_exists($this->vault) && !is_dir($this->vault))) {
            throw new RuntimeException('Unsafe private recovery vault.');
        }
        if (!is_dir($this->vault) && !mkdir($this->vault, 0700)) {
            throw new RuntimeException('Cannot create private recovery vault.');
        }
        @chmod($this->vault, 0700);
        if ((fileperms($this->vault) & 0077) !== 0) {
            throw new RuntimeException('Private recovery vault is not owner-only.');
        }
    }

    /** @return resource */
    private function lock()
    {
        $path = $this->vault . '/.lock';
        if (is_link($path)) throw new RuntimeException('Unsafe recovery lock file.');
        $handle = fopen($path, 'c');
        if ($handle === false) throw new RuntimeException('Cannot acquire recovery lock.');
        @chmod($path, 0600);
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Another recovery operation is in progress.');
        }
        return $handle;
    }

    /** @return list<string> */
    private function completeBundles(): array
    {
        $found = [];
        foreach (new DirectoryIterator($this->vault) as $item) {
            if (!$item->isDot() && preg_match('/^recovery-[0-9a-f]{32}$/D', $item->getFilename())) {
                $found[] = $item->getFilename();
            }
        }
        return $found;
    }

    private function copyRestricted(string $from, string $to): void
    {
        if (is_link($from) || !is_file($from) || file_exists($to) || is_link($to)) {
            throw new RuntimeException('Unsafe private recovery copy.');
        }
        $input = fopen($from, 'rb');
        $output = fopen($to, 'xb');
        if ($input === false || $output === false) {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
            throw new RuntimeException('Cannot open recovery archive streams.');
        }
        try {
            @chmod($to, 0600);
            if (stream_copy_to_stream($input, $output) === false || !fflush($output)) {
                throw new RuntimeException('Cannot copy private recovery archive.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function writeRestricted(string $path, string $content): void
    {
        $file = fopen($path, 'xb');
        if ($file === false) throw new RuntimeException('Cannot write private recovery metadata.');
        try {
            @chmod($path, 0600);
            $length = strlen($content);
            for ($written = 0; $written < $length;) {
                $n = fwrite($file, substr($content, $written));
                if ($n === false || $n === 0) throw new RuntimeException('Short recovery manifest write.');
                $written += $n;
            }
            if (!fflush($file)) throw new RuntimeException('Recovery metadata flush failed.');
        } finally {
            fclose($file);
        }
    }

    private function inspectDirectory(string $dir): void
    {
        if (is_link($dir) || !is_dir($dir) || (fileperms($dir) & 0077) !== 0) {
            throw new RuntimeException('Unsafe recovery bundle directory.');
        }
        $names = [];
        foreach (new DirectoryIterator($dir) as $entry) {
            if ($entry->isDot()) continue;
            if ($entry->isLink() || !$entry->isFile() || (fileperms($entry->getPathname()) & 0077) !== 0) {
                throw new RuntimeException('Unsafe recovery bundle member.');
            }
            $names[] = $entry->getFilename();
        }
        sort($names);
        if ($names !== ['code.tar.gz', 'data.tar.gz', 'manifest.json']) {
            throw new RuntimeException('Incomplete recovery bundle directory.');
        }
    }

    private function discardDataTemporary(string $file): void
    {
        // macOS commonly reports /var/folders for /private/var/folders.
        // Canonicalize the enclosing directory but never accept a symlinked file.
        if (is_link($file) || !is_file($file)
            || !preg_match('/^cms-download-[A-Za-z0-9_-]+$/D', basename($file))) return;
        $tmp = realpath(sys_get_temp_dir());
        $dir = realpath(dirname($file));
        if ($tmp !== false && $dir !== false && $dir === $tmp) {
            @unlink($file);
        }
    }

    private function removePending(string $dir): void
    {
        if (is_link($dir) || !str_starts_with($dir, $this->vault . '/.pending-')) return;
        foreach (['code.tar.gz', 'data.tar.gz', 'manifest.json'] as $name) {
            $path = $dir . '/' . $name;
            if (is_file($path) && !is_link($path)) @unlink($path);
        }
        @rmdir($dir); // Never recursively delete unknown paths.
    }
}
