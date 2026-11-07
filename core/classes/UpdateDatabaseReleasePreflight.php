<?php

declare(strict_types=1);

/**
 * F4a: read-only database source plan for a candidate production release ZIP.
 *
 * This is a SECOND diagnostic after the existing UpdatePackageReview and
 * official-asset checks, not a replacement for either. It never requires or
 * executes migration PHP, calls SQL, acquires a writer lock, or grants update
 * permission. A controller may later supply the read-only
 * MigrationRunner::status() rows. Caller pins the *exact* candidate ZIP hash.
 */
final class UpdateDatabaseReleasePreflight
{
    private const MAX_ARCHIVE_BYTES = 20971520; // same as UpdatePackageReview
    private const MAX_ENTRIES = 1500;
    private const MAX_MIGRATION_BYTES = 1048576;
    private const MAX_EXPANDED_BYTES = 134217728;

    private string $root;

    public function __construct(?string $root = null)
    {
        $path = $root ?? dirname(__DIR__, 2);
        $real = realpath($path);
        if (!is_string($real) || !is_dir($real) || is_link($path)) {
            throw new RuntimeException('Unsafe CMS root for migration preflight.');
        }
        $this->root = rtrim($real, DIRECTORY_SEPARATOR);
    }

    /**
     * @param list<array{id:string,state:string}> $migrationStates
     *  Rows from MigrationRunner::status() (READ ONLY), never from a client form.
     * @return array{status:string,install_ready:bool,reason:string,new_migrations:list<string>,
     *               existing_migrations:int,checks:list<array{key:string,status:string}>}
     */
    public function inspect(string $zipPath, string $expectedSha256, array $migrationStates): array
    {
        $report = [
            'status' => 'blocked', 'install_ready' => false,
            'reason' => 'preflight_failed', 'new_migrations' => [],
            'existing_migrations' => 0, 'checks' => [],
        ];
        $add = static function (string $key, string $state) use (&$report): void {
            $report['checks'][] = ['key' => $key, 'status' => $state];
        };
        try {
            // No file or database writes. No paths from the archive are extracted.
            if (!preg_match('/^[a-f0-9]{64}$/D', $expectedSha256)
                || is_link($zipPath) || !is_file($zipPath)) {
                return self::blocked($report, $add, 'archive_identity');
            }
            $size = filesize($zipPath);
            if (!is_int($size) || $size < 1 || $size > self::MAX_ARCHIVE_BYTES
                || !hash_equals($expectedSha256, (string)hash_file('sha256', $zipPath))) {
                return self::blocked($report, $add, 'archive_identity');
            }
            $add('archive_identity', 'good');

            $installedPhp = $this->installedPhpMigrations();
            $installedSql = $this->installedSqlSources();
            $report['existing_migrations'] = count($installedPhp);
            if (!$this->validStates($migrationStates, $installedPhp)) {
                return self::blocked($report, $add, 'installed_migration_status');
            }
            $add('installed_migration_status', 'good');

            if (!class_exists('ZipArchive')) {
                return self::blocked($report, $add, 'zip_unavailable');
            }
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
                return self::blocked($report, $add, 'archive_structure');
            }
            try {
                [$targetPhp, $targetSql] = $this->readSourceHashes($zip);
            } finally {
                $zip->close();
            }
            $add('archive_structure', 'good');

            foreach ($installedPhp as $path => $digest) {
                if (!isset($targetPhp[$path]) || !hash_equals($digest, $targetPhp[$path]['sha256'])) {
                    return self::blocked($report, $add, 'historical_php_migrations');
                }
            }
            $add('historical_php_migrations', 'good');
            foreach ($installedSql as $path => $digest) {
                if (!isset($targetSql[$path]) || !hash_equals($digest, $targetSql[$path])) {
                    return self::blocked($report, $add, 'historical_sql_sources');
                }
            }
            if (count($targetSql) !== count($installedSql)) {
                // Even a newly added historical SQL script is NOT a PHP migration.
                return self::blocked($report, $add, 'historical_sql_sources');
            }
            $add('historical_sql_sources', 'good');

            $new = array_diff_key($targetPhp, $installedPhp);
            if (count($new) > 64) {
                return self::blocked($report, $add, 'migration_count');
            }
            foreach ($new as $path => $record) {
                // PHP parsing does not execute the code. Syntax and the expected
                // *shape* are minimal diagnostics, NOT a safety proof.
                try {
                    $tokens = token_get_all($record['content'], TOKEN_PARSE);
                } catch (ParseError $e) {
                    return self::blocked($report, $add, 'migration_php_syntax');
                }
                if (!self::looksLikeClosureMigration($tokens)) {
                    return self::blocked($report, $add, 'migration_php_format');
                }
                $report['new_migrations'][] = self::migrationId($path);
            }
            sort($report['new_migrations'], SORT_STRING);
            $add('new_migration_syntax_and_format', $new ? 'review' : 'good');
            $add('migration_execution_and_restore', 'review');
            $report['status'] = 'review';
            $report['reason'] = $new ? 'new_migrations_require_restore_proof' : 'no_new_migrations';
            // NEVER true; F4a cannot approve a DB-aware installation.
            return $report;
        } catch (Throwable $e) {
            // Do not leak archive/member contents, DB details or absolute paths.
            return self::blocked($report, $add, 'preflight_failed');
        }
    }

    /** @param callable(string,string):void $add */
    private static function blocked(array $report, callable $add, string $reason): array
    {
        $report['status'] = 'blocked';
        $report['install_ready'] = false;
        $report['new_migrations'] = [];
        $report['reason'] = $reason;
        $add($reason, 'blocked');
        return $report;
    }

    /** @return array<string,string> */
    private function installedPhpMigrations(): array
    {
        $directories = [$this->root . '/database/updates'];
        foreach (glob($this->root . '/modules/*/migrations', GLOB_ONLYDIR) ?: [] as $dir) {
            $directories[] = $dir;
        }
        $result = [];
        foreach ($directories as $dir) {
            if (!is_dir($dir) || is_link($dir) || is_link(dirname($dir))) {
                throw new RuntimeException('Unsafe installed migration directory.');
            }
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (!is_file($file) || is_link($file)) {
                    throw new RuntimeException('Unexpected installed migration entry.');
                }
                $relative = substr($file, strlen($this->root) + 1);
                if (!self::isPhpMigration($relative)) {
                    throw new RuntimeException('Unexpected installed migration filename.');
                }
                $sha = hash_file('sha256', $file);
                if (!is_string($sha)) throw new RuntimeException('Cannot hash installed migration.');
                $result[$relative] = $sha;
            }
        }
        return $result;
    }

    /** @return array<string,string> */
    private function installedSqlSources(): array
    {
        $dir = $this->root . '/database/migrations';
        $install = $this->root . '/database/install';
        $schema = $install . '/empty-schema.sql';
        if (!is_dir($dir) || is_link($dir) || is_link($install)
            || !is_file($schema) || is_link($schema)) {
            throw new RuntimeException('Unsafe historical SQL sources.');
        }
        $paths = [$schema];
        foreach (glob($dir . '/*') ?: [] as $file) {
            $paths[] = $file;
        }
        $result = [];
        foreach ($paths as $file) {
            if (!is_file($file) || is_link($file)) {
                throw new RuntimeException('Invalid installed historical SQL source.');
            }
            $relative = substr($file, strlen($this->root) + 1);
            if (!self::isSqlSource($relative)) {
                throw new RuntimeException('Unexpected installed SQL source.');
            }
            $size = filesize($file);
            if (!is_int($size) || $size > self::MAX_MIGRATION_BYTES) {
                throw new RuntimeException('Oversized installed SQL source.');
            }
            $sha = hash_file('sha256', $file);
            if (!is_string($sha)) throw new RuntimeException('Cannot hash installed SQL source.');
            $result[$relative] = $sha;
        }
        return $result;
    }

    /** @param list<array{id:string,state:string}> $states */
    private function validStates(array $states, array $installed): bool
    {
        $expectedIds = [];
        foreach (array_keys($installed) as $path) {
            $expectedIds[self::migrationId($path)] = true;
        }
        $seen = [];
        foreach ($states as $record) {
            if (!is_array($record) || !is_string($record['id'] ?? null)
                || ($record['state'] ?? null) !== 'applied'
                || !isset($expectedIds[$record['id']]) || isset($seen[$record['id']])) {
                return false;
            }
            $seen[$record['id']] = true;
        }
        return count($seen) === count($expectedIds);
    }

    /** @return array{0:array<string,array{sha256:string,content:string}>,1:array<string,string>} */
    private function readSourceHashes(ZipArchive $zip): array
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) {
            throw new RuntimeException('Unexpected ZIP entry count.');
        }
        $php = [];
        $sql = [];
        $seen = [];
        $expanded = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $info = $zip->statIndex($i);
            if (!is_array($info) || !is_string($info['name'] ?? null)
                || !isset($info['size'], $info['comp_size'])) {
                throw new RuntimeException('Invalid archive member metadata.');
            }
            $name = $info['name'];
            $dir = str_ends_with($name, '/');
            if (!UpdatePackageReview::isSafeMember($name, $dir)) {
                throw new RuntimeException('Unsafe ZIP member.');
            }
            if ((int)($info['encryption_method'] ?? 0) !== 0) {
                throw new RuntimeException('Encrypted ZIP member.');
            }
            $lower = strtolower(rtrim($name, '/'));
            if (isset($seen[$lower])) throw new RuntimeException('Duplicate ZIP member.');
            $seen[$lower] = true;
            $size = (int)$info['size'];
            $packed = (int)$info['comp_size'];
            if ($size < 0 || $packed < 0 || ($dir && $size !== 0)) {
                throw new RuntimeException('Invalid ZIP member size.');
            }
            $expanded += $size;
            if ($expanded > self::MAX_EXPANDED_BYTES) throw new RuntimeException('ZIP expansion exceeds review limit.');
            $opsys = 0;
            $attributes = 0;
            if (!$zip->getExternalAttributesIndex($i, $opsys, $attributes)
                || $opsys !== ZipArchive::OPSYS_UNIX
                || (($attributes >> 16) & 0170000) !== ($dir ? 0040000 : 0100000)) {
                throw new RuntimeException('Unsafe ZIP member type.');
            }
            if ($dir) continue;
            $path = substr($name, strlen('boilerplate/'));
            $phpArea = str_starts_with($path, 'database/updates/')
                || (bool)preg_match('~^modules/[^/]+/migrations/~D', $path);
            $sqlArea = str_starts_with($path, 'database/migrations/')
                || $path === 'database/install/empty-schema.sql';
            if (!$phpArea && !$sqlArea) continue;
            if (($phpArea && !self::isPhpMigration($path))
                || ($sqlArea && !self::isSqlSource($path))
                || $size < 1 || $size > self::MAX_MIGRATION_BYTES) {
                throw new RuntimeException('Unsupported database source in ZIP.');
            }
            $content = $zip->getFromIndex($i, self::MAX_MIGRATION_BYTES);
            if (!is_string($content) || strlen($content) !== $size) {
                throw new RuntimeException('Unreadable database source in ZIP.');
            }
            if ($phpArea) {
                $php[$path] = ['sha256' => hash('sha256', $content), 'content' => $content];
            } else {
                $sql[$path] = hash('sha256', $content);
            }
        }
        return [$php, $sql];
    }

    private static function isPhpMigration(string $path): bool
    {
        return (bool)preg_match('~^(?:database/updates|modules/[a-z0-9_-]+/migrations)/[0-9]{8}_[a-z0-9_-]+\.php$~D', $path);
    }

    private static function isSqlSource(string $path): bool
    {
        return $path === 'database/install/empty-schema.sql'
            || (bool)preg_match('~^database/migrations/[^/]+\.sql$~D', $path);
    }

    private static function migrationId(string $path): string
    {
        if (str_starts_with($path, 'database/updates/')) {
            return 'core:' . substr(basename($path), 0, -4);
        }
        $parts = explode('/', $path);
        return 'module:' . $parts[1] . ':' . substr(basename($path), 0, -4);
    }

    /**
     * Recognizes the supported basic PHP migration declaration, without ever
     * treating syntax or a declaration as evidence that the code is safe.
     * Complex/custom migration formats remain blocked for separate review.
     * @param array<array{0:int,1:string,2:int}|string> $tokens
     */
    private static function looksLikeClosureMigration(array $tokens): bool
    {
        $normalized = [];
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
                $normalized[] = $token[1];
            } else {
                $normalized[] = $token;
            }
        }
        $count = count($normalized);
        for ($i = 0; $i < $count; $i++) {
            if ($normalized[$i] !== 'return') continue;
            $j = $i + 1;
            if (($normalized[$j] ?? null) === 'static') $j++;
            if (($normalized[$j] ?? null) !== 'function') continue;
            if (array_slice($normalized, $j + 1, 7) === ['(', 'Db', '$db', ')', ':', 'void', '{']) {
                return true;
            }
        }
        return false;
    }
}
