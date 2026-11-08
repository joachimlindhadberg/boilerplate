<?php

declare(strict_types=1);

/**
 * F4c.4 QA1 — browser-driven SYNTHETIC migration state-machine rehearsal.
 *
 * Not a production SQL executor. Runs ONLY in a private, direct /tmp fixture
 * named bp-f4c4-browser-<16 hex>, and only in CLI / PHP's built-in test server.
 * Never reads DbConf/.env, calls MigrationRunner::apply, connects to SQL,
 * unlocks F4c.2 recovery or changes CMS files. No CMS route uses this class.
 *
 * The public entry in qa_browser_http.php is test-only and is NOT installed.
 */
final class UpdateBrowserMigrationRehearsal
{
    private const ROOT_RE = '/\Abp-f4c4-browser-[a-f0-9]{16}\z/D';
    private const ID_RE = '/\A(?:core|module:[a-z0-9][a-z0-9_-]*):[0-9]{8}_[a-z0-9_-]+\z/D';
    private const SHA_RE = '/\A[a-f0-9]{64}\z/D';
    private const MARKER_RE = '/\A\.db-update-pending-[a-f0-9]{32}\z/D';
    private string $root;
    private string $vault;
    private string $data;
    private string $lock;

    public function __construct(string $fixtureRoot)
    {
        if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
            throw new RuntimeException('F4c.4 is private QA-only; HTTP production SAPIs prohibited.');
        }
        $parent = realpath('/tmp');
        $root = realpath($fixtureRoot);
        if (!is_string($parent) || !is_string($root) || is_link($fixtureRoot)
            || dirname($root) !== $parent || preg_match(self::ROOT_RE, basename($root)) !== 1
            || !is_dir($root) || ((int)fileperms($root) & 0077) !== 0) {
            throw new RuntimeException('F4c.4 requires a private, direct /tmp QA fixture.');
        }
        $this->root = $root;
        $this->vault = $root . '/storage/update-recovery';
        $this->data = $root . '/synthetic-database.json';
        $this->lock = $root . '/f4c4.lock';
    }

    /** @param list<array{id:string,sha256:string}> $plan */
    public function begin(array $plan, array $proofs): array
    {
        $this->validatePlan($plan);
        if (array_keys($proofs) !== ['release_sha256', 'backup_sha256', 'release_verified', 'backup_verified', 'quiescence_verified']) {
            throw new RuntimeException('Missing QA-only migration prerequisites.');
        }
        self::assertDigest($proofs['release_sha256']);
        self::assertDigest($proofs['backup_sha256']);
        if (($proofs['release_verified'] ?? null) !== true
            || ($proofs['backup_verified'] ?? null) !== true
            || ($proofs['quiescence_verified'] ?? null) !== true) {
            throw new RuntimeException('Release, backup and quiescence proofs required.');
        }
        return $this->exclusive(function () use ($plan, $proofs): array {
            $this->vaultReady();
            if ($this->markers() !== [] || file_exists($this->data) || is_link($this->data)) {
                throw new RuntimeException('Existing fixture cannot be reset or replaced.');
            }
            $id = bin2hex(random_bytes(16));
            $marker = $this->vault . '/.db-update-pending-' . $id;
            $journal = [
                'format' => 'boilerplate-f4c4-browser-migration-qa1',
                'id' => $id, 'phase' => 'prepared', 'plan' => $plan,
                'proofs' => $proofs, 'completed' => 0, 'inflight' => null,
                'blocked' => true, 'install_ready' => false,
                'production_migration' => false,
            ];
            // The F4c.2 persistent DB blocker must exist BEFORE any DB-like file.
            $this->atomicWrite($marker, $journal, false);
            $this->atomicWrite($this->data, ['applied' => []], false);
            return $this->snapshotUnlocked();
        });
    }

    /**
     * One synthetic step per browser request. The durable INFLIGHT journal is
     * committed BEFORE the pretend DDL effect, so a killed process cannot
     * automatically rerun a possibly committed DDL statement.
     * @param null|callable(string):void $checkpoint QA harness only.
     */
    public function step(?callable $checkpoint = null): array
    {
        return $this->exclusive(function () use ($checkpoint): array {
            [$path, $j] = $this->readJournal();
            if (!in_array($j['phase'], ['prepared', 'step_applied'], true)) {
                throw new RuntimeException('Ambiguous or completed migration; manual recovery required.');
            }
            $n = $j['completed'];
            $plan = $j['plan'];
            if ($n >= count($plan)) {
                throw new RuntimeException('No remaining synthetic migrations.');
            }
            $state = $this->readData();
            if (count($state['applied']) !== $n) {
                throw new RuntimeException('Synthetic effect diverged from durable journal.');
            }
            for ($i = 0; $i < $n; $i++) {
                if ($state['applied'][$i] !== $plan[$i]) {
                    throw new RuntimeException('Unexpected synthetic database history.');
                }
            }
            $j['phase'] = 'inflight';
            $j['inflight'] = $plan[$n]['id'];
            $this->atomicWrite($path, $j, true);
            if ($checkpoint !== null) $checkpoint('before-effect');
            // Never run PHP migrations or SQL. Fixed synthetic write only.
            $state['applied'][] = $plan[$n];
            $this->atomicWrite($this->data, $state, true);
            if ($checkpoint !== null) $checkpoint('after-effect');
            $j['completed'] = $n + 1;
            $j['inflight'] = null;
            $j['phase'] = 'step_applied';
            $this->atomicWrite($path, $j, true);
            return $this->snapshotUnlocked();
        });
    }

    public function verify(string $expectedHistorySha): array
    {
        self::assertDigest($expectedHistorySha);
        return $this->exclusive(function () use ($expectedHistorySha): array {
            [$path, $j] = $this->readJournal();
            if ($j['phase'] !== 'step_applied' || $j['completed'] !== count($j['plan'])) {
                throw new RuntimeException('Migration run unfinished or ambiguous.');
            }
            $state = $this->readData();
            if ($state['applied'] !== $j['plan']) {
                throw new RuntimeException('Synthetic database and journal differ.');
            }
            $actual = hash('sha256', (string)json_encode($state['applied'], JSON_UNESCAPED_SLASHES));
            if (!hash_equals($expectedHistorySha, $actual)) {
                throw new RuntimeException('Post-migration inventory fingerprint mismatch.');
            }
            $j['phase'] = 'verified_qa_only';
            $j['history_sha256'] = $actual;
            $this->atomicWrite($path, $j, true);
            // Deliberately keep F4c.2 marker, so the simulated CMS stays BLOCKED.
            return $this->snapshotUnlocked();
        });
    }

    /** Read-only, deliberately no success/unlock status and no secret values. */
    public function status(): array
    {
        try {
            return $this->snapshotUnlocked();
        } catch (Throwable $e) {
            return ['state' => 'manual_recovery', 'completed' => 0, 'total' => 0,
                'blocked' => true, 'install_ready' => false, 'can_retry' => false];
        }
    }

    private function snapshotUnlocked(): array
    {
        [, $j] = $this->readJournal();
        $phase = $j['phase'];
        return ['state' => $phase === 'inflight' ? 'manual_recovery' : $phase,
            'completed' => $j['completed'], 'total' => count($j['plan']),
            'blocked' => true, 'install_ready' => false,
            'can_retry' => in_array($phase, ['prepared', 'step_applied'], true)
                && $j['completed'] < count($j['plan'])];
    }

    /** @return array{string,array} */
    private function readJournal(): array
    {
        $paths = $this->markers();
        if (count($paths) !== 1 || preg_match(self::MARKER_RE, basename($paths[0])) !== 1) {
            throw new RuntimeException('Missing or ambiguous F4c.4 recovery journal.');
        }
        $j = $this->readJson($paths[0]);
        if (($j['format'] ?? null) !== 'boilerplate-f4c4-browser-migration-qa1'
            || !is_string($j['id'] ?? null)
            || preg_match('/\A[a-f0-9]{32}\z/D', $j['id']) !== 1
            || !hash_equals('.db-update-pending-' . $j['id'], basename($paths[0]))
            || !in_array($j['phase'] ?? null, ['prepared','inflight','step_applied','verified_qa_only'], true)
            || !is_int($j['completed'] ?? null) || !is_array($j['plan'] ?? null)
            || $j['completed'] < 0 || $j['completed'] > count($j['plan'])
            || ($j['blocked'] ?? null) !== true || ($j['install_ready'] ?? null) !== false
            || ($j['production_migration'] ?? null) !== false) {
            throw new RuntimeException('Corrupt F4c.4 recovery journal.');
        }
        $this->validatePlan($j['plan']);
        $phase = $j['phase'];
        $n = $j['completed'];
        $total = count($j['plan']);
        if (!array_key_exists('inflight', $j)
            || ($phase === 'inflight' && ($n >= $total || $j['inflight'] !== $j['plan'][$n]['id']))
            || ($phase !== 'inflight' && $j['inflight'] !== null)
            || ($phase === 'prepared' && $n !== 0)
            || ($phase === 'step_applied' && $n < 1)
            || ($phase === 'verified_qa_only' && ($n !== $total
                || !is_string($j['history_sha256'] ?? null)
                || preg_match(self::SHA_RE, $j['history_sha256']) !== 1))) {
            throw new RuntimeException('Inconsistent durable checkpoint.');
        }
        return [$paths[0], $j];
    }

    /** @return list<string> */
    private function markers(): array
    {
        // A read-only status call must never create missing recovery dirs.
        $storage = $this->root . '/storage';
        if (is_link($storage) || is_link($this->vault)
            || !is_dir($storage) || !is_dir($this->vault)) {
            throw new RuntimeException('Recovery vault missing or unsafe.');
        }
        foreach ([$storage, $this->vault] as $dir) {
            clearstatcache(true, $dir);
            $mode = @fileperms($dir);
            if (!is_int($mode) || ($mode & 0077) !== 0) {
                throw new RuntimeException('Recovery vault not private.');
            }
        }
        $all = [];
        foreach (new DirectoryIterator($this->vault) as $entry) {
            if ($entry->isDot()) continue;
            $name = $entry->getFilename();
            if (str_starts_with($name, '.db-update-') || str_starts_with($name, '.one-click-pending-')) {
                if ($entry->isLink() || !$entry->isFile()) {
                    throw new RuntimeException('Unsafe F4c.4 recovery marker.');
                }
                $all[] = $entry->getPathname();
            }
        }
        return $all;
    }

    private function readData(): array
    {
        $data = $this->readJson($this->data);
        if (array_keys($data) !== ['applied'] || !is_array($data['applied']) || !array_is_list($data['applied'])) {
            throw new RuntimeException('Corrupt synthetic database fixture.');
        }
        return $data;
    }

    private function validatePlan(array $plan): void
    {
        if (!array_is_list($plan) || count($plan) < 1 || count($plan) > 8) {
            throw new RuntimeException('Invalid synthetic migration plan.');
        }
        $seen = [];
        foreach ($plan as $item) {
            if (!is_array($item) || array_keys($item) !== ['id', 'sha256']
                || !is_string($item['id']) || preg_match(self::ID_RE, $item['id']) !== 1) {
                throw new RuntimeException('Invalid synthetic migration ID.');
            }
            self::assertDigest($item['sha256']);
            if (isset($seen[$item['id']])) throw new RuntimeException('Duplicate migration ID.');
            $seen[$item['id']] = true;
        }
    }

    private static function assertDigest(mixed $value): void
    {
        if (!is_string($value) || preg_match(self::SHA_RE, $value) !== 1) {
            throw new RuntimeException('Invalid pinned SHA-256 proof.');
        }
    }

    private function vaultReady(): void
    {
        $storage = $this->root . '/storage';
        foreach ([$storage, $this->vault] as $dir) {
            if (is_link($dir) || (file_exists($dir) && !is_dir($dir))) {
                throw new RuntimeException('Unsafe recovery directory.');
            }
            if (!is_dir($dir) && !@mkdir($dir, 0700)) {
                throw new RuntimeException('Recovery vault unavailable.');
            }
            clearstatcache(true, $dir);
            $mode = @fileperms($dir);
            if (!is_int($mode) || ($mode & 0077) !== 0) {
                throw new RuntimeException('Recovery vault not private.');
            }
        }
    }

    private function readJson(string $path): array
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Missing private journal or fixture data.');
        }
        clearstatcache(true, $path);
        $bytes = @filesize($path);
        if (!is_int($bytes) || $bytes < 2 || $bytes > 65536
            || (((int)@fileperms($path) & 0077) !== 0)) {
            throw new RuntimeException('Unsafe or oversized private fixture data.');
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || strlen($raw) !== $bytes) throw new RuntimeException('Incomplete fixture read.');
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException('Corrupt JSON fixture.');
        }
        if (!is_array($data)) throw new RuntimeException('Invalid fixture document.');
        return $data;
    }

    private function atomicWrite(string $path, array $value, bool $replace): void
    {
        if (is_link($path) || (file_exists($path) && !$replace)) {
            throw new RuntimeException('Cannot overwrite fixture file.');
        }
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > 65536) throw new RuntimeException('QA journal exceeds maximum size.');
        $tmp = dirname($path) . '/.f4c4-tmp-' . bin2hex(random_bytes(16));
        $stream = @fopen($tmp, 'xb');
        if (!is_resource($stream)) throw new RuntimeException('Unable to create temporary private file.');
        try {
            if (!@chmod($tmp, 0600) || fwrite($stream, $json) !== strlen($json)
                || !fflush($stream)) {
                throw new RuntimeException('Incomplete atomic QA journal write.');
            }
        } finally {
            fclose($stream);
        }
        try {
            if (is_link($path) || (!$replace && file_exists($path)) || !@rename($tmp, $path)) {
                throw new RuntimeException('Atomic private file replacement failed.');
            }
        } finally {
            if (file_exists($tmp)) @unlink($tmp);
        }
    }

    private function exclusive(callable $work): array
    {
        if (is_link($this->lock) || (file_exists($this->lock) && !is_file($this->lock))) {
            throw new RuntimeException('Unsafe F4c.4 lock.');
        }
        $h = @fopen($this->lock, 'c+b');
        if (!is_resource($h)) throw new RuntimeException('Cannot open QA lock.');
        try {
            @chmod($this->lock, 0600);
            if (!flock($h, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another browser migration request is active.');
            }
            return $work();
        } finally {
            @flock($h, LOCK_UN);
            fclose($h);
        }
    }
}
