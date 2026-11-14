<?php

declare(strict_types=1);

/** Stage 4c production candidate. READ ONLY; never authorizes DROP/restore. */
final class UpdateOneClickDatabaseRecoveryScope
{
    private const NAME = '/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D';

    /** @param list<string> $names @return list<string> */
    private static function clean(array $names): array
    {
        if (count($names) > 10000) throw new RuntimeException('Excessive object inventory.');
        foreach ($names as $n) {
            if (!is_string($n) || preg_match(self::NAME, $n) !== 1)
                throw new RuntimeException('Unsafe database object name.');
        }
        if (count($names) !== count(array_unique($names)))
            throw new RuntimeException('Duplicate object name.');
        sort($names, SORT_STRING);
        return $names;
    }

    /**
     * Read-only inventory. No use of the live Host header, no DDL.
     * @return array{tables:list<string>,triggers:list<string>}
     */
    public static function inspectMySQL(mysqli $db, string $schema): array
    {
        if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $schema) !== 1)
            throw new RuntimeException('Invalid configured schema.');
        $dbResult = $db->query('SELECT DATABASE()');
        if (!$dbResult instanceof mysqli_result) throw new RuntimeException('Cannot identify schema.');
        try { $identity = $dbResult->fetch_row()[0] ?? null; }
        finally { $dbResult->free(); }
        if ($identity !== $schema) throw new RuntimeException('Database mismatch.');
        $objects = $db->query('SELECT TABLE_NAME AS object_name, TABLE_TYPE AS object_type FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        if (!$objects instanceof mysqli_result) throw new RuntimeException('Cannot inspect schema tables.');
        $tables = [];
        try {
            while ($row = $objects->fetch_assoc()) {
                if (($row['object_type'] ?? '') !== 'BASE TABLE')
                    throw new RuntimeException('Unsupported database view or object.');
                $tables[] = (string)$row['object_name'];
            }
        } finally { $objects->free(); }
        $objects = $db->query('SELECT TRIGGER_NAME AS object_name FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()');
        if (!$objects instanceof mysqli_result) throw new RuntimeException('Cannot inspect triggers.');
        $triggers = [];
        try { while ($row = $objects->fetch_assoc()) $triggers[] = (string)$row['object_name']; }
        finally { $objects->free(); }
        foreach (['ROUTINES WHERE ROUTINE_SCHEMA', 'EVENTS WHERE EVENT_SCHEMA'] as $part) {
            $check = $db->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.' . $part . ' = DATABASE()');
            if (!$check instanceof mysqli_result) throw new RuntimeException('Cannot inspect database objects.');
            try { $count = (int)($check->fetch_row()[0] ?? -1); }
            finally { $check->free(); }
            if ($count !== 0) throw new RuntimeException('Unsupported routines/events block recovery.');
        }
        return ['tables' => self::clean($tables), 'triggers' => self::clean($triggers)];
    }

    /**
     * Read-only decision using a sealed pre-update inventory AND an explicit
     * pre-journal list of tables/triggers that the approved release may create.
     * Unknown objects always BLOCK. Nothing here deletes data.
     * @param array{tables:list<string>,triggers:list<string>} $old
     * @param array{tables:list<string>,triggers:list<string>} $live
     * @param array{tables:list<string>,triggers:list<string>} $declared
     * @return array{status:'review_only',install_ready:false,drop_tables:list<string>}
     */
    public static function review(array $old, array $live, array $declared): array
    {
        foreach ([$old, $live, $declared] as $list) {
            if (array_keys($list) !== ['tables','triggers']
                || !array_is_list($list['tables']) || !array_is_list($list['triggers']))
                throw new RuntimeException('Invalid recovery object contract.');
        }
        $baseTables = self::clean($old['tables']);
        $baseTriggers = self::clean($old['triggers']);
        $newTables = self::clean($declared['tables']);
        $newTriggers = self::clean($declared['triggers']);
        $tables = self::clean($live['tables']);
        $triggers = self::clean($live['triggers']);
        if ($baseTables === [] || array_intersect($baseTables, $newTables)
            || array_intersect($baseTriggers, $newTriggers))
            throw new RuntimeException('Recovery plan overlaps baseline.');
        if (array_diff($tables, array_merge($baseTables,$newTables)) !== []
            || array_diff($triggers, array_merge($baseTriggers,$newTriggers)) !== [])
            throw new RuntimeException('Unknown objects: do not run destructive recovery.');
        return ['status' => 'review_only', 'install_ready' => false, 'drop_tables' => $tables];
    }

    /**
     * Extract baseline from immutable verified SiteBackup (NOT request input).
     * Reuse exact QA1c parser to establish trigger names and SQL table names.
     * @return array{tables:list<string>,triggers:list<string>}
     */
    public static function sealedBaseline(string $archive, string $sha256): array
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $sha256) !== 1
            || is_link($archive) || !is_file($archive)
            || !hash_equals($sha256, (string)hash_file('sha256', $archive)))
            throw new RuntimeException('Unverified backup bytes.');
        $report = (new UpdateRecoveryDataArchive())->verify($archive);
        if (!hash_equals($sha256, (string)$report['sha256']))
            throw new RuntimeException('Backup archive verification failed.');
        $path = realpath($archive);
        if (!is_string($path)) throw new RuntimeException('Unknown archive location.');
        $tar = new PharData($path);
        $manifest = json_decode($tar['manifest.json']->getContent(), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || !is_array($manifest['database_tables'] ?? null))
            throw new RuntimeException('Backup table manifest missing.');
        $tables = self::clean(array_keys($manifest['database_tables']));
        $sqlTables = [];
        $triggers = [];
        $stream = fopen('phar://' . $path . '/database.sql', 'rb');
        if (!is_resource($stream)) throw new RuntimeException('Cannot read sealed SQL.');
        try {
            foreach (UpdateDatabaseSqlRestore::statements($stream) as $statement) {
                if (preg_match('/\ACREATE TABLE `([A-Za-z0-9_]+)` \(/isD', $statement, $m))
                    $sqlTables[] = $m[1];
                if (preg_match('/\ACREATE TRIGGER `([A-Za-z_][A-Za-z0-9_]{0,63})` /isD', $statement, $m))
                    $triggers[] = $m[1];
            }
        } finally { fclose($stream); }
        $triggers = self::clean($triggers);
        if ($tables === [] || $tables !== self::clean($sqlTables)
            || count($tables) !== $report['tables'] || count($triggers) !== $report['triggers']
            || !hash_equals($sha256, (string)hash_file('sha256', $archive)))
            throw new RuntimeException('Sealed SQL object inventory inconsistent.');
        return ['tables' => $tables, 'triggers' => $triggers];
    }
}
