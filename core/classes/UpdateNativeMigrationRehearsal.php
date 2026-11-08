<?php

declare(strict_types=1);

/**
 * F4c.5 QA1: real MySQL DDL through a strictly isolated, non-production adapter.
 *
 * No CMS route uses this source. This is neither a production migration
 * executor nor an extension of MigrationRunner. It cannot read .env/DbConf,
 * select a production database, resolve a recovery journal, or enable updates.
 * The only SQL statements it can execute are fixed QA fixtures below.
 */
interface UpdateF4c5MigrationTarget
{
    public function assertPrivateIdentity(string $root): void;
    public function schemaName(): string;
    /** @return array{tables:int,triggers:int,migrations:int,flag:bool,marker:bool} */
    public function inventory(): array;
    public function executeQaStep(int $index): void;
}

/** Only permitted REAL SQL implementation; never substitute DbConf credentials. */
final class UpdateF4c5PrivateMySqlTarget implements UpdateF4c5MigrationTarget
{
    private const SCHEMA = '/\Aboilerplate_f4b1_[a-f0-9]{16}\z/D';
    private mysqli $connection;
    private string $schema;
    private string $serverRoot;

    public function __construct(mysqli $connection, string $schema, string $serverRoot)
    {
        if (preg_match(self::SCHEMA, $schema) !== 1) {
            throw new RuntimeException('A dedicated F4b.1 disposable schema is required.');
        }
        $this->connection = $connection;
        $this->schema = $schema;
        $this->serverRoot = $serverRoot;
    }

    public function schemaName(): string { return $this->schema; }

    public function assertPrivateIdentity(string $root): void
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('Native SQL QA is CLI only.');
        $server = realpath($this->serverRoot);
        if (!is_string($server) || is_link($this->serverRoot) || dirname($server) !== realpath('/tmp')
            || preg_match('/\Abp-f4c5-native-[a-f0-9]{16}\z/D', basename($server)) !== 1
            || !is_dir($server) || (fileperms($server) & 0077) !== 0) {
            throw new RuntimeException('Wrong private MySQL server root.');
        }
        $expectedSocket = $server . '/mysql.sock';
        $expectedData = $server . '/data';
        if (!is_dir($expectedData) || is_link($expectedData) || !is_file($server . '/private-mysql-qa.token')
            || is_link($server . '/private-mysql-qa.token') || !is_readable($server . '/private-mysql-qa.token')
            || (fileperms($server . '/private-mysql-qa.token') & 0077) !== 0
            || !is_string(realpath($expectedData))) {
            throw new RuntimeException('Private MySQL fixture not provisioned.');
        }
        $socket = @filetype($expectedSocket);
        if ($socket !== 'socket' || is_link($expectedSocket)) {
            throw new RuntimeException('Not a private QA Unix socket.');
        }
        $result = $this->connection->query(
            'SELECT DATABASE(), @@GLOBAL.socket, @@GLOBAL.datadir, '
            . '@@GLOBAL.skip_networking, @@GLOBAL.log_bin, CURRENT_USER(), VERSION()'
        );
        if (!$result instanceof mysqli_result) throw new RuntimeException('Cannot attest native MySQL.');
        $row = $result->fetch_row();
        if (!is_array($row) || count($row) !== 7
            || $row[0] !== $this->schema || $row[1] !== $expectedSocket
            || realpath((string)$row[2]) !== realpath($expectedData)
            || (int)$row[3] !== 1 || (int)$row[4] !== 0
            || preg_match('/\Abp_f4b3_w_[a-f0-9]{10}@localhost\z/D', (string)$row[5]) !== 1
            || !str_starts_with((string)$row[6], '9.5.0')) {
            throw new RuntimeException('Private native MySQL identity mismatch.');
        }
    }

    /** Read-only physical inventory, independently observed around every DDL. */
    public function inventory(): array
    {
        $this->scalar('SELECT 1');
        $columns = $this->scalar("SELECT COUNT(*) FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pages' "
            . "AND COLUMN_NAME='f4c5_qa_flag' AND COLUMN_TYPE='tinyint unsigned'");
        $anyColumns = $this->scalar("SELECT COUNT(*) FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pages' AND COLUMN_NAME='f4c5_qa_flag'");
        $marker = $this->scalar("SELECT COUNT(*) FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME='f4c5_qa_marker'");
        $tables = $this->scalar("SELECT COUNT(*) FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
        $triggers = $this->scalar('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()');
        $migrations = $this->scalar('SELECT COUNT(*) FROM `schema_migrations`');
        if ($columns !== $anyColumns || $anyColumns > 1 || $marker > 1) {
            throw new RuntimeException('Unexpected QA DDL shape.');
        }
        return ['tables' => $tables, 'triggers' => $triggers, 'migrations' => $migrations,
            'flag' => $columns === 1, 'marker' => $marker === 1];
    }

    /** Exactly TWO hardcoded QA statements. Never accepts SQL from HTTP, ZIP, or caller. */
    public function executeQaStep(int $index): void
    {
        $sql = match ($index) {
            0 => 'ALTER TABLE `pages` ADD COLUMN `f4c5_qa_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0',
            1 => 'CREATE TABLE `f4c5_qa_marker` (`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY, '
                . '`message` VARCHAR(32) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            default => throw new RuntimeException('Unsupported native QA migration.'),
        };
        // MySQL DDL commits independently. Exception leaves INFLIGHT journal.
        if (!$this->connection->query($sql)) {
            throw new RuntimeException('Native QA DDL failed.');
        }
    }

    private function scalar(string $sql): int
    {
        $result = $this->connection->query($sql);
        if (!$result instanceof mysqli_result) throw new RuntimeException('SQL inventory unavailable.');
        $row = $result->fetch_row();
        if (!is_array($row) || count($row) !== 1 || !ctype_digit((string)$row[0])) {
            throw new RuntimeException('Invalid SQL inventory value.');
        }
        return (int)$row[0];
    }
}

