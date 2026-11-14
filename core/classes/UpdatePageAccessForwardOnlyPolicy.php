<?php

declare(strict_types=1);

/**
 * Stable 2.5.1 narrow forward-only exception. It authorizes one immutable,
 * audited Core migration and nothing else. No approval from HTTP/env fields.
 * Unknown SQL / arbitrary PHP module migrations remain unsupported.
 */
final class UpdatePageAccessForwardOnlyPolicy
{
    public const ID = 'core:20261105_000_page_access';
    public const PATH = 'database/updates/20261105_000_page_access.php';
    public const SOURCE_SHA256 = '51f597fdff8540d29adc9ba1350297ebc78bf35d0df55fcaebe0075a2b8ef31a';

    public static function inspectSource(string $source): void
    {
        if (is_link($source) || !is_file($source) || basename($source) !== '20261105_000_page_access.php'
            || ($size = filesize($source)) === false || $size < 1 || $size > 16384
            || !hash_equals(self::SOURCE_SHA256, (string)hash_file('sha256', $source))) {
            throw new RuntimeException('Unapproved or modified migration source.');
        }
    }

    public static function exists(mysqli $db): bool
    {
        $r = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='page_access'");
        if (!$r instanceof mysqli_result) throw new RuntimeException('Cannot inspect migration target.');
        return (int)$r->fetch_row()[0] !== 0;
    }

    /** A previously existing target must not be accepted by IF NOT EXISTS. */
    public static function assertFreshTarget(mysqli $db): void
    {
        if (self::exists($db)) throw new RuntimeException('page_access already exists; manual schema review required.');
        $parent = $db->query("SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pages'")->fetch_all(MYSQLI_ASSOC);
        if (count($parent) !== 1 || strcasecmp((string)$parent[0]['ENGINE'], 'InnoDB') !== 0) {
            throw new RuntimeException('Expected InnoDB parent table is missing.');
        }
        $columns = $db->query("SELECT COLUMN_TYPE,IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pages' AND COLUMN_NAME='id'")->fetch_all(MYSQLI_ASSOC);
        if (count($columns) !== 1 || strtolower((string)$columns[0]['COLUMN_TYPE']) !== 'int'
            || $columns[0]['IS_NULLABLE'] !== 'NO') {
            throw new RuntimeException('Unsupported pages primary-key definition.');
        }
        $pk = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pages' "
            . "AND CONSTRAINT_NAME='PRIMARY' AND COLUMN_NAME='id'")->fetch_row();
        if ((int)$pk[0] !== 1) throw new RuntimeException('Missing pages primary key.');
    }

    /** Exact semantic postflight for the pinned page_access creation. */
    public static function assertSchema(mysqli $db): void
    {
        $table = $db->query("SELECT ENGINE,TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='page_access'")->fetch_all(MYSQLI_ASSOC);
        if (count($table) !== 1 || strcasecmp((string)$table[0]['ENGINE'], 'InnoDB') !== 0
            || strcasecmp((string)$table[0]['TABLE_COLLATION'], 'utf8mb4_unicode_ci') !== 0) {
            throw new RuntimeException('Unexpected page_access table identity.');
        }
        $cols = $db->query("SELECT COLUMN_NAME,COLUMN_TYPE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE,COLUMN_DEFAULT "
            . "FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() "
            . "AND TABLE_NAME='page_access' ORDER BY ORDINAL_POSITION")->fetch_all(MYSQLI_ASSOC);
        if (count($cols) !== 3 || $cols[0]['COLUMN_NAME'] !== 'page_id'
            || strtolower((string)$cols[0]['COLUMN_TYPE']) !== 'int' || $cols[0]['IS_NULLABLE'] !== 'NO'
            || $cols[1]['COLUMN_NAME'] !== 'password_hash' || $cols[1]['DATA_TYPE'] !== 'varchar'
            || (int)$cols[1]['CHARACTER_MAXIMUM_LENGTH'] !== 255 || $cols[1]['IS_NULLABLE'] !== 'NO'
            || $cols[2]['COLUMN_NAME'] !== 'updated_at' || $cols[2]['DATA_TYPE'] !== 'datetime'
            || $cols[2]['IS_NULLABLE'] !== 'NO'
            || !preg_match('/^current_timestamp(?:\(\))?$/i', (string)($cols[2]['COLUMN_DEFAULT'] ?? ''))) {
            throw new RuntimeException('Unexpected page_access columns.');
        }
        $pk = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='page_access' "
            . "AND CONSTRAINT_NAME='PRIMARY'")->fetch_all(MYSQLI_ASSOC);
        if (count($pk) !== 1 || $pk[0]['COLUMN_NAME'] !== 'page_id') {
            throw new RuntimeException('Unexpected page_access primary key.');
        }
        $fk = $db->query("SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME "
            . "FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() "
            . "AND TABLE_NAME='page_access' AND CONSTRAINT_NAME='page_access_page_fk' "
            . "AND COLUMN_NAME='page_id'")->fetch_all(MYSQLI_ASSOC);
        $cascade = $db->query("SELECT DELETE_RULE FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS "
            . "WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='page_access_page_fk'")->fetch_all(MYSQLI_ASSOC);
        if (count($fk) !== 1 || $fk[0]['REFERENCED_TABLE_NAME'] !== 'pages'
            || $fk[0]['REFERENCED_COLUMN_NAME'] !== 'id'
            || count($cascade) !== 1 || $cascade[0]['DELETE_RULE'] !== 'CASCADE') {
            throw new RuntimeException('Unexpected page_access foreign key.');
        }
    }

    public static function assertMigrationStamp(Db $db, bool $allowStamp): void
    {
        $rows = $db->fetchArray(
            'SELECT checksum_sha256 FROM schema_migrations WHERE migration_id = ?', [self::ID]
        );
        if (count($rows) > 1) throw new RuntimeException('Duplicate migration stamp.');
        if ($rows !== []) {
            if (!hash_equals(self::SOURCE_SHA256, (string)$rows[0]['checksum_sha256'])) {
                throw new RuntimeException('Conflicting page_access migration history.');
            }
            return;
        }
        if (!$allowStamp) throw new RuntimeException('Missing page_access migration stamp.');
        $db->query('INSERT INTO schema_migrations (migration_id, checksum_sha256, applied_at) VALUES (?, ?, NOW())',
            [self::ID, self::SOURCE_SHA256]);
        self::assertMigrationStamp($db, false);
    }
}
