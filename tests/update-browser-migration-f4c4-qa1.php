<?php

declare(strict_types=1);

/** F4c.4: private, non-SQL fixture QA. No CMS config, DB or HTTP mutation. */
require_once dirname(__DIR__) . '/core/classes/UpdateBrowserMigrationRehearsal.php';
require_once dirname(__DIR__) . '/core/classes/UpdateDurableRecoveryGate.php';

$pass = 0;
$fail = 0;
$fixtures = [];
function check4(bool $ok, string $label): void {
    global $pass, $fail;
    if ($ok) { ++$pass; echo "PASS: $label\n"; }
    else { ++$fail; echo "FAIL: $label\n"; }
}
function rejects4(callable $fn): bool {
    try { $fn(); return false; } catch (Throwable $e) { return true; }
}
function fixture4(): string {
    global $fixtures;
    $path = '/tmp/bp-f4c4-browser-' . bin2hex(random_bytes(8));
    if (!mkdir($path, 0700) || !chmod($path, 0700)) throw new RuntimeException('Fixture create failed.');
    $fixtures[] = $path;
    return $path;
}
function clean4(string $root): void {
    if (preg_match('/^\/tmp\/bp-f4c4-browser-[a-f0-9]{16}$/D', $root) !== 1 || is_link($root)) return;
    if (!file_exists($root)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $entry) {
        $path = $entry->getPathname();
        if (!str_starts_with($path, $root . '/')) throw new RuntimeException('Unsafe QA cleanup path.');
        if ($entry->isDir() && !$entry->isLink()) rmdir($path);
        else unlink($path);
    }
    rmdir($root);
}
function proofs4(): array {
    return ['release_sha256' => str_repeat('a', 64), 'backup_sha256' => str_repeat('b', 64),
        'release_verified' => true, 'backup_verified' => true, 'quiescence_verified' => true];
}
function plan4(): array {
    return [
        ['id' => 'core:20261108_qa_prepare', 'sha256' => str_repeat('1',64)],
        ['id' => 'module:forms:20261108_qa_apply', 'sha256' => str_repeat('2',64)],
    ];
}

