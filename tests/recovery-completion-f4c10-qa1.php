<?php

declare(strict_types=1);

/** F4c.10: CLI-only private recovery completion fault / re-admission QA. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$repo = dirname(__DIR__);
require_once $repo . '/core/classes/UpdateMaintenanceGate.php';
require_once $repo . '/core/classes/UpdateDurableRecoveryGate.php';
require_once $repo . '/tests/recovery-completion-engine-f4c10-qa1.php';

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "PASS: {$label}\n"; }
    else { $fail++; echo "FAIL: {$label}\n"; }
};
$reject = static function (callable $fn) use ($check): void {
    $caught = false;
    try { $fn(); } catch (Throwable $e) { $caught = true; }
    $check($caught, 'Unsafe or out-of-order operation refused');
};
$tmp = realpath('/tmp');
if ($tmp === false) throw new RuntimeException('Missing private temp directory.');
$roots = [];
$create = static function () use ($tmp, &$roots): array {
    $root = $tmp . '/bp-f4c7-site-' . bin2hex(random_bytes(8));
    if (!mkdir($root, 0700) || !mkdir($root . '/storage', 0700)
        || !mkdir($root . '/storage/update-recovery', 0700)
        || !mkdir($root . '/core', 0700)
        || !mkdir($root . '/core/classes', 0700)) {
        throw new RuntimeException('Could not establish isolated private root.');
    }
    $roots[] = $root;
    foreach (['.f4c10-qa-private' => 'F4c10 fixture', '.env' => 'APP_ENV=test',
        'core/classes/FirstInstall.php' => '<?php // private test source'] as $name => $content) {
        $file = $root . '/' . $name;
        $fd = fopen($file, 'x');
        if (!is_resource($fd)) throw new RuntimeException('Private file creation failed.');
        chmod($file, 0600);
        fwrite($fd, $content);
        fclose($fd);
    }
    $baseline = hash('sha256', 'pristine-synthetic-DB-' . $root);
    return [$root, $baseline, hash_file('sha256', $root . '/.env'),
        hash_file('sha256', $root . '/core/classes/FirstInstall.php')];
};
$clean = static function (string $path) use (&$clean): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (new DirectoryIterator($path) as $item) {
        if (!$item->isDot()) $clean($item->getPathname());
    }
    rmdir($path);
};
$success = false;
try {
    [$root, $baseline, $env, $code] = $create();
    $observation = $baseline;
    $id = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(32));
    $owner = 17;
    $gate = new UpdateDurableRecoveryGate($root);
    // The observer must read the mutable snapshot on every call.
    $qa = new UpdateRecoveryCompletionF4c10Qa($root, $id,
        static function () use (&$observation): string { return $observation; });
    $check($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Clean private CMS starts unblocked');
    $reject(static fn () => $qa->verify($owner, $token));
    $reject(static fn () => $qa->complete($owner, $token));
    $reject(static fn () => new UpdateRecoveryCompletionF4c10Qa('/tmp', $id,
        static fn (): string => $baseline));
    $reject(static fn () => new UpdateRecoveryCompletionF4c10Qa($root, '../wrong',
        static fn (): string => $baseline));
    $qa->begin($owner, $token, $baseline, $env, $code);
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Pending marker blocks real gate');
    $check($qa->state() === 'pending', 'Durable journal begins pending');
    $reject(static fn () => $qa->begin($owner, $token, $baseline, $env, $code));
    $reject(static fn () => $qa->verify($owner + 1, $token));
    $reject(static fn () => $qa->verify($owner, str_repeat('0', 64)));
    $observation = hash('sha256', 'database-drift');
    $reject(static fn () => $qa->verify($owner, $token));
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'SQL drift leaves recovery blocked');
    $observation = $baseline;
    $file = $root . '/.env';
    file_put_contents($file, 'APP_ENV=altered');
    $reject(static fn () => $qa->verify($owner, $token));
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Config drift leaves recovery blocked');
    file_put_contents($file, 'APP_ENV=test');
    $qa->verify($owner, $token);
    $check($qa->state() === 'verified_pending', 'Verified phase is durable');
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Verification alone NEVER unlocks');
    $reject(static fn () => $qa->verify($owner, $token));
    $qa = new UpdateRecoveryCompletionF4c10Qa($root, $id,
        static function () use (&$observation): string { return $observation; });
    $check($qa->state() === 'verified_pending', 'New PHP instance sees durable verified phase');
    $observation = hash('sha256', 'mutated-after-proof');
    $reject(static fn () => $qa->complete($owner, $token));
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Late SQL drift blocks completion');
    $observation = $baseline;
    $reject(static fn () => $qa->complete($owner + 1, $token));
    $reject(static fn () => $qa->complete($owner, str_repeat('f', 64)));
    $extra = $root . '/storage/update-recovery/.db-update-blocked-' . bin2hex(random_bytes(16));
    file_put_contents($extra, 'qa-conflict'); chmod($extra, 0600);
    $reject(static fn () => $qa->complete($owner, $token));
    $check($gate->status() === UpdateDurableRecoveryGate::DATABASE, 'Multiple recovery markers remain blocked');
    unlink($extra);
    $qa->complete($owner, $token);
    $check($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Atomic archive permits PRIVATE re-admission');
    $check($qa->state() === 'archived', 'Verified evidence archived without deletion');
    $archive = $root . '/storage/update-recovery/.f4c10-completed-' . $id;
    $check(is_file($archive) && (fileperms($archive) & 0077) === 0, 'Owner-only completion evidence retained');
    $reject(static fn () => $qa->complete($owner, $token));
    $check($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Replay cannot recreate a recovery operation');

    [$root2, $baseline2, $env2, $code2] = $create();
    $id2 = bin2hex(random_bytes(16));
    $token2 = bin2hex(random_bytes(32));
    $qa2 = new UpdateRecoveryCompletionF4c10Qa($root2, $id2,
        static fn (): string => $baseline2);
    $qa2->begin($owner, $token2, $baseline2, $env2, $code2);
    $qa2->verify($owner, $token2);
    $check((new UpdateDurableRecoveryGate($root2))->status() === UpdateDurableRecoveryGate::DATABASE,
        'Simulated crash before rename leaves CMS blocked');
    unset($qa2);
    $qa2 = new UpdateRecoveryCompletionF4c10Qa($root2, $id2,
        static fn (): string => $baseline2);
    $qa2->complete($owner, $token2);
    $check((new UpdateDurableRecoveryGate($root2))->status() === UpdateDurableRecoveryGate::CLEAR,
        'Restarted completion atomically admits verified private site');
    $check($qa2->state() === 'archived', 'Crash after rename retains durable evidence');
    $success = true;
} finally {
    if ($success) {
        foreach ($roots as $root) $clean($root);
    } else {
        fwrite(STDERR, 'NOTE: Isolated F4c.10 QA evidence retained: ' . implode(', ', $roots) . "\n");
    }
}
echo "F4c.10 Completion Contracts: {$pass} PASS / {$fail} FAIL (private QA; no production unlock)\n";
if (!$success || $fail) exit(1);
