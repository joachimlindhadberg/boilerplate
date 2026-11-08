<?php

declare(strict_types=1);

/**
 * F4c.3: QA-ONLY persistent database target cutover rehearsal.
 *
 * This component ONLY writes inside an owner-only direct /tmp fixture named
 * bp-f4c3-cutover-<16 hex>. It is CLI-only and uses names that F4b.1 accepts
 * for synthetic databases. It DOES NOT load DbConf/.env, connect to any SQL
 * server, modify the installed site's database selection, or clear F4c.2's
 * durable recovery gate. An independently tested caller must verify a native
 * restore and pass its exact inventory fingerprint; this class cannot prove it.
 *
 * There is deliberately NO production activation API. In particular, do not
 * wire active-target.json into DbConf, PHP-FPM, Apache, or One-Click Update.
 */
final class UpdateDatabaseCutoverRehearsal
{
    private const ROOT_RE = '/\Abp-f4c3-cutover-[a-f0-9]{16}\z/D';
    private const SCHEMA_RE = '/\Aboilerplate_f4b1_[a-f0-9]{16}\z/D';
    private const DIGEST_RE = '/\A[a-f0-9]{64}\z/D';
    private const JOURNAL_RE = '/\A\.db-update-pending-[a-f0-9]{32}\z/D';
    private const PHASES = ['prepared', 'candidate_verified', 'switch_armed',
        'switched_quarantined', 'cutover_verified_qa_only'];
    private string $root;
    private string $vault;
    private string $pointer;
    private string $lockPath;

