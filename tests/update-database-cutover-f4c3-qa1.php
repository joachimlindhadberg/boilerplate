<?php

declare(strict_types=1);

/** F4c.3 CLI-only synthetic-state test. Never connects to the CMS database. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once dirname(__DIR__) . '/core/classes/UpdateDatabaseCutoverRehearsal.php';
require_once dirname(__DIR__) . '/core/classes/UpdateDurableRecoveryGate.php';

$passes = 0;
function qa(bool $condition, string $label): void {
    global $passes;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $passes++;
    echo 'PASS: ' . $label . "\n";
}
function rejected(callable $fn): bool {
    try { $fn(); } catch (Throwable $e) { return true; }
    return false;
}
function fixture(): string {
    $root = '/tmp/bp-f4c3-cutover-' . bin2hex(random_bytes(8));
    if (!mkdir($root, 0700)) throw new RuntimeException('Private fixture unavailable');
    return $root;
}
function removeFixture(string $root): void {
    $canonical = realpath($root);
    if (!is_string($canonical) || dirname($canonical) !== realpath('/tmp')
        || !preg_match('/^bp-f4c3-cutover-[a-f0-9]{16}$/D', basename($canonical))) {
        throw new RuntimeException('Refusing unsafe cleanup.');
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($canonical, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isLink() || $item->isFile()) unlink($item->getPathname());
        elseif ($item->isDir()) rmdir($item->getPathname());
    }
    rmdir($canonical);
}
function childSnapshot(string $root): array {
    $path = dirname(__DIR__) . '/core/classes/UpdateDatabaseCutoverRehearsal.php';
    $code = 'require $argv[2]; echo json_encode((new UpdateDatabaseCutoverRehearsal($argv[1]))->inspectAfterRestart(),JSON_THROW_ON_ERROR);';
    $proc = proc_open([PHP_BINARY, '-r', $code, $root, $path],
        [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    if (!is_resource($proc)) throw new RuntimeException('Cannot start independent PHP reader');
    fclose($pipes[0]); $raw = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($proc) !== 0) throw new RuntimeException('New PHP process rejected recovery journal: '.$err);
    return json_decode((string)$raw,true,10,JSON_THROW_ON_ERROR);
}

$root = fixture();
$other = fixture();
$bad = fixture();
$done = false;
try {
    $before = 'boilerplate_f4b1_' . bin2hex(random_bytes(8));
    $candidate = 'boilerplate_f4b1_' . bin2hex(random_bytes(8));
    $fingerprint = hash('sha256', 'verified canonical 26-table, 9-trigger synthetic CMS inventory');
    $rehearsal = new UpdateDatabaseCutoverRehearsal($root);
    qa(rejected(fn () => new UpdateDatabaseCutoverRehearsal(dirname(__DIR__))), 'CMS source root never accepted as QA cutover target');
    qa(rejected(fn () => new UpdateDatabaseCutoverRehearsal('/tmp')), 'Generic system temp folder refused');
    qa(rejected(fn () => $rehearsal->initialize('boilerplate', $candidate, $fingerprint)), 'Production-like database name rejected');
    qa(rejected(fn () => $rehearsal->initialize($before, 'cms_live', $fingerprint)), 'Unapproved destination database rejected');
    qa(rejected(fn () => $rehearsal->initialize($before, $before, $fingerprint)), 'Identical source and destination refused');
    qa(rejected(fn () => $rehearsal->initialize($before, $candidate, 'not-a-hash')), 'Invalid inventory fingerprint refused');
    qa(!is_dir($root.'/storage'), 'Invalid input caused no fixture state writes');

    $first = $rehearsal->initialize($before, $candidate, $fingerprint);
    qa($first['phase'] === 'prepared', 'Durable journal created in prepared phase');
    qa($first['active_schema'] === $before, 'Initial isolated pointer selects original database');
    qa($first['blocked'] && !$first['install_ready'] && !$first['production_cutover'], 'Production install stays disabled');
    qa(is_dir($root.'/storage/update-recovery'), 'Private F4c.2 recovery vault exists');
    qa((new UpdateDurableRecoveryGate($root))->status() === UpdateDurableRecoveryGate::DATABASE,
        'Existing F4c.2 gate detects persistent F4c.3 database marker');
    qa(count(glob($root.'/storage/update-recovery/.db-update-pending-*')) === 1,
        'Exactly one durable database recovery marker');
    qa((fileperms($root.'/active-target.json') & 0077) === 0,
        'Synthetic pointer protected by private permissions');
    qa(childSnapshot($root)['active_schema'] === $before,
        'Separate PHP process reads original pointer and recovery journal');
    qa(rejected(fn () => $rehearsal->initialize($before, $candidate, $fingerprint)),
        'Existing fixture cannot be reset or overwritten');
    qa(rejected(fn () => $rehearsal->switchCandidate()), 'Cannot switch before restoring candidate');
    qa(rejected(fn () => $rehearsal->confirmRestoredCandidate(str_repeat('a', 64))),
        'Candidate fingerprint mismatch blocks cutover');
    qa($rehearsal->inspectAfterRestart()['phase'] === 'prepared',
        'Fingerprint mismatch cannot advance journal');

    $verified = $rehearsal->confirmRestoredCandidate($fingerprint);
    qa($verified['phase'] === 'candidate_verified', 'Independent candidate proof advances phase');
    qa($verified['active_schema'] === $before, 'Proof alone does not switch database');
    qa(rejected(fn () => $rehearsal->confirmRestoredCandidate($fingerprint)),
        'Repeat candidate verification is refused');
    qa(rejected(fn () => $rehearsal->confirmCandidateIsSelected($candidate, $fingerprint)),
        'Cannot approve a target that has not yet been selected');

    $armed = $rehearsal->armSwitch();
    qa($armed['phase'] === 'switch_armed' && $armed['active_schema'] === $before,
        'Durable switch intent written before pointer change');
    qa($armed['switch_ambiguous'], 'Armed switch stays explicitly ambiguous after crash');
    qa(childSnapshot($root)['active_schema'] === $before, 'Restart before pointer write preserves original selection');
    qa(rejected(fn () => $rehearsal->armSwitch()), 'Cannot arm the same cutover twice');
    qa(rejected(fn () => $rehearsal->confirmCandidateIsSelected($before, $fingerprint)),
        'Old schema cannot impersonate independently verified candidate');

    $middle = $rehearsal->switchPointerOnlyForCrashQa();
    qa($middle['phase'] === 'switch_armed' && $middle['active_schema'] === $candidate,
        'Crash checkpoint: pointer changed while intent remains durable');
    qa($middle['blocked'] && !$middle['install_ready'], 'Crash checkpoint remains fail closed');
    qa(childSnapshot($root)['active_schema'] === $candidate,
        'Separate PHP process reconstructs pointer after interrupted switch');
    qa(childSnapshot($root)['switch_ambiguous'], 'Restart recognizes uncommitted pointer switch');
    qa((new UpdateDurableRecoveryGate($root))->status() === UpdateDurableRecoveryGate::DATABASE,
        'F4c.2 still blocks site after pointer write');
    qa(rejected(fn () => $rehearsal->switchCandidate()),
        'A repeated cutover cannot silently overwrite candidate pointer');
    qa(rejected(fn () => $rehearsal->confirmCandidateIsSelected($candidate, str_repeat('f',64))),
        'Wrong post-cutover database fingerprint rejected');
    $finished = $rehearsal->confirmCandidateIsSelected($candidate, $fingerprint);
    qa($finished['phase'] === 'cutover_verified_qa_only',
        'Independent selected-candidate proof finalizes QA record');
    qa($finished['blocked'] && !$finished['install_ready'] && !$finished['production_cutover'],
        'Even verified cutover does not authorize production activation');
    qa((new UpdateDurableRecoveryGate($root))->status() === UpdateDurableRecoveryGate::DATABASE,
        'Verified fixture cannot silently clear F4c.2 recovery marker');
    qa(childSnapshot($root)['phase'] === 'cutover_verified_qa_only',
        'Completed QA journal survives a new PHP process');
    qa(rejected(fn () => $rehearsal->confirmCandidateIsSelected($candidate, $fingerprint)),
        'Repeated finalization cannot clear recovery lock');

    $second = new UpdateDatabaseCutoverRehearsal($other);
    $second->initialize('boilerplate_f4b1_1234567890abcdef', 'boilerplate_f4b1_fedcba0987654321', $fingerprint);
    $second->confirmRestoredCandidate($fingerprint);
    $second->armSwitch();
    $second->switchCandidate();
    $normal = $second->inspectAfterRestart();
    qa($normal['phase'] === 'switched_quarantined' && $normal['active_schema'] === 'boilerplate_f4b1_fedcba0987654321',
        'Normal atomic candidate switch selects verified target');
    qa($normal['blocked'], 'Normal candidate switch remains quarantine only');
    qa($second->confirmCandidateIsSelected('boilerplate_f4b1_fedcba0987654321', $fingerprint)['phase'] === 'cutover_verified_qa_only',
        'Normal path completes independent QA verification');

    $corrupt = new UpdateDatabaseCutoverRehearsal($bad);
    $corrupt->initialize('boilerplate_f4b1_abcdef1234567890', 'boilerplate_f4b1_9876543210abcdef', $fingerprint);
    file_put_contents($bad.'/active-target.json', "not JSON\n");
    qa(rejected(fn () => $corrupt->inspectAfterRestart()), 'Corrupt pointer fails closed');
    qa((new UpdateDurableRecoveryGate($bad))->status() === UpdateDurableRecoveryGate::DATABASE,
        'Corrupt pointer does not remove durable recovery marker');
    qa(rejected(fn () => $corrupt->armSwitch()), 'Corrupt pointer never permits cutover');
    qa(is_file($bad.'/active-target.json'), 'Corrupt QA evidence preserved for inspection');

    qa(!str_contains(file_get_contents(dirname(__DIR__).'/core/classes/UpdateDatabaseCutoverRehearsal.php'), 'DbConf::'),
        'Cutover rehearsal never loads production database selection');
    qa(!method_exists(UpdateDatabaseCutoverRehearsal::class, 'activateProduction'),
        'No production cutover method is exposed');
    qa(!method_exists(UpdateDatabaseCutoverRehearsal::class, 'clearRecoveryMarker'),
        'No recovery unlock method is exposed');
    qa(!method_exists(UpdateDatabaseCutoverRehearsal::class, 'dropSchema'),
        'No schema deletion method exists');
    $done = true;
} finally {
    if ($done) {
        removeFixture($root); removeFixture($other); removeFixture($bad);
    } else {
        fwrite(STDERR, 'NOTE: Preserved isolated QA evidence under '.$root."\n");
    }
}
qa(!is_dir($root) && !is_dir($other) && !is_dir($bad), 'Only private synthetic fixture directories removed');
echo "F4c.3 cutover rehearsal: {$passes} PASS / 0 FAIL (CLI QA only; no live CMS or SQL)\n";
