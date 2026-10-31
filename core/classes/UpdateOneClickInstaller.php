<?php

declare(strict_types=1);

/**
 * Browser-triggered, official GitHub release installer (QA1).
 * It updates CODE ONLY. New/changed migrations are deliberately refused until
 * a real SQL restore path has been separately verified on the target host.
 * Caller must enforce admin tools.manage + CSRF and acquire the exclusive
 * UpdateMaintenanceGate for the entire operation.
 */
final class UpdateOneClickInstaller
{
    private string $root;
    private string $vault;

    public function __construct(?string $root = null)
    {
        $given = $root ?? dirname(__DIR__, 2);
        $real = realpath($given);
        if ($real === false || !is_dir($real) || is_link($given)) {
            throw new RuntimeException('Unsafe update installation root.');
        }
        $this->root = rtrim($real, '/');
        $this->vault = $this->root . '/storage/update-recovery';
    }

    public function hasInterruptedUpdate(): bool
    {
        if (!is_dir($this->vault) || is_link($this->vault)) return false;
        foreach (new DirectoryIterator($this->vault) as $entry) {
            if (!$entry->isDot() && preg_match('/^\.one-click-pending-[a-f0-9]{32}$/D', $entry->getFilename())) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array{status:string,version:string}
     * Must be called under the exclusive maintenance gate.
     */
    public function install(string $expectedVersion, string $expectedSha, string $channel = 'stable'): array
    {
        if ($this->hasInterruptedUpdate()) return ['status' => 'recovery_required', 'version' => ''];
        $stage = (new UpdateOfficialReleasePackage($this->root))->preparedStatus($channel);
        if (($stage['status'] ?? '') !== 'prepared_for_review_only'
            || !is_string($stage['version'] ?? null) || !hash_equals($expectedVersion, $stage['version'])
            || !is_string($stage['sha256'] ?? null) || !hash_equals($expectedSha, $stage['sha256'])) {
            return ['status' => 'invalid_preparation', 'version' => ''];
        }
        $archive = $this->vault . '/.official-release-' . $stage['sha256'] . '.zip';
        if (is_link($archive) || !is_file($archive)) return ['status' => 'invalid_preparation', 'version' => ''];
        $size = filesize($archive);
        if (!is_int($size) || $size < 1 || $size > UpdatePackageReview::MAX_ARCHIVE_BYTES
            || !hash_equals($stage['sha256'], (string)@hash_file('sha256', $archive))) {
            return ['status' => 'invalid_preparation', 'version' => ''];
        }
        $review = (new UpdatePackageReview($this->root))->inspect($archive, $size);
        if (($review['status'] ?? '') !== 'review'
            || ($review['target_version'] ?? '') !== $expectedVersion
            || ($review['new_migrations'] ?? -1) !== 0
            || ($review['files'] ?? 0) !== $stage['files']) {
            return ['status' => 'unsupported_release', 'version' => ''];
        }
        // A file-only update must not leave previously pending/changed CMS
        // migrations unapplied. This reads the live registry without any writes.
        if (class_exists('Db')) {
            try {
                foreach ((new MigrationRunner(new Db(), $this->root))->status() as $migration) {
                    if (($migration['state'] ?? '') !== 'applied') {
                        return ['status' => 'unsupported_release', 'version' => ''];
                    }
                }
            } catch (Throwable $e) {
                return ['status' => 'unsupported_release', 'version' => ''];
            }
        }
        // PHP config changes need manual host approval. Unchanged .user.ini is fine.
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            return ['status' => 'invalid_preparation', 'version' => ''];
        }
        $work = null;
        $journalComplete = false;
        $plan = [];
        try {
            $this->assertPrivateVault();
            $work = $this->vault . '/.one-click-pending-' . bin2hex(random_bytes(16));
            if (!mkdir($work, 0700)) throw new RuntimeException('Cannot allocate private update work area.');
            @chmod($work, 0700);
            if ((fileperms($work) & 0077) !== 0) throw new RuntimeException('Unsafe update work area.');
            foreach (['old', 'new'] as $folder) {
                if (!mkdir($work . '/' . $folder, 0700)) throw new RuntimeException('Cannot create update staging directory.');
            }
            $seen = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat) || !is_string($stat['name'] ?? null)) {
                    throw new RuntimeException('Invalid ZIP entry.');
                }
                $name = $stat['name'];
                $dir = str_ends_with($name, '/');
                if (!UpdatePackageReview::isSafeMember($name, $dir)) {
                    throw new RuntimeException('Unsafe ZIP path.');
                }
                if ($dir) continue;
                $relative = substr($name, strlen('boilerplate/'));
                $key = strtolower($relative);
                if (isset($seen[$key])) throw new RuntimeException('Duplicate archive member.');
                $seen[$key] = true;
                $dest = $this->destination($relative);
                if ($relative === 'public_html/.user.ini' && (!is_file($dest)
                    || !hash_equals((string)@hash_file('sha256', $dest),
                        hash('sha256', (string)$zip->getFromIndex($i))))) {
                    throw new RuntimeException('Hosting configuration differs.');
                }
                $payload = $zip->getFromIndex($i);
                if (!is_string($payload) || strlen($payload) !== (int)$stat['size']) {
                    throw new RuntimeException('Cannot read release file.');
                }
                $oldExists = is_file($dest);
                $oldHash = $oldExists ? hash_file('sha256', $dest) : null;
                if ($oldExists && !is_string($oldHash)) throw new RuntimeException('Cannot hash installed file.');
                $newHash = hash('sha256', $payload);
                if ($oldExists && hash_equals($oldHash, $newHash)) continue;
                $newPath = $this->workPath($work, 'new', $relative);
                $this->writePrivate($newPath, $payload);
                unset($payload);
                if ($oldExists) {
                    $oldPath = $this->workPath($work, 'old', $relative);
                    $this->copyPrivate($dest, $oldPath);
                    if (!hash_equals($oldHash, (string)hash_file('sha256', $oldPath))) {
                        throw new RuntimeException('Source changed during staging.');
                    }
                }
                $plan[] = ['path' => $relative, 'old' => $oldHash, 'new' => $newHash];
            }
            if (!$plan) return ['status' => 'unsupported_release', 'version' => ''];
            // No target write before the full, private, verified recovery bundle exists.
            $backup = (new UpdateRecoveryBundle($this->root))->create();
            (new UpdateRecoveryBundle($this->root))->verify($backup['id']);
            $manifest = json_encode(['format' => 'boilerplate-one-click-journal-v1',
                'version' => $expectedVersion, 'archive_sha256' => $expectedSha,
                'backup_id' => $backup['id'], 'plan' => $plan], JSON_THROW_ON_ERROR);
            $this->writePrivate($work . '/manifest.json', $manifest);
            $journalComplete = true;

