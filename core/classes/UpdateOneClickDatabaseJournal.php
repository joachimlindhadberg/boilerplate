<?php

declare(strict_types=1);

/**
 * S5.2 Stage 4: durable, fail-closed joint file/database update journal.
 *
 * This is a STORAGE PRIMITIVE, not an update authorization, SQL executor,
 * administrative endpoint or permission to reset a populated database.
 * The caller MUST hold UpdateMaintenanceGate EX; have authenticated tools.manage
 * plus CSRF before creating the journal; and independently verify the full
 * backup, migration/ZIP plans, quiescence and release before writing anything.
 *
 * Marker names match UpdateDurableRecoveryGate: a pending/blocked marker keeps
 * all normal CMS requests blocked even if PHP dies mid-migration. No class here
 * knows how to clear the marker following a damaged database: that needs a
 * separately approved and independently authenticated restore controller.
 */
final class UpdateOneClickDatabaseJournal
{
    private const FORMAT = 'boilerplate-one-click-db-journal-v1';
    private const ID = '/\A[a-f0-9]{32}\z/D';
    private const SHA = '/\A[a-f0-9]{64}\z/D';
    private const SCHEMA = '/\A[A-Za-z0-9_]{1,64}\z/D';
    private const MIGRATION = '/\A(?:core|module:[a-z0-9][a-z0-9_-]*):[0-9]{8}_[a-z0-9_-]+\z/D';

    private string $root;
    private string $vault;

    public function __construct(string $root)
    {
        $resolved = realpath($root);
        if (!is_string($resolved) || is_link($root) || !is_dir($resolved)) {
            throw new RuntimeException('Unsafe database journal CMS root.');
        }
        $this->root = rtrim($resolved, '/');
        $this->vault = $this->root . '/storage/update-recovery';
    }

    /**
     * Persist a blocked marker BEFORE the first code write or database DDL.
     * The returned recovery secret is deliberately NOT written to disk. No
     * endpoint currently accepts it. A future separate recovery controller
     * must add CSRF/Origin, rate limits, same-site HTTPS and user consent.
     *
     * @param list<string> $migrationIds
     * @return array{id:string,recovery_secret:string}
     */
    public function begin(
        string $schema,
        string $backupId,
        string $backupSha256,
        string $releaseSha256,
        string $filePlanSha256,
        array $migrationIds,
        ?string $preissuedRecoverySecret = null
    ): array {
        $this->assertVault();
        if (preg_match(self::SCHEMA, $schema) !== 1
            || preg_match('/\Arecovery-[a-f0-9]{32}\z/D', $backupId) !== 1) {
            throw new RuntimeException('Invalid database or backup identity.');
        }
        foreach ([$backupSha256, $releaseSha256, $filePlanSha256] as $digest) {
            if (preg_match(self::SHA, $digest) !== 1) {
                throw new RuntimeException('Invalid update source digest.');
            }
        }
        if ($migrationIds === [] || count($migrationIds) > 64) {
            throw new RuntimeException('Invalid pending migrations.');
        }
        foreach ($migrationIds as $id) {
            if (!is_string($id) || preg_match(self::MIGRATION, $id) !== 1) {
                throw new RuntimeException('Invalid migration identifier.');
            }
        }
        if (count($migrationIds) !== count(array_unique($migrationIds))) {
            throw new RuntimeException('Duplicate migration IDs.');
        }
        // Fail closed on ANY existing database or file update transaction.
        // Other processes must be serialized by the caller's exclusive gate.
        foreach (new DirectoryIterator($this->vault) as $entry) {
            if ($entry->isDot()) continue;
            $name = $entry->getFilename();
            if (str_starts_with($name, '.db-update-') || str_starts_with($name, '.one-click-pending-')) {
                throw new RuntimeException('A pending update or recovery already exists.');
            }
        }
        $id = bin2hex(random_bytes(16));
        $secret = $preissuedRecoverySecret ?? bin2hex(random_bytes(32));
        if (preg_match(self::SHA, $secret) !== 1) {
            throw new RuntimeException('Invalid preissued recovery capability.');
        }
        sort($migrationIds, SORT_STRING);
        $record = [
            'format' => self::FORMAT, 'id' => $id, 'schema' => $schema,
            'backup_id' => $backupId, 'backup_sha256' => $backupSha256,
            'release_sha256' => $releaseSha256, 'file_plan_sha256' => $filePlanSha256,
            'migration_ids' => $migrationIds,
            'recovery_secret_sha256' => hash('sha256', $secret),
            'recovery_expires_at' => time() + 3600,
            'phase' => 'prepared', 'blocked' => true, 'install_ready' => false,
        ];
        $this->writeAtomically($this->path($id), $record, true);
        return ['id' => $id, 'recovery_secret' => $secret];
    }