    public function __construct(string $fixtureRoot)
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('F4c.3 is CLI-only.');
        }
        $tempParent = realpath('/tmp');
        $root = realpath($fixtureRoot);
        if (!is_string($tempParent) || !is_string($root)
            || is_link($fixtureRoot)
            || dirname($root) !== $tempParent
            || preg_match(self::ROOT_RE, basename($root)) !== 1
            || !is_dir($root) || (fileperms($root) & 0077) !== 0) {
            throw new RuntimeException('F4c.3 requires a dedicated owner-only /tmp QA fixture.');
        }
        $this->root = $root;
        $this->vault = $root . '/storage/update-recovery';
        $this->pointer = $root . '/active-target.json';
        $this->lockPath = $root . '/cutover.lock';
    }

    /**
     * Persists F4c.2's database marker BEFORE the first pointer write.
     * $inventorySha must originate from a separate, independent SQL inventory.
     */
    public function initialize(string $oldSchema, string $candidateSchema, string $inventorySha): array
    {
        self::assertSchema($oldSchema);
        self::assertSchema($candidateSchema);
        self::assertDigest($inventorySha);
        if ($oldSchema === $candidateSchema) {
            throw new RuntimeException('Cutover requires distinct isolated databases.');
        }
        return $this->underLock(function () use ($oldSchema, $candidateSchema, $inventorySha): array {
            $this->prepareVault();
            if (is_file($this->pointer) || is_link($this->pointer) || $this->journalPaths() !== []) {
                throw new RuntimeException('Refusing to overwrite an existing cutover fixture.');
            }
            $id = bin2hex(random_bytes(16));
            $journal = [
                'format' => 'boilerplate-f4c3-cutover-qa1',
                'id' => $id,
                'phase' => 'prepared',
                'before' => $oldSchema,
                'candidate' => $candidateSchema,
                'inventory_sha256' => $inventorySha,
                'blocked' => true,
                'install_ready' => false,
                'production_cutover' => false,
            ];
            $path = $this->vault . '/.db-update-pending-' . $id;
            $this->atomicJson($path, $journal); // fail closed BEFORE pointer creation
            $this->writePointer($oldSchema);
            return $this->snapshotUnlocked();
        });
    }

    public function confirmRestoredCandidate(string $actualInventorySha): array
    {
        self::assertDigest($actualInventorySha);
        return $this->underLock(function () use ($actualInventorySha): array {
            [$path, $j] = $this->oneJournal();
            $this->requirePhase($j, 'prepared');
            $this->requirePointer($j['before']);
            if (!hash_equals($j['inventory_sha256'], $actualInventorySha)) {
                throw new RuntimeException('Recovery inventory fingerprint mismatch.');
            }
            $j['phase'] = 'candidate_verified';
            $this->atomicJson($path, $j);
            return $this->snapshotUnlocked();
        });
    }

    /** Durable switch intent must precede changing the pointer. */
    public function armSwitch(): array
    {
        return $this->underLock(function (): array {
            [$path, $j] = $this->oneJournal();
            $this->requirePhase($j, 'candidate_verified');
            $this->requirePointer($j['before']);
            $j['phase'] = 'switch_armed';
            $this->atomicJson($path, $j);
            return $this->snapshotUnlocked();
        });
    }

    /** Normal synthetic pointer replacement. Always leaves recovery blocked. */
    public function switchCandidate(): array
    {
        return $this->underLock(function (): array {
            [$path, $j] = $this->oneJournal();
            $this->requirePhase($j, 'switch_armed');
            $this->requirePointer($j['before']);
            $this->writePointer($j['candidate']);
            $j['phase'] = 'switched_quarantined';
            $this->atomicJson($path, $j);
            return $this->snapshotUnlocked();
        });
    }

    /** QA fault injection: mimic SIGKILL between pointer rename and journal commit. */
    public function switchPointerOnlyForCrashQa(): array
    {
        return $this->underLock(function (): array {
            [, $j] = $this->oneJournal();
            $this->requirePhase($j, 'switch_armed');
            $this->requirePointer($j['before']);
            $this->writePointer($j['candidate']);
            return $this->snapshotUnlocked();
        });
    }

    /** An independent caller must re-query the *selected* MySQL database. */
    public function confirmCandidateIsSelected(string $observedSchema, string $observedInventorySha): array
    {
        self::assertSchema($observedSchema);
        self::assertDigest($observedInventorySha);
        return $this->underLock(function () use ($observedSchema, $observedInventorySha): array {
            [$path, $j] = $this->oneJournal();
            if (!in_array($j['phase'], ['switch_armed', 'switched_quarantined'], true)) {
                throw new RuntimeException('Fixture is not awaiting cutover verification.');
            }
            $this->requirePointer($j['candidate']);
            if ($observedSchema !== $j['candidate']
                || !hash_equals($j['inventory_sha256'], $observedInventorySha)) {
                throw new RuntimeException('Active database identity or inventory mismatch.');
            }
            $j['phase'] = 'cutover_verified_qa_only';
            $this->atomicJson($path, $j);
            return $this->snapshotUnlocked();
        });
    }

    /**
     * Restart reconstruction. The selected pointer may be old or new, but
     * BOTH are BLOCKED. Never infer production readiness from either outcome.
     */
    public function inspectAfterRestart(): array
    {
        return $this->underLock(fn (): array => $this->snapshotUnlocked());
    }

    private function snapshotUnlocked(): array
    {
        [, $j] = $this->oneJournal();
        $active = $this->readPointer();
        if ($active === null && $j['phase'] !== 'prepared') {
            throw new RuntimeException('Missing cutover pointer; manual intervention required.');
        }
        if ($active !== null && !in_array($active, [$j['before'], $j['candidate']], true)) {
            throw new RuntimeException('Unrecognized database target; manual intervention required.');
        }
        if ($active === $j['candidate'] && in_array($j['phase'], ['prepared', 'candidate_verified'], true)) {
            throw new RuntimeException('Pointer changed before durable switch intent.');
        }
        if ($active === $j['before'] && in_array($j['phase'], ['switched_quarantined', 'cutover_verified_qa_only'], true)) {
            throw new RuntimeException('Pointer unexpectedly reverted after switch.');
        }
        return [
            'phase' => $j['phase'],
            'active_schema' => $active,
            'before' => $j['before'],
            'candidate' => $j['candidate'],
            'blocked' => true,
            'install_ready' => false,
            'production_cutover' => false,
            'recovery_required' => true,
            'switch_ambiguous' => $j['phase'] === 'switch_armed',
        ];
    }

    private function requirePointer(string $schema): void
    {
        if ($this->readPointer() !== $schema) {
            throw new RuntimeException('Active target changed; refusing cutover.');
        }
    }

    private function readPointer(): ?string
    {
        if (!file_exists($this->pointer) && !is_link($this->pointer)) return null;
        $p = $this->readJson($this->pointer, 1024);
        $schema = $p['schema'] ?? null;
        if (($p['format'] ?? null) !== 'boilerplate-f4c3-synthetic-pointer-v1'
            || !is_string($schema)) {
            throw new RuntimeException('Corrupt cutover pointer.');
        }
        self::assertSchema($schema);
        return $schema;
    }

    private function writePointer(string $schema): void
    {
        self::assertSchema($schema);
        $this->atomicJson($this->pointer, [
            'format' => 'boilerplate-f4c3-synthetic-pointer-v1', 'schema' => $schema,
        ]);
    }

    /** @return array{0:string,1:array} */
    private function oneJournal(): array
    {
        $paths = $this->journalPaths();
        if (count($paths) !== 1) throw new RuntimeException('One valid cutover journal required.');
        $path = $paths[0];
        $j = $this->readJson($path, 4096);
        if (($j['format'] ?? null) !== 'boilerplate-f4c3-cutover-qa1'
            || !is_string($j['id'] ?? null)
            || $path !== $this->vault . '/.db-update-pending-' . $j['id']
            || !is_string($j['before'] ?? null)
            || !is_string($j['candidate'] ?? null)
            || !is_string($j['inventory_sha256'] ?? null)
            || !in_array($j['phase'] ?? null, self::PHASES, true)
            || ($j['blocked'] ?? null) !== true
            || ($j['install_ready'] ?? null) !== false
            || ($j['production_cutover'] ?? null) !== false) {
            throw new RuntimeException('Invalid F4c.3 recovery journal.');
        }
        self::assertSchema($j['before']);
        self::assertSchema($j['candidate']);
        self::assertDigest($j['inventory_sha256']);
        if ($j['before'] === $j['candidate']) {
            throw new RuntimeException('Journal targets are identical.');
        }
        return [$path, $j];
    }

    /** @return list<string> */
    private function journalPaths(): array
    {
        if (!is_dir($this->vault) || is_link($this->vault)
            || (fileperms($this->vault) & 0077) !== 0) {
            throw new RuntimeException('Unsafe F4c.3 private journal vault.');
        }
        $paths = [];
        foreach (new DirectoryIterator($this->vault) as $entry) {
            if ($entry->isDot()) continue;
            $name = $entry->getFilename();
            if (str_starts_with($name, '.db-update-')) {
                if (preg_match(self::JOURNAL_RE, $name) !== 1
                    || $entry->isLink() || !$entry->isFile()) {
                    throw new RuntimeException('Invalid or conflicting F4c.3 journal.');
                }
                $paths[] = $entry->getPathname();
            }
        }
        return $paths;
    }

    private function prepareVault(): void
    {
        $storage = $this->root . '/storage';
        foreach ([$storage, $this->vault] as $path) {
            if (is_link($path) || (file_exists($path) && !is_dir($path))) {
                throw new RuntimeException('Unsafe fixture storage location.');
            }
            if (!is_dir($path) && !mkdir($path, 0700)) {
                throw new RuntimeException('Cannot create private fixture storage.');
            }
            if ((fileperms($path) & 0077) !== 0) {
                throw new RuntimeException('Fixture storage must be owner-only.');
            }
        }
    }

    private function underLock(callable $fn): array
    {
        if (is_link($this->lockPath) || (file_exists($this->lockPath) && !is_file($this->lockPath))) {
            throw new RuntimeException('Unsafe fixture cutover lock.');
        }
        $h = fopen($this->lockPath, 'c+b');
        if ($h === false) throw new RuntimeException('Unable to open fixture lock.');
        try {
            chmod($this->lockPath, 0600);
            $file = fstat($h);
            $disk = stat($this->lockPath);
            if (!is_array($file) || !is_array($disk)
                || $file['ino'] !== $disk['ino'] || $file['dev'] !== $disk['dev']
                || (fileperms($this->lockPath) & 0077) !== 0
                || !flock($h, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Fixture cutover lock unavailable.');
            }
            return $fn();
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }
    }

    private function readJson(string $path, int $limit): array
    {
        if (is_link($path) || !is_file($path) || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('Unsafe fixture state file.');
        }
        $n = filesize($path);
        if (!is_int($n) || $n < 2 || $n > $limit) {
            throw new RuntimeException('Invalid fixture state size.');
        }
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) !== $n) {
            throw new RuntimeException('Fixture state changed during read.');
        }
        $json = json_decode($bytes, true, 10, JSON_THROW_ON_ERROR);
        if (!is_array($json) || array_is_list($json)) {
            throw new RuntimeException('Invalid fixture state structure.');
        }
        return $json;
    }

    /** Atomic JSON replacement, fsyncing file content. Directory fsync is not portable in PHP. */
    private function atomicJson(string $dest, array $body): void
    {
        $dir = dirname($dest);
        if (!is_dir($dir) || is_link($dir) || (fileperms($dir) & 0077) !== 0
            || is_link($dest) || (file_exists($dest) && !is_file($dest))) {
            throw new RuntimeException('Unsafe fixture write location.');
        }
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $tmp = tempnam($dir, '.f4c3-');
        if (!is_string($tmp) || is_link($tmp)) {
            throw new RuntimeException('Cannot allocate atomic state file.');
        }
        try {
            chmod($tmp, 0600);
            $h = fopen($tmp, 'wb');
            if ($h === false) throw new RuntimeException('Cannot open state file.');
            try {
                if (fwrite($h, $json) !== strlen($json) || !fflush($h)
                    || !fsync($h)) {
                    throw new RuntimeException('Cannot sync cutover state.');
                }
            } finally {
                fclose($h);
            }
            if (!rename($tmp, $dest)) {
                throw new RuntimeException('Atomic cutover rename failed.');
            }
        } finally {
            if (is_file($tmp)) unlink($tmp);
        }
    }

    private function requirePhase(array $journal, string $phase): void
    {
        if ($journal['phase'] !== $phase) {
            throw new RuntimeException('Cutover phase is not eligible.');
        }
    }

    private static function assertSchema(string $schema): void
    {
        if (preg_match(self::SCHEMA_RE, $schema) !== 1) {
            throw new RuntimeException('Production or non-test schema was refused.');
        }
    }

    private static function assertDigest(string $digest): void
    {
        if (preg_match(self::DIGEST_RE, $digest) !== 1) {
            throw new RuntimeException('Invalid inventory SHA-256.');
        }
    }
}
