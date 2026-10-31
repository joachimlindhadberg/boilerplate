<?php

declare(strict_types=1);

/**
 * Read-only compatibility snapshot of the INSTALLED CMS; never applies an update.
 * It cannot certify compatibility with an unknown target release or prove a backup.
 */
final class UpdateCompatibilityPreflight
{
    private Db $db;
    private string $root;

    public function __construct(?Db $db = null, ?string $root = null)
    {
        $this->db = $db ?? new Db();
        $this->root = rtrim($root ?? dirname(__DIR__, 2), DIRECTORY_SEPARATOR);
    }

    /** @return array{version:string,overall:string,checks:list<array{key:string,status:string,detail:string}>,migration_counts:array<string,int>} */
    public function inspect(): array
    {
        $checks = [];
        $add = static function (string $key, string $status, string $detail = '') use (&$checks): void {
            $checks[] = ['key' => $key, 'status' => $status, 'detail' => $detail];
        };

        require_once $this->root . '/core/bootstrap/php-requirement.php';
        $phpOk = boilerplate_php_version_supported(PHP_VERSION_ID);
        $add('php', $phpOk ? 'good' : 'blocked', PHP_VERSION . ' · min. ' . BOILERPLATE_MIN_PHP_VERSION);

        foreach (['mysqli', 'fileinfo', 'openssl', 'mbstring'] as $extension) {
            $add('extension_' . $extension, extension_loaded($extension) ? 'good' : 'blocked');
        }
        $backupTools = class_exists(PharData::class) && extension_loaded('zlib');
        $add('backup_tools', $backupTools ? 'good' : 'review');

        // Reading the built-in version constant does not inspect secrets or connect to external services.
        $version = defined('BOILERPLATE_VERSION') ? (string)BOILERPLATE_VERSION : 'unknown';
        if ($version === 'unknown') {
            $source = @file_get_contents($this->root . '/public_html/incl/init.php');
            if (is_string($source) && preg_match(UpdateReleaseVersion::SOURCE_PATTERN, $source, $matches)) {
                $version = $matches[1];
            }
        }
        $add('version', $version === 'unknown' ? 'blocked' : 'good', $version);

        // Exactly the historical minimum structural guard used by MigrationRunner::apply().
        $baseTables = ['users', 'pages', 'page_blocks', 'settings', 'security_rate_limits'];
        $migrationCounts = ['applied' => 0, 'pending' => 0, 'checksum-mismatch' => 0, 'missing-file' => 0];
        try {
            $rows = $this->db->fetchArray(
                'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?, ?, ?, ?)',
                $baseTables
            );
            $existing = array_map(static fn(array $row): string => (string)($row['TABLE_NAME'] ?? ''), $rows);
            $missing = array_values(array_diff($baseTables, $existing));
            $add('base_schema', $missing ? 'blocked' : 'good', count($existing) . '/5');

            $registry = (int)$this->db->fetchValue(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'"
            ) > 0;
            $add('registry', $registry ? 'good' : 'blocked');

            if ($registry && !$missing) {
                $states = (new MigrationRunner($this->db, $this->root))->status();
                foreach ($states as $row) {
                    $state = (string)($row['state'] ?? '');
                    if (isset($migrationCounts[$state])) $migrationCounts[$state]++;
                }
                $mismatch = $migrationCounts['checksum-mismatch'];
                $missingFiles = $migrationCounts['missing-file'];
                $pending = $migrationCounts['pending'];
                $status = ($mismatch > 0 || $missingFiles > 0) ? 'blocked' : ($pending > 0 ? 'review' : 'good');
                $detail = implode(' · ', [
                    'Applied ' . $migrationCounts['applied'],
                    'Pending ' . $pending,
                    'Missing ' . $missingFiles,
                    'Checksum mismatch ' . $mismatch,
                ]);
                $add('migrations', $status, $detail);
            } else {
                $add('migrations', 'blocked');
            }
        } catch (Throwable $e) {
            // Do not expose SQL errors, private hostnames or credentials in admin output.
            $add('database_check', 'blocked');
        }

        // This does not inspect or upload target releases, change database state,
        // run code migrations, or claim a working backup/rollback strategy.
        $add('target_package', 'review');
        $add('backup_verified', 'review');
        $overall = count(array_filter($checks, static fn(array $row): bool => $row['status'] === 'blocked')) > 0
            ? 'blocked' : 'review';

        return [
            'version' => $version,
            'overall' => $overall,
            'checks' => $checks,
            'migration_counts' => $migrationCounts,
        ];
    }
}
