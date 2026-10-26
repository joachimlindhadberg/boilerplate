<?php
/**
 * Full site *data* backup: SQL database dump plus current uploads
 * and any existing legacy files (read-only compatibility). Never archives source code or credentials.
 *
 * Restore is deliberately manual to an EMPTY database; do not treat this as
 * an importable JSON export. Admin-only operation, invoked after CSRF check.
 */
final class SiteBackup
{
    /**
     * Returns the temporary .tar.gz file. Caller MUST delete it in finally.
     * Requires ext-phar and ext-zlib; usable without shell commands.
     */
    public function create(): string
    {
        if (!class_exists(PharData::class) || !extension_loaded('zlib')) {
            throw new RuntimeException('Site backup requires the PHP Phar and zlib extensions.');
        }
        $work = sys_get_temp_dir() . '/cms-backup-' . bin2hex(random_bytes(12));
        if (!mkdir($work, 0700) || !is_dir($work)) {
            throw new RuntimeException('Could not create secure temporary backup directory.');
        }
        $tarPath = $work . '/site-data.tar';
        $gzPath = $tarPath . '.gz';
        try {
            $sqlPath = $work . '/database.sql';
            $summary = $this->writeDatabaseSql($sqlPath);
            $files = self::uploadFiles(PUBLICPATH);
            foreach ($summary['media_paths'] as $mediaPath) {
                // A backup with silently missing managed media is NOT a
                // complete data backup. Fail before offering a download.
                $relative = ltrim($mediaPath, '/');
                $candidates = [$relative];
                // Existing CMS installations support either storage location
                // for old media URLs; the backup must honor the same fallback.
                if (str_starts_with($relative, 'uploads/')) {
                    $candidates[] = 'assets/' . $relative;
                } elseif (str_starts_with($relative, 'assets/uploads/')) {
                    $candidates[] = substr($relative, strlen('assets/'));
                }
                $found = false;
                foreach ($candidates as $candidate) {
                    if (isset($files['public_html/' . $candidate])) { $found = true; break; }
                }
                if (!$found) {
                    throw new RuntimeException('Referenced media is missing or outside managed uploads: ' . $mediaPath);
                }
            }
            $fileHashes = [];
            foreach ($files as $entry => $path) {
                $sha = hash_file('sha256', $path);
                if ($sha === false) throw new RuntimeException('Cannot verify an upload file: ' . $entry);
                $fileHashes[$entry] = $sha;
            }
            // Only advertise a legacy backup root when actual legacy files exist.
            $contents = ['database.sql', 'manifest.json', 'public_html/uploads/'];
            foreach (array_keys($files) as $entry) {
                if (str_starts_with($entry, 'public_html/assets/uploads/')) {
                    $contents[] = 'public_html/assets/uploads/';
                    break;
                }
            }
            $manifest = [
                'format' => 'boilerplate-site-data-backup',
                'version' => 1,
                'generated_utc' => gmdate('c'),
                'cms_version' => defined('BOILERPLATE_VERSION') ? BOILERPLATE_VERSION : 'unknown',
                'database_tables' => $summary['tables'],
                'database_rows' => $summary['rows'],
                'database_triggers' => $summary['triggers'],
                'database_sha256' => hash_file('sha256', $sqlPath),
                'upload_files' => count($files),
                'upload_sha256' => $fileHashes,
                'contents' => $contents,
                'contains_sensitive_data' => true,
                'excludes' => ['.env', 'application code', 'private logs', 'unregistered directories'],
                'restore' => 'Fresh empty database only; see docs/releases/BACKUP-MEDIA-V2.3.6.md',
            ];
            $manifestFile = $work . '/manifest.json';
            if (file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('Failed to write backup manifest.');
            }
            $archive = new PharData($tarPath);
            $archive->addFile($sqlPath, 'database.sql');
            $archive->addFile($manifestFile, 'manifest.json');
            foreach ($files as $entry => $fullPath) {
                $archive->addFile($fullPath, $entry);
            }
            unset($archive);
            $archive = new PharData($tarPath);
            $archive->compress(Phar::GZ);
            unset($archive);
            if (!is_file($gzPath) || filesize($gzPath) === 0) {
                throw new RuntimeException('The backup archive could not be created.');
            }
            @chmod($gzPath, 0600);
            // The final file is relocated out of the temporary work directory,
            // allowing temporary plain-text SQL to be cleaned up immediately.
            $destination = tempnam(sys_get_temp_dir(), 'cms-download-');
            if ($destination === false || !rename($gzPath, $destination)) {
                if ($destination !== false) @unlink($destination);
                throw new RuntimeException('The backup file could not be prepared for download.');
            }
            @chmod($destination, 0600);
            return $destination;
        } finally {
            foreach ([$work . '/database.sql', $work . '/manifest.json', $gzPath, $tarPath] as $file) {
                if (is_file($file)) @unlink($file);
            }
            @rmdir($work);
        }
    }

