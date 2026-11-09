<?php

declare(strict_types=1);

/**
 * F4c.10 QA-ONLY recovery completion state machine.
 *
 * NEVER include from web bootstrap or live Tools. Does not execute SQL, restore
 * backups, migrate databases or authenticate customers. The caller MUST supply
 * a fresh, independent read of an isolated MySQL inventory via $observe.
 * Only an owner-only direct /tmp/bp-f4c7-site-<16 hex> copy with a private
 * .f4c10-qa-private sentinel is accepted. A real production instance is NOT
 * an accepted target. Readmission is rehearsed exclusively in that copy.
 */
final class UpdateRecoveryCompletionF4c10Qa
{
    private const ROOT = '/\Abp-f4c7-site-[a-f0-9]{16}\z/D';
    private const HEX32 = '/\A[a-f0-9]{32}\z/D';
    private const HEX64 = '/\A[a-f0-9]{64}\z/D';
    private const FORMAT = 'boilerplate-f4c10-completion-qa1';

    private string $root;
    private string $vault;
    private string $marker;
    private string $completed;
    private string $id;
    /** @var Closure():string */
    private Closure $observe;

    public function __construct(string $root, string $id, callable $independentSqlInventory)
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('F4c.10 completion is CLI QA only.');
        $real = realpath($root);
        if (!is_string($real) || is_link($root) || dirname($real) !== realpath('/tmp')
            || preg_match(self::ROOT, basename($real)) !== 1
            || !self::privateDir($real) || !self::privateFile($real . '/.f4c10-qa-private')) {
            throw new RuntimeException('F4c.10 requires an explicit private test site.');
        }
        self::hex($id, self::HEX32);
        $this->root = $real;
        $this->id = $id;
        $this->vault = $real . '/storage/update-recovery';
        if (!self::privateDir($real . '/storage') || !self::privateDir($this->vault)) {
            throw new RuntimeException('Private recovery vault not ready.');
        }
        $this->marker = $this->vault . '/.db-update-pending-' . $id;
        $this->completed = $this->vault . '/.f4c10-completed-' . $id;
        $this->observe = Closure::fromCallable($independentSqlInventory);
    }

    public function begin(int $owner, string $token, string $baseline, string $envSha, string $codeSha): void
    {
        if ($owner < 1 || strlen($token) !== 64 || !ctype_xdigit($token)) {
            throw new RuntimeException('Invalid private QA owner or approval token.');
        }
        foreach ([$baseline, $envSha, $codeSha] as $digest) self::hex($digest, self::HEX64);
        $this->exclusive(function () use ($owner, $token, $baseline, $envSha, $codeSha): void {
            if ((new UpdateDurableRecoveryGate($this->root))->status() !== UpdateDurableRecoveryGate::CLEAR
                || file_exists($this->marker) || is_link($this->marker)
                || file_exists($this->completed) || is_link($this->completed)) {
                throw new RuntimeException('Existing recovery evidence must not be replaced.');
            }
            $this->assertSnapshot($baseline, $envSha, $codeSha);
            $journal = [
                'format' => self::FORMAT, 'id' => $this->id, 'phase' => 'pending',
                'owner_id' => $owner, 'token_sha256' => hash('sha256', $token),
                'baseline_sha256' => $baseline, 'env_sha256' => $envSha,
                'code_sha256' => $codeSha, 'proof_sha256' => null,
                'blocked' => true, 'install_ready' => false,
                'production_recovery' => false,
            ];
            $this->writeNew($this->marker, $journal);
            $this->requireBlocked();
        });
    }

    /** Write durable approval while retaining the active blocking marker. */
    public function verify(int $owner, string $token): void
    {
        $this->exclusive(function () use ($owner, $token): void {
            $j = $this->journal('pending');
            $this->authority($j, $owner, $token);
            $this->requireBlocked();
            $this->assertSnapshot($j['baseline_sha256'], $j['env_sha256'], $j['code_sha256']);
            $j['phase'] = 'verified_pending';
            $j['proof_sha256'] = self::proof($j);
            $this->replaceAtomically($this->marker, $j);
            $this->requireBlocked();
        });
    }

    /**
     * Last isolated recheck followed by one atomic rename of verified evidence.
     * Before rename: gate DATABASE. After rename: gate CLEAR. A process crash
     * cannot leave a missing journal: the archived file is the same inode.
     */
    public function complete(int $owner, string $token): void
    {
        $this->exclusive(function () use ($owner, $token): void {
            $j = $this->journal('verified_pending');
            $this->authority($j, $owner, $token);
            $this->requireBlocked();
            if (!is_string($j['proof_sha256'])
                || !hash_equals(self::proof($j), $j['proof_sha256'])) {
                throw new RuntimeException('Completion proof mismatch.');
            }
            $this->assertSnapshot($j['baseline_sha256'], $j['env_sha256'], $j['code_sha256']);
            $this->assertOnlyThisPending();
            if (file_exists($this->completed) || is_link($this->completed)) {
                throw new RuntimeException('Completion archive already exists.');
            }
            if (!rename($this->marker, $this->completed)) {
                throw new RuntimeException('Atomic QA completion rename failed.');
            }
            if ((new UpdateDurableRecoveryGate($this->root))->status() !== UpdateDurableRecoveryGate::CLEAR) {
                throw new RuntimeException('Another recovery condition still blocks the site.');
            }
        });
    }

    /** Inspect; never modifies the private journal. */
    public function state(): string
    {
        if (self::privateFile($this->completed)) return 'archived';
        if (self::privateFile($this->marker)) return $this->journal(null)['phase'];
        return 'unknown';
    }

    private function authority(array $j, int $owner, string $token): void
    {
        if ($owner < 1 || $j['owner_id'] !== $owner || strlen($token) !== 64
            || !ctype_xdigit($token)
            || !hash_equals($j['token_sha256'], hash('sha256', $token))) {
            throw new RuntimeException('No matching private QA approval authority.');
        }
    }

    private function assertSnapshot(string $baseline, string $envSha, string $codeSha): void
    {
        $observed = ($this->observe)();
        if (!is_string($observed) || preg_match(self::HEX64, $observed) !== 1
            || !hash_equals($baseline, $observed)
            || !self::privateFile($this->root . '/.env')
            || !hash_equals($envSha, (string)hash_file('sha256', $this->root . '/.env'))
            || is_link($this->root . '/core/classes/FirstInstall.php')
            || !is_file($this->root . '/core/classes/FirstInstall.php')
            || !hash_equals($codeSha, (string)hash_file('sha256', $this->root . '/core/classes/FirstInstall.php'))) {
            throw new RuntimeException('Independent SQL/config/code recovery proof mismatch.');
        }
    }

    private function journal(?string $required): array
    {
        if (!self::privateFile($this->marker)) throw new RuntimeException('Missing secure pending journal.');
        $raw = file_get_contents($this->marker);
        if (!is_string($raw) || strlen($raw) > 4096) throw new RuntimeException('Invalid journal content.');
        $j = json_decode($raw, true, 12, JSON_THROW_ON_ERROR);
        $keys = ['format','id','phase','owner_id','token_sha256','baseline_sha256','env_sha256',
            'code_sha256','proof_sha256','blocked','install_ready','production_recovery'];
        if (!is_array($j) || array_keys($j) !== $keys || $j['format'] !== self::FORMAT
            || $j['id'] !== $this->id
            || !in_array($j['phase'], ['pending','verified_pending'], true)
            || ($required !== null && $j['phase'] !== $required)
            || !is_int($j['owner_id']) || $j['owner_id'] < 1
            || $j['blocked'] !== true || $j['install_ready'] !== false
            || $j['production_recovery'] !== false) {
            throw new RuntimeException('Unexpected recovery journal state.');
        }
        foreach (['token_sha256','baseline_sha256','env_sha256','code_sha256'] as $name) {
            self::hex($j[$name], self::HEX64);
        }
        if ($j['phase'] === 'pending' && $j['proof_sha256'] !== null) {
            throw new RuntimeException('Unverified journal contains a proof.');
        }
        if ($j['phase'] === 'verified_pending') {
            self::hex($j['proof_sha256'], self::HEX64);
        }
        return $j;
    }

    private function requireBlocked(): void
    {
        if ((new UpdateDurableRecoveryGate($this->root))->status() !== UpdateDurableRecoveryGate::DATABASE) {
            throw new RuntimeException('Expected persistent database recovery block.');
        }
    }

    private function assertOnlyThisPending(): void
    {
        foreach (new DirectoryIterator($this->vault) as $item) {
            if ($item->isDot()) continue;
            $name = $item->getFilename();
            if (str_starts_with($name, '.db-update-') || str_starts_with($name, '.one-click-pending-') || str_starts_with($name, '.f4c10-stage-')) {
                if ($item->getPathname() !== $this->marker) {
                    throw new RuntimeException('Conflicting recovery journal prevents readmission.');
                }
            }
        }
    }

    private static function proof(array $j): string
    {
        return hash('sha256', implode('|', [self::FORMAT, $j['id'], (string)$j['owner_id'],
            $j['token_sha256'], $j['baseline_sha256'], $j['env_sha256'], $j['code_sha256']]));
    }

    private function writeNew(string $target, array $data): void
    {
        if (file_exists($target) || is_link($target)) throw new RuntimeException('Journal already exists.');
        $this->writeBytes($target, $data);
    }

    private function replaceAtomically(string $target, array $data): void
    {
        if (!self::privateFile($target)) throw new RuntimeException('Unsafe target journal.');
        $tmp = $this->vault . '/.f4c10-stage-' . bin2hex(random_bytes(12));
        try {
            $this->writeBytes($tmp, $data);
            if (!rename($tmp, $target)) throw new RuntimeException('Journal replace failed.');
        } finally {
            if (is_file($tmp) && !is_link($tmp)) @unlink($tmp);
        }
    }

    private function writeBytes(string $path, array $data): void
    {
        $bytes = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $fd = @fopen($path, 'x');
        if ($fd === false) throw new RuntimeException('Could not create private journal.');
        try {
            if (!chmod($path, 0600) || fwrite($fd, $bytes) !== strlen($bytes)
                || !fflush($fd)) throw new RuntimeException('Private journal write failed.');
            if (function_exists('fsync') && !fsync($fd)) {
                throw new RuntimeException('Private journal sync failed.');
            }
        } finally {
            fclose($fd);
        }
    }

    private function exclusive(callable $action): void
    {
        $gate = new UpdateMaintenanceGate($this->root);
        if (!$gate->acquireExclusive()) throw new RuntimeException('Private maintenance lease busy.');
        try { $action(); }
        finally { $gate->release(); }
    }

    private static function privateDir(string $path): bool
    {
        if (is_link($path) || !is_dir($path)) return false;
        $mode = @fileperms($path);
        return is_int($mode) && ($mode & 0077) === 0;
    }

    private static function privateFile(string $path): bool
    {
        if (is_link($path) || !is_file($path)) return false;
        $mode = @fileperms($path);
        $size = @filesize($path);
        return is_int($mode) && ($mode & 0077) === 0 && is_int($size) && $size >= 0 && $size <= 4096;
    }

    private static function hex(mixed $value, string $re): void
    {
        if (!is_string($value) || preg_match($re, $value) !== 1) {
            throw new RuntimeException('Invalid private QA digest or identifier.');
        }
    }
}
