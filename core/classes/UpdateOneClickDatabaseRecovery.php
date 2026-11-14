<?php

declare(strict_types=1);

/**
 * S5.2: Restore source bridge for a verified One-Click recovery bundle.
 *
 * This is NOT a populated-schema reset, an update runner, an authorization
 * service, or an endpoint. No caller can regard a review result as install
 * approval. The SQL executor can restore only a completely EMPTY schema.
 *
 * When an authenticated, journal-authorized recovery controller is later
 * connected, it MUST own the exclusive UpdateMaintenanceGate, independently
 * prove the selected database identity and all affected objects, and retain
 * a durable DB-recovery marker BEFORE any non-transactional DROP / DDL.
 */
final class UpdateOneClickDatabaseRecovery
{
    private const ID_RE = '/\Arecovery-[a-f0-9]{32}\z/D';
    private const SHA_RE = '/\A[a-f0-9]{64}\z/D';

    private string $root;
    private string $vault;

    public function __construct(?string $root = null)
    {
        $path = $root ?? dirname(__DIR__, 2);
        $real = realpath($path);
        if (!is_string($real) || !is_dir($real) || is_link($path)) {
            throw new RuntimeException('Unsafe recovery root.');
        }
        $this->root = rtrim($real, DIRECTORY_SEPARATOR);
        $this->vault = $this->root . '/storage/update-recovery';
    }

    /**
     * READ-ONLY. Reuses the real immutable bundle and SQL/media validation.
     * @return array{status:'review_only',install_ready:false,backup_id:string,sha256:string,tables:int,triggers:int}
     */
    public function inspect(string $bundleId): array
    {
        [$manifest, $source] = $this->verifiedSource($bundleId);
        $archive = (new UpdateRecoveryDataArchive())->verify($source);
        if (!hash_equals($manifest['data_sha256'], $archive['sha256'])
            || $manifest['database_tables'] !== $archive['tables']
            || $manifest['database_triggers'] !== $archive['triggers']) {
            throw new RuntimeException('Verified backup data inventory changed.');
        }
        return [
            'status' => 'review_only', 'install_ready' => false,
            'backup_id' => $bundleId, 'sha256' => $manifest['data_sha256'],
            'tables' => $archive['tables'], 'triggers' => $archive['triggers'],
        ];
    }

    /**
     * Restore ONLY a pre-existing EMPTY database using normal CMS credentials.
     *
     * The caller, NOT this library, must independently authorize recovery and
     * prove that this operation is part of a durable locked DB-recovery journal.
     * No endpoint in Stable 2.5.1 invokes this method yet.
     *
     * @return array{tables:int,triggers:int,statements:int}
     */
    public function restoreToEmpty(mysqli $db, string $bundleId): array
    {
        // Never take a client-supplied DB name. The SQL executor independently
        // checks SELECT DATABASE() and refuses any populated target.
        if (!class_exists('DbConf')) {
            throw new RuntimeException('CMS database configuration unavailable.');
        }
        $database = DbConf::name();
        if (!is_string($database) || preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $database) !== 1) {
            throw new RuntimeException('Unsafe configured database identity.');
        }
        $report = $this->inspect($bundleId);
        $source = $this->bundlePath($bundleId) . '/data.tar.gz';
        return UpdateDatabaseSqlRestore::restoreToEmpty(
            $db, $source, $report['sha256'], $database
        );
    }

    /** @return array{0:array<string,mixed>,1:string} */
    private function verifiedSource(string $bundleId): array
    {
        $dir = $this->bundlePath($bundleId);
        // The original bundle verifier checks both code/config and data,
        // immutable manifests, archive bytes and protected permissions.
        $verified = (new UpdateRecoveryBundle($this->root))->verify($bundleId);
        if (($verified['status'] ?? '') !== 'verified_archives_restore_not_rehearsed') {
            throw new RuntimeException('Unapproved recovery bundle state.');
        }
        $manifestFile = $dir . '/manifest.json';
        $archive = $dir . '/data.tar.gz';
        if (is_link($manifestFile) || !is_file($manifestFile)
            || is_link($archive) || !is_file($archive)) {
            throw new RuntimeException('Invalid recovery bundle members.');
        }
        $raw = file_get_contents($manifestFile);
        if (!is_string($raw) || strlen($raw) > 65536) {
            throw new RuntimeException('Invalid recovery manifest.');
        }
        $m = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($m)
            || ($m['format'] ?? '') !== 'boilerplate-recovery-bundle'
            || ($m['version'] ?? null) !== 1
            || ($m['approved_for_automatic_update'] ?? null) !== false
            || ($m['requires_restore_rehearsal'] ?? null) !== true
            || ($m['requires_write_quiescence'] ?? null) !== true
            || !is_string($m['data_sha256'] ?? null)
            || preg_match(self::SHA_RE, $m['data_sha256']) !== 1
            || !is_int($m['database_tables'] ?? null)
            || $m['database_tables'] < 1
            || !is_int($m['database_triggers'] ?? null)
            || $m['database_triggers'] < 0
            || !hash_equals($m['data_sha256'], (string)hash_file('sha256', $archive))) {
            throw new RuntimeException('Untrusted SQL recovery bundle.');
        }
        return [$m, $archive];
    }

    private function bundlePath(string $bundleId): string
    {
        if (preg_match(self::ID_RE, $bundleId) !== 1) {
            throw new RuntimeException('Invalid recovery bundle ID.');
        }
        $storage = $this->root . '/storage';
        if (is_link($storage) || is_link($this->vault)
            || !is_dir($this->vault) || ((fileperms($this->vault) & 0077) !== 0)) {
            throw new RuntimeException('Unsafe private recovery vault.');
        }
        $dir = $this->vault . '/' . $bundleId;
        if (is_link($dir) || !is_dir($dir)
            || ((fileperms($dir) & 0077) !== 0)) {
            throw new RuntimeException('Unsafe recovery bundle directory.');
        }
        return $dir;
    }
}
