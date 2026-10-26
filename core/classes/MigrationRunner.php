<?php

declare(strict_types=1);

/**
 * Explicit, CLI-only database migration service.
 *
 * Applied DDL is not rolled back by MySQL. Migrations MUST be idempotent and
 * may only run via core/cli/migrate.php during a planned deployment.
 */
final class MigrationRunner
{
    private Db $db;
    private string $root;

    public function __construct(?Db $db = null, ?string $root = null)
    {
        $this->db = $db ?? new Db();
        $this->root = rtrim($root ?? dirname(__DIR__, 2), DIRECTORY_SEPARATOR);
    }

    /** @return array<string,array{path:string,sha256:string}> */
    public function discover(): array
    {
        $sources = [$this->root . '/database/updates' => 'core'];
        foreach (glob($this->root . '/modules/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $slug)) {
                $sources[$dir . '/migrations'] = 'module:' . $slug;
            }
        }

        $result = [];
        foreach ($sources as $directory => $prefix) {
            foreach (glob($directory . '/*.php') ?: [] as $file) {
                $filename = basename($file);
                if (!preg_match('/^[0-9]{8}_[a-z0-9_-]+\.php$/D', $filename) || is_link($file)) {
                    throw new RuntimeException('Invalid migration filename or symlink: ' . $file);
                }
                $id = $prefix . ':' . substr($filename, 0, -4);
                if (isset($result[$id])) {
                    throw new RuntimeException('Duplicate migration ID: ' . $id);
                }
                $hash = hash_file('sha256', $file);
                if (!is_string($hash)) {
                    throw new RuntimeException('Cannot hash migration: ' . $file);
                }
                $result[$id] = ['path' => $file, 'sha256' => $hash];
            }
        }
        // Core before modules, then deterministic module order and migration name.
        uksort($result, static function (string $a, string $b): int {
            [$scopeA, $nameA] = explode(':', $a, 2);
            [$scopeB, $nameB] = explode(':', $b, 2);
            if ($scopeA === 'core' && $scopeB !== 'core') return -1;
            if ($scopeB === 'core' && $scopeA !== 'core') return 1;
            return strcmp($scopeA, $scopeB) ?: strcmp($nameA, $nameB);
        });
        return $result;
    }

    /** Read-only status, never creates or modifies schema. */
    public function status(): array
    {
        $discovered = $this->discover();
        $applied = $this->applied();
        $status = [];
        foreach ($discovered as $id => $migration) {
            $stored = $applied[$id] ?? null;
            $state = $stored === null ? 'pending' : (
                hash_equals($stored, $migration['sha256']) ? 'applied' : 'checksum-mismatch'
            );
            $status[] = ['id' => $id, 'state' => $state];
        }
        foreach ($applied as $id => $stored) {
            if (!isset($discovered[$id])) {
                $status[] = ['id' => $id, 'state' => 'missing-file'];
            }
        }
        return $status;
    }

    /** @return array<int,string> Names of migrations newly applied. */
    public function apply(): array
    {
        $this->assertSupportedBase();
        $lock = 'boilerplate_schema_' . substr(hash('sha256', DbConf::name()), 0, 24);
        if ((int)$this->db->fetchValue('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) {
            throw new RuntimeException('Another migration is running; database lock unavailable.');
        }
        try {
            $migrations = $this->discover();
            $applied = $this->applied();
            // Refuse tampered or missing previously-applied migrations BEFORE any DDL.
            foreach ($applied as $id => $hash) {
                // Uninstalled modules can leave legitimate historical records.
                // Never silently accept missing core migrations or changed files.
                if (!isset($migrations[$id])) {
                    if (str_starts_with($id, 'module:')) continue;
                    throw new RuntimeException('Core migration file missing: ' . $id);
                }
                if (!hash_equals($hash, $migrations[$id]['sha256'])) {
                    throw new RuntimeException('Migration checksum mismatch: ' . $id);
                }
            }
            if (!$this->registryExists()) {
                $this->installRegistry();
            }
            $done = [];
            foreach ($migrations as $id => $entry) {
                if (isset($applied[$id])) continue;
                $migration = require $entry['path'];
                if (!is_callable($migration)) {
                    throw new RuntimeException('Migration must return a callable: ' . $id);
                }
                // The callable must be restart-safe: CREATE/ALTER DDL commits on MySQL.
                $migration($this->db);
                $this->db->query(
                    'INSERT INTO schema_migrations (migration_id, checksum_sha256, applied_at) VALUES (?, ?, NOW())',
                    [$id, $entry['sha256']]
                );
                $done[] = $id;
            }
            return $done;
        } finally {
            $this->db->fetchValue('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private function assertSupportedBase(): void
    {
        // V2.3.7 or later only. Never attempt to repair an arbitrary older database.
        $tables = ['users', 'pages', 'page_blocks', 'settings', 'security_rate_limits'];
        $existing = $this->db->fetchArray(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?, ?, ?)',
            $tables
        );
        if (count($existing) !== count($tables)) {
            throw new RuntimeException('Expected V2.3.7 database tables are missing. Restore/upgrade to V2.3.7 before V2.3.8.');
        }
    }

    private function registryExists(): bool
    {
        return (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'"
        ) > 0;
    }

    /** @return array<string,string> */
    private function applied(): array
    {
        if (!$this->registryExists()) return [];
        $rows = $this->db->fetchArray('SELECT migration_id, checksum_sha256 FROM schema_migrations ORDER BY migration_id');
        $result = [];
        foreach ($rows as $row) {
            $result[(string)$row['migration_id']] = (string)$row['checksum_sha256'];
        }
        return $result;
    }

    private function installRegistry(): void
    {
        $this->db->query(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'migration_id varchar(191) NOT NULL PRIMARY KEY,'
            . 'checksum_sha256 char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            . 'applied_at datetime NOT NULL'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
