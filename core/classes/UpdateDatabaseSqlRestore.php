<?php

declare(strict_types=1);

/**
 * QA2b candidate: browser-compatible SQL restore to an EMPTY, pre-provisioned schema.
 * No mysql CLI, CREATE DATABASE, root grants, DROP TABLE, or live-site restore.
 * This class is intentionally NOT connected to the production One-Click updater.
 */
final class UpdateDatabaseSqlRestore
{
    private const MAX_STATEMENT_BYTES = 33554432; // Bound memory per SQL statement to 32 MiB.
    private const MAX_STATEMENTS = 2000000;

    /**
     * The archive must be the immutable, verified SiteBackup data.tar.gz from
     * UpdateRecoveryBundle. The destination must already exist and be EMPTY.
     *
     * @return array{tables:int,triggers:int,statements:int}
     */
    public static function restoreToEmpty(
        mysqli $db,
        string $archive,
        string $expectedArchiveSha256,
        string $expectedDatabase
    ): array {
        if (!preg_match('/^[a-f0-9]{64}$/D', $expectedArchiveSha256)
            || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $expectedDatabase)
            || is_link($archive) || !is_file($archive) || !is_readable($archive)) {
            throw new RuntimeException('Invalid SQL restore authority or archive.');
        }
        $rawHash = hash_file('sha256', $archive);
        if (!is_string($rawHash) || !hash_equals($expectedArchiveSha256, $rawHash)) {
            throw new RuntimeException('Recovery archive digest mismatch.');
        }
        $report = (new UpdateRecoveryDataArchive())->verify($archive);
        if (!hash_equals($rawHash, $report['sha256'])) {
            throw new RuntimeException('Recovery archive verification mismatch.');
        }
        $currentDatabase = $db->query('SELECT DATABASE()')->fetch_row()[0] ?? null;
        if (!is_string($currentDatabase) || $currentDatabase !== $expectedDatabase) {
            throw new RuntimeException('Refusing restore to a different database.');
        }
        $tableCount = (int)$db->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetch_row()[0];
        $triggerCount = (int)$db->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->fetch_row()[0];
        $routineCount = (int)$db->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()')->fetch_row()[0];
        $eventCount = (int)$db->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.EVENTS WHERE EVENT_SCHEMA = DATABASE()')->fetch_row()[0];
        if ($tableCount !== 0 || $triggerCount !== 0 || $routineCount !== 0 || $eventCount !== 0) {
            throw new RuntimeException('SQL restore destination must be completely empty.');
        }
        if ((int)$report['tables'] < 1 || (int)$report['triggers'] < 0) {
            throw new RuntimeException('Invalid recovery archive inventory.');
        }
        $absolute = realpath($archive);
        if (!is_string($absolute)) throw new RuntimeException('Cannot resolve recovery archive.');
        $sqlPath = 'phar://' . $absolute . '/database.sql';
        $stream = @fopen($sqlPath, 'rb');
        if (!is_resource($stream)) throw new RuntimeException('Verified SQL archive member unavailable.');
        $oldFk = (int)$db->query('SELECT @@SESSION.foreign_key_checks')->fetch_row()[0];
        $tables = 0;
        $triggers = 0;
        $total = 0;
        try {
            // The trusted dump itself contains SET FOREIGN_KEY_CHECKS=0/1.
            // Restore the session even if a statement fails midway.
            foreach (self::statements($stream) as $statement) {
                ++$total;
                if ($total > self::MAX_STATEMENTS) throw new RuntimeException('Excessive SQL statement count.');
                $kind = self::statementKind($statement);
                if ($kind === 'table') ++$tables;
                if ($kind === 'trigger') ++$triggers;
                $result = $db->query($statement);
                if ($result instanceof mysqli_result) $result->free();
            }
        } finally {
            fclose($stream);
            $db->query('SET SESSION FOREIGN_KEY_CHECKS=' . ($oldFk === 0 ? '0' : '1'));
        }
        if ($tables !== (int)$report['tables'] || $triggers !== (int)$report['triggers']) {
            throw new RuntimeException('SQL statements do not match sealed backup inventory.');
        }
        $actualTables = (int)$db->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetch_row()[0];
        $actualTriggers = (int)$db->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->fetch_row()[0];
        if ($actualTables !== $tables || $actualTriggers !== $triggers) {
            throw new RuntimeException('SQL restore postflight inventory mismatch.');
        }
        return ['tables' => $tables, 'triggers' => $triggers, 'statements' => $total];
    }

    /**
     * Stream the exact line-terminated SQL format produced by SiteBackup.
     * Client DELIMITER directives are interpreted locally, never sent to MySQL.
     * A backup with unsupported or malformed statements fails CLOSED.
     *
     * @param resource $stream
     * @return Generator<int,string>
     */
    public static function statements($stream): Generator
    {
        if (!is_resource($stream)) throw new InvalidArgumentException('SQL input must be a stream.');
        $delimiter = ';';
        $statement = '';
        $logical = '';
        $lineNum = 0;
        $hadSql = false;
        // fgets(..., 65536) is bounded, including for giant INSERT lines.
        // A logical line may span multiple fgets reads; cap memory to 32 MiB.
        while (($piece = fgets($stream, 65536)) !== false) {
            $logical .= $piece;
            if (strlen($logical) > self::MAX_STATEMENT_BYTES) {
                throw new RuntimeException('Oversized SQL line in recovery archive.');
            }
            if (!str_ends_with($piece, "\n") && !feof($stream)) continue;
            ++$lineNum;
            $line = $logical;
            $logical = '';
            $trimmed = trim($line);
            if ($statement === '' && ($trimmed === '' || str_starts_with($trimmed, '-- '))) continue;
            if ($statement === '' && str_starts_with($trimmed, 'DELIMITER')) {
                if ($trimmed === 'DELIMITER $$') $delimiter = '$$';
                elseif ($trimmed === 'DELIMITER ;') $delimiter = ';';
                else throw new RuntimeException('Unsupported SQL delimiter directive.');
                continue;
            }
            if ($trimmed === '' && $statement === '') continue;
            $statement .= $line;
            if (strlen($statement) > self::MAX_STATEMENT_BYTES) {
                throw new RuntimeException('Oversized SQL recovery statement.');
            }
            $end = rtrim($statement);
            if (str_ends_with($end, $delimiter)) {
                $sql = trim(substr($end, 0, -strlen($delimiter)));
                if ($sql === '') throw new RuntimeException('Empty SQL recovery statement.');
                self::statementKind($sql);
                $hadSql = true;
                yield $sql;
                $statement = '';
            }
        }
        if ($logical !== '') {
            ++$lineNum;
            $statement .= $logical;
            $logical = '';
            $end = rtrim($statement);
            if (str_ends_with($end, $delimiter)) {
                $sql = trim(substr($end, 0, -strlen($delimiter)));
                self::statementKind($sql);
                $hadSql = true;
                yield $sql;
                $statement = '';
            }
        }
        if (trim($statement) !== '' || $delimiter !== ';' || !$hadSql) {
            throw new RuntimeException('Unfinished or invalid SQL recovery stream.');
        }
    }

    private static function statementKind(string $statement): string
    {
        if (preg_match('/^SET NAMES utf8mb4$/iD', $statement)
            || preg_match("/^SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'$/iD", $statement)
            || preg_match('/^SET FOREIGN_KEY_CHECKS = [01]$/iD', $statement)) return 'setting';
        if (preg_match('/^CREATE TABLE `[A-Za-z0-9_]+` \(/isD', $statement)) return 'table';
        if (preg_match('/^INSERT INTO `[A-Za-z0-9_]+` VALUES \(/isD', $statement)) return 'insert';
        if (preg_match('/^CREATE TRIGGER `(?:media_ref_)?[A-Za-z_][A-Za-z0-9_]{0,63}` (?:BEFORE|AFTER) (?:INSERT|UPDATE|DELETE) ON `[A-Za-z_][A-Za-z0-9_]{0,63}` FOR EACH ROW /isD', $statement)) return 'trigger';
        throw new RuntimeException('Unsupported statement in SQL recovery archive.');
    }
}