try {
    $bad = '/tmp/bp-f4c4-not-approved';
    check4(rejects4(fn() => new UpdateBrowserMigrationRehearsal($bad)), 'Arbitrary temp root refused');
    check4(rejects4(fn() => new UpdateBrowserMigrationRehearsal(dirname(__DIR__))), 'CMS source root refused');
    $loose = fixture4(); chmod($loose, 0755);
    check4(rejects4(fn() => new UpdateBrowserMigrationRehearsal($loose)), 'World-readable fixture refused');
    chmod($loose, 0700);
    $tmp = fixture4();
    $gate = new UpdateBrowserMigrationRehearsal($tmp);
    $initial = $gate->status();
    check4($initial['state'] === 'manual_recovery' && $initial['blocked'] === true, 'Missing journal fails closed');
    $badProof = proofs4(); $badProof['backup_verified'] = false;
    check4(rejects4(fn() => $gate->begin(plan4(), $badProof)), 'Unverified backup refuses begin');
    $badProof = proofs4(); $badProof['release_verified'] = false;
    check4(rejects4(fn() => $gate->begin(plan4(), $badProof)), 'Unverified release refuses begin');
    $badProof = proofs4(); $badProof['quiescence_verified'] = false;
    check4(rejects4(fn() => $gate->begin(plan4(), $badProof)), 'No quiescence proof refuses begin');
    $badProof = proofs4(); $badProof['backup_sha256'] = 'not-a-sha';
    check4(rejects4(fn() => $gate->begin(plan4(), $badProof)), 'Invalid backup hash refused');
    check4(rejects4(fn() => $gate->begin([], proofs4())), 'Empty plan refused');
    check4(rejects4(fn() => $gate->begin(array_fill(0, 9, plan4()[0]), proofs4())), 'Oversized plan refused');
    check4(rejects4(fn() => $gate->begin([plan4()[0], plan4()[0]], proofs4())), 'Duplicate migration ID refused');
    $p = plan4(); $p[0]['id'] = '../secret';
    check4(rejects4(fn() => $gate->begin($p, proofs4())), 'Unsafe migration ID refused');
    $p = plan4(); $p[0]['sha256'] = str_repeat('z', 64);
    check4(rejects4(fn() => $gate->begin($p, proofs4())), 'Invalid migration source hash refused');
    check4(!file_exists($tmp . '/synthetic-database.json'), 'No effects before valid begin');
    $start = $gate->begin(plan4(), proofs4());
    check4($start['state'] === 'prepared' && $start['total'] === 2, 'Verified fixture begins prepared');
    check4($start['blocked'] && !$start['install_ready'], 'Production migration stays disabled');
    check4((new UpdateDurableRecoveryGate($tmp))->status() === UpdateDurableRecoveryGate::DATABASE,
        'F4c.2 detects DB blocker created before synthetic writes');
    check4(count(glob($tmp . '/storage/update-recovery/.db-update-pending-*') ?: []) === 1,
        'Exactly one durable recovery marker exists');
    check4(rejects4(fn() => $gate->begin(plan4(), proofs4())), 'Cannot overwrite existing migration run');
    check4(rejects4(fn() => $gate->verify(str_repeat('0',64))), 'Cannot verify before migration');
    $one = $gate->step();
    check4($one['state'] === 'step_applied' && $one['completed'] === 1, 'One browser request performs one synthetic step');
    check4($one['can_retry'], 'Next step available only after durable checkpoint');
    $second = $gate->step();
    check4($second['completed'] === 2 && $second['total'] === 2, 'Second synthetic step follows deterministic order');
    check4(!$second['can_retry'], 'No extra step after final planned migration');
    check4(rejects4(fn() => $gate->step()), 'Third execution request refused');
    check4(rejects4(fn() => $gate->verify(str_repeat('f',64))), 'Wrong post-migration fingerprint refused');
    $history = hash('sha256', json_encode(plan4(), JSON_UNESCAPED_SLASHES));
    $done = $gate->verify($history);
    check4($done['state'] === 'verified_qa_only', 'Final independent fingerprint accepted in synthetic QA');
    check4(!$done['install_ready'] && $done['blocked'], 'Even verified run remains blocked');
    check4(rejects4(fn() => $gate->verify($history)), 'Repeated finalize refused');
    check4((new UpdateDurableRecoveryGate($tmp))->status() === UpdateDurableRecoveryGate::DATABASE,
        'F4c.2 blocker is never released automatically');
    $newProcess = new UpdateBrowserMigrationRehearsal($tmp);
    check4($newProcess->status()['state'] === 'verified_qa_only', 'Fresh PHP object sees durable final state');
    $a = fixture4(); $g = new UpdateBrowserMigrationRehearsal($a); $g->begin(plan4(),proofs4());
    check4(rejects4(fn() => $g->step(static function(string $checkpoint): void {
        if ($checkpoint === 'before-effect') throw new RuntimeException('Simulated PHP death before effect');
    })), 'Simulated interruption before synthetic DDL');
    $afterA = (new UpdateBrowserMigrationRehearsal($a))->status();
    check4($afterA['state'] === 'manual_recovery' && !$afterA['can_retry'], 'Before-DDL crash fails closed');
    check4(rejects4(fn() => $g->step()), 'Before-DDL crash cannot auto-retry migration');
    check4((new UpdateDurableRecoveryGate($a))->status() === UpdateDurableRecoveryGate::DATABASE,
        'Before-DDL crash preserves DB blocker');
    $b = fixture4(); $g = new UpdateBrowserMigrationRehearsal($b); $g->begin(plan4(),proofs4());
    check4(rejects4(fn() => $g->step(static function(string $checkpoint): void {
        if ($checkpoint === 'after-effect') throw new RuntimeException('Simulated PHP death after effect');
    })), 'Simulated interruption after synthetic DDL');
    $afterB = (new UpdateBrowserMigrationRehearsal($b))->status();
    check4($afterB['state'] === 'manual_recovery' && !$afterB['can_retry'], 'After-DDL crash fails closed');
    $synthetic = json_decode(file_get_contents($b . '/synthetic-database.json'),true);
    check4(count($synthetic['applied'] ?? []) === 1, 'Committed synthetic DDL effect persists after crash');
    check4(rejects4(fn() => $g->step()), 'After-DDL crash never applies duplicate DDL');
    $c = fixture4(); $g = new UpdateBrowserMigrationRehearsal($c); $g->begin(plan4(),proofs4());
    $markers = glob($c.'/storage/update-recovery/.db-update-pending-*');
    check4(count($markers) === 1, 'Corruption fixture marker located');
    file_put_contents($markers[0], '{broken');
    check4($g->status()['state'] === 'manual_recovery', 'Corrupt journal fails closed');
    check4(rejects4(fn() => $g->step()), 'Corrupt journal cannot execute step');
    $d = fixture4(); $g = new UpdateBrowserMigrationRehearsal($d); $g->begin(plan4(),proofs4());
    file_put_contents($d . '/synthetic-database.json', '{"applied":[{"id":"injected","sha256":"bad"}]}');
    check4(rejects4(fn() => $g->step()), 'Out-of-band synthetic database mutation blocks run');
    check4((new UpdateDurableRecoveryGate($d))->status() === UpdateDurableRecoveryGate::DATABASE,
        'Out-of-band mutation keeps persistent DB blocker');
    $e = fixture4(); $g = new UpdateBrowserMigrationRehearsal($e); $g->begin(plan4(),proofs4());
    chmod($e . '/storage/update-recovery', 0755);
    check4(rejects4(fn() => $g->step()), 'Public recovery vault refuses writes');
    check4($g->status()['state'] === 'manual_recovery', 'Public recovery vault fails closed for reads');
    chmod($e . '/storage/update-recovery', 0700);
    check4(!method_exists(UpdateBrowserMigrationRehearsal::class,'unlock'), 'No production recovery unlock method');
    check4(!method_exists(UpdateBrowserMigrationRehearsal::class,'applySql'), 'No SQL executor exposed');
    check4(!method_exists(UpdateBrowserMigrationRehearsal::class,'activateProduction'), 'No production activation method');
    $source = file_get_contents(dirname(__DIR__).'/core/classes/UpdateBrowserMigrationRehearsal.php');
    check4(!str_contains($source, 'new mysqli(') && !str_contains($source, 'DbConf::name()'),
        'No production database selection or connection');
    check4(!str_contains($source, '->apply()') && !str_contains($source, '->query('),
        'Never executes MigrationRunner or SQL');
} finally {
    foreach (array_reverse($fixtures) as $path) clean4($path);
}
echo "F4c.4 Browser Migration Rehearsal QA1: $pass PASS / $fail FAIL (private synthetic fixture only)\n";
if ($fail > 0) exit(1);