    /** Read and validate the exact persisted source/phase; never auto-recovers. */
    public function inspect(string $id): array
    {
        $this->assertVault();
        $path = $this->path($id);
        if (is_link($path) || !is_file($path)) {
            throw new RuntimeException('Database recovery journal does not exist.');
        }
        clearstatcache(true, $path);
        if ((fileperms($path) & 0077) !== 0 || (filesize($path) ?: 0) > 8192) {
            throw new RuntimeException('Unsafe database journal permissions or size.');
        }
        $raw = file_get_contents($path);
        if (!is_string($raw) || strlen($raw) < 50 || strlen($raw) > 8192) {
            throw new RuntimeException('Invalid database journal content.');
        }
        try { $state = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
        catch (Throwable $e) { throw new RuntimeException('Invalid database journal JSON.', 0, $e); }
        if (!is_array($state) || ($state['format'] ?? '') !== self::FORMAT
            || ($state['id'] ?? '') !== $id
            || preg_match(self::SCHEMA, (string)($state['schema'] ?? '')) !== 1
            || preg_match('/\Arecovery-[a-f0-9]{32}\z/D', (string)($state['backup_id'] ?? '')) !== 1
            || !in_array($state['phase'] ?? null, [
                'prepared', 'files_written', 'database_running', 'database_written',
                'postflight_verified', 'recovery_required', 'recovering', 'restored'
            ], true)
            || !is_int($state['recovery_expires_at'] ?? null)
            || ($state['recovery_expires_at'] ?? 0) < 1
            || ($state['blocked'] ?? null) !== true
            || ($state['install_ready'] ?? null) !== false
            || !is_array($state['migration_ids'] ?? null)
            || count($state['migration_ids']) < 1 || count($state['migration_ids']) > 64) {
            throw new RuntimeException('Unrecognized or unblocked database journal.');
        }
        foreach (['backup_sha256','release_sha256','file_plan_sha256','recovery_secret_sha256'] as $key) {
            if (!is_string($state[$key] ?? null) || preg_match(self::SHA, $state[$key]) !== 1) {
                throw new RuntimeException('Corrupt database journal digest.');
            }
        }
        if (count($state['migration_ids']) !== count(array_unique($state['migration_ids']))) {
            throw new RuntimeException('Duplicate recorded migration.');
        }
        foreach ($state['migration_ids'] as $migration) {
            if (!is_string($migration) || preg_match(self::MIGRATION, $migration) !== 1) {
                throw new RuntimeException('Corrupt recorded migration.');
            }
        }
        $sorted = $state['migration_ids'];
        sort($sorted, SORT_STRING);
        if ($sorted !== $state['migration_ids']) {
            throw new RuntimeException('Unsorted journal migration inventory.');
        }
        return $state;
    }

    /**
     * Recovery capability possession is independent of MySQL login.
     * This verifies the secret only; it does NOT perform recovery or confer
     * authorization to drop tables. An independent recovery endpoint is needed.
     */
    public function verifyRecoverySecret(string $id, string $secret): bool
    {
        if (preg_match(self::SHA, $secret) !== 1) return false;
        $record = $this->inspect($id);
        return hash_equals($record['recovery_secret_sha256'], hash('sha256', $secret));
    }

    /** Durable state transition. Only the caller can establish postflight proof. */
    public function checkpoint(string $id, string $next): void
    {
        $record = $this->inspect($id);
        $allowed = [
            'prepared' => ['files_written', 'recovery_required'],
            'files_written' => ['database_running', 'recovery_required'],
            'database_running' => ['database_written', 'recovery_required'],
            'database_written' => ['postflight_verified', 'recovery_required'],
            'postflight_verified' => ['recovery_required'],
            'recovery_required' => ['recovering'],
            'recovering' => ['restored', 'recovery_required'],
            'restored' => [],
        ];
        if (!in_array($next, $allowed[$record['phase']] ?? [], true)) {
            throw new LogicException('Illegal persistent database cutover transition.');
        }
        $record['phase'] = $next;
        $this->writeAtomically($this->path($id), $record, false);
    }

    /**
     * No generic finalize/unlock method by design. A full production installer
     * must independently compare code+SQL+media postflight and atomically
     * commit/remove its durable journal. Until then gate remains blocked.
     */

    private function assertVault(): void
    {
        $storage = $this->root . '/storage';
        if (is_link($storage) || is_link($this->vault)
            || !is_dir($storage) || !is_dir($this->vault)) {
            throw new RuntimeException('Missing or unsafe private recovery storage.');
        }
        clearstatcache(true, $storage);
        clearstatcache(true, $this->vault);
        // The parent storage folder may be 0755 on existing CMS installs;
        // it must not be writable by another account. The vault itself is 0700.
        if ((fileperms($storage) & 0022) !== 0
            || (fileperms($this->vault) & 0077) !== 0) {
            throw new RuntimeException('Unsafe recovery storage permissions.');
        }
    }

    private function path(string $id): string
    {
        if (preg_match(self::ID, $id) !== 1) throw new RuntimeException('Invalid journal ID.');
        return $this->vault . '/.db-update-pending-' . $id;
    }

    /** Writes only 0600 files under the protected vault; never follows symlinks. */
    private function writeAtomically(string $target, array $record, bool $create): void
    {
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > 8192) throw new RuntimeException('Oversized database recovery journal.');
        if ($create) {
            if (file_exists($target) || is_link($target)) {
                throw new RuntimeException('Existing database journal target.');
            }
            $path = $target;
        } else {
            if (is_link($target) || !is_file($target)) {
                throw new RuntimeException('Database journal vanished during checkpoint.');
            }
            $path = $this->vault . '/.journal-write-' . bin2hex(random_bytes(16));
        }
        $handle = @fopen($path, 'xb');
        if (!is_resource($handle)) throw new RuntimeException('Cannot create recovery journal.');
        try {
            if (!chmod($path, 0600)) throw new RuntimeException('Cannot restrict recovery journal.');
            $remaining = $json;
            while ($remaining !== '') {
                $count = fwrite($handle, $remaining);
                if (!is_int($count) || $count === 0) throw new RuntimeException('Short recovery journal write.');
                $remaining = substr($remaining, $count);
            }
            if (!fflush($handle) || !function_exists('fsync') || !fsync($handle)) {
                throw new RuntimeException('Cannot durably flush recovery journal.');
            }
        } catch (Throwable $e) {
            fclose($handle);
            // A failed first write intentionally leaves a fail-closed marker.
            if (!$create) @unlink($path);
            throw $e;
        }
        fclose($handle);
        if (!$create && !rename($path, $target)) {
            @unlink($path);
            throw new RuntimeException('Cannot atomically advance recovery journal.');
        }
    }
}