            foreach ($plan as $file) {
                $dest = $this->destination($file['path']);
                $actual = is_file($dest) ? hash_file('sha256', $dest) : null;
                if ($actual !== $file['old']) throw new RuntimeException('Installed file changed during preparation.');
                $this->replace($this->workPath($work, 'new', $file['path']), $dest, $file['new']);
            }
            $init = @file_get_contents($this->root . '/public_html/incl/init.php');
            if (!is_string($init) || !preg_match(UpdateReleaseVersion::SOURCE_PATTERN, $init, $m)
                || $m[1] !== $expectedVersion) throw new RuntimeException('Version verification failed.');
            foreach ($plan as $file) {
                if (!hash_equals($file['new'], (string)@hash_file('sha256', $this->destination($file['path'])))) {
                    throw new RuntimeException('Final file verification failed.');
                }
            }
            // Commit independently of cleanup so a cleanup failure cannot
            // trigger a rollback of a successful deployment.
            $completed = $this->vault . '/.one-click-completed-' . substr(basename($work), strlen('.one-click-pending-'));
            if (!rename($work, $completed)) throw new RuntimeException('Cannot finalize update journal.');
            $work = null;
            try { $this->removeWork($completed); } catch (Throwable $ignored) {
                error_log('Completed update temporary cleanup pending.');
            }
            return ['status' => 'updated', 'version' => $expectedVersion];
        } catch (Throwable $e) {
            error_log('One-click update interrupted: ' . get_class($e));
            if ($journalComplete && is_string($work)) {
                try {
                    $this->restorePlan($work, $plan);
                    $this->removeWork($work);
                    $work = null;
                    return ['status' => 'restored_after_failure', 'version' => ''];
                } catch (Throwable $restoreFailure) {
                    error_log('One-click file restore requires attention: ' . get_class($restoreFailure));
                    return ['status' => 'recovery_required', 'version' => ''];
                }
            }
            return ['status' => 'failed_before_changes', 'version' => ''];
        } finally {
            $zip->close();
            if (is_string($work) && !$journalComplete) {
                try { $this->removeWork($work); } catch (Throwable $ignored) { /* Retain unsafe artifact. */ }
            }
        }
    }

    /** Recovery is a separate, administrator-approved operation under exclusive gate. */
    public function restoreInterrupted(): array
    {
        $paths = [];
        if (!is_dir($this->vault) || is_link($this->vault)) return ['status' => 'no_recovery'];
        foreach (new DirectoryIterator($this->vault) as $entry) {
            if (!$entry->isDot() && preg_match('/^\.one-click-pending-[a-f0-9]{32}$/D', $entry->getFilename())) {
                $paths[] = $entry->getPathname();
            }
        }
        if (count($paths) !== 1) return ['status' => 'recovery_required'];
        $work = $paths[0];
        if (is_link($work) || !is_dir($work) || !is_file($work . '/manifest.json')) {
            return ['status' => 'recovery_required'];
        }
        try {
            $raw = file_get_contents($work . '/manifest.json');
            if (!is_string($raw) || strlen($raw) > 2097152) throw new RuntimeException('Invalid journal.');
            $record = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            if (($record['format'] ?? '') !== 'boilerplate-one-click-journal-v1'
                || !is_array($record['plan'] ?? null) || count($record['plan']) > 1500
                || !is_string($record['backup_id'] ?? null)) throw new RuntimeException('Invalid journal format.');
            (new UpdateRecoveryBundle($this->root))->verify($record['backup_id']);
            $this->restorePlan($work, $record['plan']);
            $this->removeWork($work);
            return ['status' => 'restored'];
        } catch (Throwable $e) {
            error_log('Interrupted update recovery needs manual attention: ' . get_class($e));
            return ['status' => 'recovery_required'];
        }
    }

    private function restorePlan(string $work, array $plan): void
    {
        foreach (array_reverse($plan) as $file) {
            if (!is_array($file) || !is_string($file['path'] ?? null)
                || !array_key_exists('old', $file) || (!is_string($file['old']) && $file['old'] !== null)
                || !is_string($file['new'] ?? null)
                || !preg_match('/^[a-f0-9]{64}$/D', $file['new'])
                || (is_string($file['old']) && !preg_match('/^[a-f0-9]{64}$/D', $file['old']))) {
                throw new RuntimeException('Invalid recovery plan entry.');
            }
            $dest = $this->destination($file['path']);
            $current = is_file($dest) ? hash_file('sha256', $dest) : null;
            if ($current === $file['old']) continue;
            if ($current !== $file['new']) throw new RuntimeException('Externally modified file; manual recovery required.');
            if ($file['old'] === null) {
                if (!unlink($dest)) throw new RuntimeException('Cannot delete new file during rollback.');
            } else {
                $old = $this->workPath($work, 'old', $file['path']);
                if (!is_file($old) || is_link($old)
                    || !hash_equals($file['old'], (string)hash_file('sha256', $old))) {
                    throw new RuntimeException('Missing rollback source.');
                }
                $this->replace($old, $dest, $file['old']);
            }
        }
    }

    private function assertPrivateVault(): void
    {
        if (is_link($this->root . '/storage') || !is_dir($this->vault) || is_link($this->vault)
            || (fileperms($this->vault) & 0077) !== 0) {
            throw new RuntimeException('Private update storage unavailable.');
        }
    }

    private function destination(string $relative): string
    {
        if (!UpdatePackageReview::isSafeMember('boilerplate/' . $relative, false)) {
            throw new RuntimeException('Unsafe destination file.');
        }
        $path = $this->root;
        $segments = explode('/', $relative);
        foreach ($segments as $i => $segment) {
            $path .= '/' . $segment;
            if (is_link($path)) throw new RuntimeException('Symbolic link in update target.');
            if ($i < count($segments) - 1 && file_exists($path) && !is_dir($path)) {
                throw new RuntimeException('Non-directory in target path.');
            }
        }
        if (file_exists($path) && !is_file($path)) throw new RuntimeException('Non-file update target.');
        return $path;
    }

    private function workPath(string $base, string $folder, string $relative): string
    {
        if (!in_array($folder, ['old', 'new'], true)
            || !UpdatePackageReview::isSafeMember('boilerplate/' . $relative, false)) {
            throw new RuntimeException('Unsafe staging path.');
        }
        return $base . '/' . $folder . '/' . $relative;
    }

    private function writePrivate(string $path, string $bytes): void
    {
        $parent = dirname($path);
        if (!is_dir($parent) && !mkdir($parent, 0700, true)) throw new RuntimeException('Cannot create private path.');
        if (is_link($path) || file_exists($path)) throw new RuntimeException('Cannot overwrite staging file.');
        if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Cannot write stage.');
        @chmod($path, 0600);
    }

    private function copyPrivate(string $from, string $to): void
    {
        $parent = dirname($to);
        if (!is_dir($parent) && !mkdir($parent, 0700, true)) throw new RuntimeException('Cannot create backup folder.');
        if (is_link($from) || is_link($to) || file_exists($to) || !copy($from, $to)) {
            throw new RuntimeException('Cannot back up file.');
        }
        @chmod($to, 0600);
    }

    private function replace(string $source, string $target, string $digest): void
    {
        if (is_link($source) || !is_file($source)
            || !hash_equals($digest, (string)@hash_file('sha256', $source))) {
            throw new RuntimeException('Update source changed.');
        }
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0755, true)) {
            throw new RuntimeException('Cannot prepare destination directory.');
        }
        $this->destination(substr($target, strlen($this->root) + 1));
        // Never stage executable PHP inside the public webroot, even briefly.
        // A cross-filesystem rename fails closed before replacing the target.
        $tmp = tempnam(dirname($source), '.bp-update-');
        if (!is_string($tmp)) throw new RuntimeException('Cannot create same-filesystem update temporary.');
        try {
            if (!copy($source, $tmp)) throw new RuntimeException('Cannot stage target file.');
            @chmod($tmp, 0644);
            if (!hash_equals($digest, (string)hash_file('sha256', $tmp))) {
                throw new RuntimeException('Temporary destination checksum mismatch.');
            }
            if (!rename($tmp, $target)) throw new RuntimeException('Cannot atomically replace target.');
        } finally {
            if (is_file($tmp)) @unlink($tmp);
        }
    }

    private function removeWork(string $work): void
    {
        if (is_link($work) || !is_dir($work)
            || !preg_match('/^\.one-click-(?:pending|completed)-[a-f0-9]{32}$/D', basename($work))
            || dirname($work) !== $this->vault) throw new RuntimeException('Unsafe work directory cleanup.');
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            if ($item->isLink()) throw new RuntimeException('Refusing linked work cleanup.');
            if ($item->isDir()) { if (!rmdir($item->getPathname())) throw new RuntimeException('Cleanup failed.'); }
            elseif ($item->isFile()) { if (!unlink($item->getPathname())) throw new RuntimeException('Cleanup failed.'); }
            else throw new RuntimeException('Unexpected staging entry.');
        }
        if (!rmdir($work)) throw new RuntimeException('Cleanup failed.');
    }
}