/** Durable, fail-closed QA state machine; never clears the recovery marker. */
final class UpdateNativeMigrationRehearsal
{
    private const ROOT = '/\Abp-f4c5-run-[a-f0-9]{16}\z/D';
    private const SCHEMA = '/\Aboilerplate_f4b1_[a-f0-9]{16}\z/D';
    private const DIGEST = '/\A[a-f0-9]{64}\z/D';
    private const MARKER = '/\A\.db-update-pending-[a-f0-9]{32}\z/D';
    private string $root;
    private string $vault;
    private string $lock;
    private UpdateF4c5MigrationTarget $target;

    public function __construct(string $root, UpdateF4c5MigrationTarget $target)
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('Native migration QA is CLI-only.');
        $temp = realpath('/tmp');
        $real = realpath($root);
        if (!is_string($temp) || !is_string($real) || is_link($root)
            || dirname($real) !== $temp || preg_match(self::ROOT, basename($real)) !== 1
            || !is_dir($real) || (fileperms($real) & 0077) !== 0) {
            throw new RuntimeException('F4c.5 requires private direct /tmp fixture.');
        }
        if (preg_match(self::SCHEMA, $target->schemaName()) !== 1) {
            throw new RuntimeException('Only isolated restore schemas are allowed.');
        }
        $this->root = $real;
        $this->vault = $real . '/storage/update-recovery';
        $this->lock = $real . '/f4c5.lock';
        $this->target = $target;
    }

    public function begin(string $backupSha, string $releaseSha, string $baselineSha): array
    {
        foreach ([$backupSha, $releaseSha, $baselineSha] as $hash) self::assertSha($hash);
        return $this->exclusive(function () use ($backupSha, $releaseSha, $baselineSha): array {
            $this->target->assertPrivateIdentity($this->root);
            $this->validateInventory($this->target->inventory(), 0);
            $this->prepareVault();
            if ($this->journals() !== []) throw new RuntimeException('An existing run must not be replaced.');
            $id = bin2hex(random_bytes(16));
            $record = ['format' => 'boilerplate-f4c5-native-qa1', 'id' => $id,
                'schema' => $this->target->schemaName(), 'phase' => 'prepared',
                'completed' => 0, 'inflight' => null, 'backup_sha256' => $backupSha,
                'release_sha256' => $releaseSha, 'baseline_sha256' => $baselineSha,
                'blocked' => true, 'install_ready' => false, 'production_migration' => false];
            // Durable recovery marker written BEFORE any native SQL mutation.
            $this->atomic($this->vault . '/.db-update-pending-' . $id, $record, false);
            return $this->snapshot();
        });
    }

    /** One real SQL DDL per invocation. Crash is ALWAYS ambiguous (no auto retry). */
    public function step(?callable $checkpoint = null): array
    {
        return $this->exclusive(function () use ($checkpoint): array {
            [$marker, $j] = $this->load();
            if (!in_array($j['phase'], ['prepared', 'step_applied'], true) || $j['completed'] >= 2) {
                throw new RuntimeException('Ambiguous or completed DDL. Manual recovery only.');
            }
            $this->target->assertPrivateIdentity($this->root);
            $n = $j['completed'];
            $this->validateInventory($this->target->inventory(), $n);
            $j['phase'] = 'inflight';
            $j['inflight'] = $n;
            $this->atomic($marker, $j, true);
            if ($checkpoint !== null) $checkpoint('before-ddl');
            // Native SQL DDL is a non-transactional side effect.
            $this->target->executeQaStep($n);
            if ($checkpoint !== null) $checkpoint('after-ddl');
            $this->validateInventory($this->target->inventory(), $n + 1);
            $j['completed'] = $n + 1;
            $j['inflight'] = null;
            $j['phase'] = 'step_applied';
            $this->atomic($marker, $j, true);
            return $this->snapshot();
        });
    }

    public function verify(string $expectedInventorySha): array
    {
        self::assertSha($expectedInventorySha);
        return $this->exclusive(function () use ($expectedInventorySha): array {
            [$path, $j] = $this->load();
            if ($j['phase'] !== 'step_applied' || $j['completed'] !== 2) {
                throw new RuntimeException('Native migrations not fully and safely recorded.');
            }
            $this->target->assertPrivateIdentity($this->root);
            $inventory = $this->target->inventory();
            $this->validateInventory($inventory, 2);
            $proof = hash('sha256', json_encode($inventory, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (!hash_equals($proof, $expectedInventorySha)) {
                throw new RuntimeException('Independent native SQL inventory mismatch.');
            }
            $j['phase'] = 'verified_qa_only';
            $j['inventory_sha256'] = $proof;
            $this->atomic($path, $j, true);
            // No unlock method and no production activation in this class.
            return $this->snapshot();
        });
    }

    public function status(): array
    {
        try { return $this->snapshot(); }
        catch (Throwable $e) {
            return ['state'=>'manual_recovery','completed'=>0,'blocked'=>true,
                'install_ready'=>false,'can_retry'=>false];
        }
    }

    private function snapshot(): array
    {
        [, $j] = $this->load();
        return ['state' => $j['phase'] === 'inflight' ? 'manual_recovery' : $j['phase'],
            'completed' => $j['completed'], 'blocked' => true, 'install_ready' => false,
            'can_retry' => in_array($j['phase'], ['prepared', 'step_applied'], true) && $j['completed'] < 2];
    }

    /** @return array{string,array<string,mixed>} */
    private function load(): array
    {
        $files = $this->journals();
        if (count($files) !== 1 || preg_match(self::MARKER, basename($files[0])) !== 1) {
            throw new RuntimeException('Missing or conflicting durable recovery marker.');
        }
        $j = $this->readJson($files[0]);
        if (($j['format'] ?? null) !== 'boilerplate-f4c5-native-qa1'
            || !is_string($j['id'] ?? null) || !hash_equals('.db-update-pending-' . $j['id'], basename($files[0]))
            || !is_string($j['schema'] ?? null) || $j['schema'] !== $this->target->schemaName()
            || !in_array($j['phase'] ?? null, ['prepared','inflight','step_applied','verified_qa_only'], true)
            || !is_int($j['completed'] ?? null) || $j['completed'] < 0 || $j['completed'] > 2
            || ($j['blocked'] ?? null) !== true || ($j['install_ready'] ?? null) !== false
            || ($j['production_migration'] ?? null) !== false) {
            throw new RuntimeException('Corrupt native recovery journal.');
        }
        foreach (['backup_sha256','release_sha256','baseline_sha256'] as $field) self::assertSha($j[$field] ?? null);
        if (!array_key_exists('inflight', $j) || ($j['phase'] === 'inflight' && $j['inflight'] !== $j['completed'])
            || ($j['phase'] !== 'inflight' && $j['inflight'] !== null)
            || ($j['phase'] === 'prepared' && $j['completed'] !== 0)
            || ($j['phase'] === 'step_applied' && $j['completed'] < 1)
            || ($j['phase'] === 'verified_qa_only' && ($j['completed'] !== 2
                || !is_string($j['inventory_sha256'] ?? null)
                || preg_match(self::DIGEST, $j['inventory_sha256']) !== 1))) {
            throw new RuntimeException('Inconsistent native recovery checkpoint.');
        }
        return [$files[0], $j];
    }

    private function validateInventory(array $v, int $completed): void
    {
        $expected = ['tables' => 26 + ($completed === 2 ? 1 : 0),
            'triggers' => 9, 'migrations' => 8,
            'flag' => $completed >= 1, 'marker' => $completed === 2];
        if ($v !== $expected) throw new RuntimeException('Real MySQL inventory diverges from durable checkpoint.');
    }

    private static function assertSha(mixed $value): void
    {
        if (!is_string($value) || preg_match(self::DIGEST, $value) !== 1) {
            throw new RuntimeException('Invalid SHA-256 proof.');
        }
    }

    private function prepareVault(): void
    {
        foreach ([$this->root . '/storage', $this->vault] as $dir) {
            if (is_link($dir) || (file_exists($dir) && !is_dir($dir))) throw new RuntimeException('Unsafe QA vault.');
            if (!is_dir($dir) && !@mkdir($dir, 0700)) throw new RuntimeException('Cannot create QA vault.');
            $mode = @fileperms($dir);
            if (!is_int($mode) || ($mode & 0077) !== 0) throw new RuntimeException('QA vault must be private.');
        }
    }

    /** @return list<string> */
    private function journals(): array
    {
        $storage = $this->root . '/storage';
        if (is_link($storage) || is_link($this->vault) || !is_dir($storage) || !is_dir($this->vault)
            || (fileperms($storage) & 0077) !== 0 || (fileperms($this->vault) & 0077) !== 0) {
            throw new RuntimeException('Recovery vault missing or unsafe.');
        }
        $all = [];
        foreach (new DirectoryIterator($this->vault) as $entry) {
            if ($entry->isDot()) continue;
            if ($entry->isLink() || !$entry->isFile()) throw new RuntimeException('Unexpected recovery entry.');
            $all[] = $entry->getPathname();
        }
        return $all;
    }

    private function readJson(string $path): array
    {
        if (is_link($path) || !is_file($path) || !is_readable($path) || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('Unsafe recovery document.');
        }
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > 32768) throw new RuntimeException('Recovery document size invalid.');
        $raw = file_get_contents($path);
        if (!is_string($raw) || strlen($raw) !== $size) throw new RuntimeException('Partial journal read.');
        $j = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($j) || array_is_list($j)) throw new RuntimeException('Invalid recovery JSON.');
        return $j;
    }

    private function atomic(string $path, array $json, bool $replace): void
    {
        if (is_link($path) || (file_exists($path) && !$replace)) throw new RuntimeException('Cannot overwrite journal.');
        $raw = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($raw) > 32768) throw new RuntimeException('Oversized recovery journal.');
        $tmp = dirname($path) . '/.f4c5-write-' . bin2hex(random_bytes(16));
        $stream = fopen($tmp, 'xb');
        if (!is_resource($stream)) throw new RuntimeException('Cannot write private journal.');
        try {
            if (!chmod($tmp, 0600) || fwrite($stream, $raw) !== strlen($raw)
                || !fflush($stream) || (function_exists('fsync') && !fsync($stream))) {
                throw new RuntimeException('Failed durable journal write.');
            }
        } finally { fclose($stream); }
        try {
            if (is_link($path) || (!$replace && file_exists($path)) || !rename($tmp, $path)) {
                throw new RuntimeException('Journal rename failed.');
            }
        } finally { if (file_exists($tmp)) unlink($tmp); }
        // Process-kill persistence tested; no portable directory fsync proof.
    }

    private function exclusive(callable $work): array
    {
        if (is_link($this->lock) || (file_exists($this->lock) && !is_file($this->lock))) {
            throw new RuntimeException('Unsafe QA lock.');
        }
        $fd = fopen($this->lock, 'c+b');
        if (!is_resource($fd)) throw new RuntimeException('QA lock unavailable.');
        try {
            chmod($this->lock, 0600);
            if (!flock($fd, LOCK_EX | LOCK_NB)) throw new RuntimeException('Concurrent migration blocked.');
            return $work();
        } finally { flock($fd, LOCK_UN); fclose($fd); }
    }
}