    /**
     * Safe, predictable upload entries; never follows symbolic links.
     * Throws on unreadable directories/files rather than silently omitting them.
     * @return array<string,string> archive entry => absolute path
     */
    public static function uploadFiles(string $publicRoot): array
    {
        $files = [];
        foreach (['uploads', 'assets/uploads'] as $dir) {
            $base = rtrim($publicRoot, '/') . '/' . $dir;
            if (!file_exists($base) && !is_link($base)) continue;
            if (!is_dir($base) || is_link($base) || !is_readable($base)) {
                throw new RuntimeException('Upload directory is unreadable or a symbolic link: ' . $dir);
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $path = $file->getPathname();
                if ($file->isLink() || ! $file->isFile() || ! $file->isReadable()) {
                    throw new RuntimeException('Unable to safely back up an upload file: ' . $path);
                }
                // Security rules are application code, never user backup data.
                // A restored media-only archive must not overwrite upload protection.
                if ($file->getFilename() === '.htaccess') {
                    continue;
                }
                $relative = substr($path, strlen($base) + 1);
                if (!is_string($relative) || str_contains($relative, '..') || str_contains($relative, '\\')) {
                    throw new RuntimeException('Unsafe upload file path.');
                }
                $files['public_html/' . $dir . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = $path;
            }
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /** @return array{tables:array<string,int>,rows:int,media_paths:list<string>,triggers:int} */
    private function writeDatabaseSql(string $file): array
    {
        $db = new mysqli(DbConf::host(), DbConf::user(), DbConf::pass(), DbConf::name(), DbConf::port());
        $db->set_charset('utf8mb4');
        $stream = fopen($file, 'wb');
        if ($stream === false) {
            $db->close();
            throw new RuntimeException('Could not write temporary SQL backup.');
        }
        @chmod($file, 0600);
        $tables = [];
        $rowTotal = 0;
        $mediaPaths = [];
        try {
            $db->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $tableResult = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $names = [];
            while ($row = $tableResult->fetch_row()) {
                $names[] = (string)$row[0];
            }
            $tableResult->free();
            if ($names === []) throw new RuntimeException('No database tables found; backup aborted.');
            self::write($stream, "-- Boilerplate CMS site data backup. RESTORE TO AN EMPTY DATABASE ONLY.\n"
                . "-- Contains accounts/password hashes and form submissions. Store securely.\n"
                . "SET NAMES utf8mb4;\nSET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
            sort($names, SORT_STRING);
            foreach ($names as $table) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) throw new RuntimeException('Unsafe database table name.');
                $escapedTable = '`' . $table . '`';
                $schemaResult = $db->query('SHOW CREATE TABLE ' . $escapedTable);
                $schema = $schemaResult->fetch_assoc();
                $schemaResult->free();
                $definition = (string)($schema['Create Table'] ?? '');
                if ($definition === '') throw new RuntimeException('Could not read table schema for ' . $table);
                self::write($stream, '-- Table: ' . $table . "\n" . $definition . ";\n");
                $rows = $db->query('SELECT * FROM ' . $escapedTable, MYSQLI_USE_RESULT);
                $fields = $rows->fetch_fields();
                $count = 0;
                try {
                    while ($row = $rows->fetch_assoc()) {
                        if ($table === 'media' && isset($row['file_path']) && (string)$row['file_path'] !== '') {
                            $mediaPaths[] = (string)$row['file_path'];
                        }
                        $values = [];
                        foreach ($fields as $field) {
                            $value = $row[$field->name];
                            $values[] = $value === null ? 'NULL' : self::sqlLiteral($db, (string)$value, $field);
                        }
                        self::write($stream, 'INSERT INTO ' . $escapedTable . ' VALUES (' . implode(', ', $values) . ");\n");
                        $count++;
                    }
                } finally {
                    $rows->free();
                }
                $tables[$table] = $count;
                $rowTotal += $count;
                self::write($stream, "\n");
            }
            // Triggers are essential for the safety of the indexed media
            // reference registry. Restore them AFTER rows have been loaded,
            // so importing the backup cannot mutate its snapshot/dirty state.
            // The SQL client handles compound trigger bodies via DELIMITER.
            $triggerResult = $db->query(
                'SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, '
                . 'ACTION_TIMING, ACTION_STATEMENT '
                . 'FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() '
                . 'ORDER BY TRIGGER_NAME'
            );
            $triggerCount = 0;
            $mediaTriggers = [];
            self::write($stream, "\n-- Restore database triggers (required for media integrity)\nDELIMITER $$\n");
            while ($trigger = $triggerResult->fetch_assoc()) {
                $name = (string)$trigger['TRIGGER_NAME'];
                $table = (string)$trigger['EVENT_OBJECT_TABLE'];
                $action = (string)$trigger['EVENT_MANIPULATION'];
                $timing = (string)$trigger['ACTION_TIMING'];
                $statement = (string)$trigger['ACTION_STATEMENT'];
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $name)
                    || !preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $table)
                    || !in_array($action, ['INSERT', 'UPDATE', 'DELETE'], true)
                    || !in_array($timing, ['BEFORE', 'AFTER'], true)
                    || $statement === '' || str_contains($statement, '$$')) {
                    throw new RuntimeException('Unsafe or unsupported database trigger in backup: ' . $name);
                }
                self::write($stream, 'CREATE TRIGGER `' . $name . '` ' . $timing . ' ' . $action
                    . ' ON `' . $table . '` FOR EACH ROW ' . $statement . "$$\n");
                ++$triggerCount;
                if (str_starts_with($name, 'media_ref_')) $mediaTriggers[] = $name;
            }
            $triggerResult->free();
            if (in_array('media_reference_state', $names, true) && count($mediaTriggers) !== 9) {
                throw new RuntimeException('Complete site backup refused: media-reference triggers are missing.');
            }
            self::write($stream, "DELIMITER ;\nSET FOREIGN_KEY_CHECKS = 1;\n");
            $db->commit();
        } catch (Throwable $e) {
            try { $db->rollback(); } catch (Throwable $ignored) {}
            throw $e;
        } finally {
            fclose($stream);
            $db->close();
        }
        return ['tables' => $tables, 'rows' => $rowTotal, 'media_paths' => $mediaPaths, 'triggers' => $triggerCount];
    }

    private static function sqlLiteral(mysqli $db, string $value, object $field): string
    {
        // MySQL's JSON protocol type (245) may be advertised with the BINARY
        // flag and charsetnr=63 by mysqli. Unlike BLOBs it MUST be written as
        // utf8mb4 text; a bare 0x... binary literal fails with MySQL #3144.
        // Check the type before the binary flags, including on PHP builds that
        // do not expose MYSQLI_TYPE_JSON as a named constant.
        $jsonType = defined('MYSQLI_TYPE_JSON') ? MYSQLI_TYPE_JSON : 245;
        if ((int) $field->type === $jsonType) {
            return "'" . $db->real_escape_string($value) . "'";
        }
        // Binary/varbinary/BLOB fields must still use hexadecimal literals.
        $binaryFlag = defined('MYSQLI_BINARY_FLAG') ? MYSQLI_BINARY_FLAG : 128;
        if (($field->flags & $binaryFlag) && $field->charsetnr === 63) {
            return '0x' . bin2hex($value);
        }
        return "'" . $db->real_escape_string($value) . "'";
    }

    /** @param resource $stream */
    private static function write($stream, string $text): void
    {
        $length = strlen($text);
        $written = 0;
        while ($written < $length) {
            $count = fwrite($stream, substr($text, $written));
            if ($count === false || $count === 0) throw new RuntimeException('Could not write SQL backup.');
            $written += $count;
        }
    }
}
