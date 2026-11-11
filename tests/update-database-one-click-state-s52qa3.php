<?php

declare(strict_types=1);

// S5.2 QA3. Run only in CLI, with the verified repo path or the QA support tree.
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    fwrite(STDERR, "STOP: CLI fixture root and source root required.\n");
    exit(2);
}
$source = $argv[1];
$fixture = $argv[2];
if (!is_dir($source) || !is_dir($fixture) || realpath($source) === realpath($fixture)) {
    fwrite(STDERR, "STOP: Invalid test roots.\n");
    exit(2);
}
require_once $source . '/core/classes/UpdateDatabaseOneClickState.php';
require_once $source . '/core/classes/UpdateDurableRecoveryGate.php';

function qa(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . "\n";
}
function rejects(callable $fn): bool {
    try { $fn(); return false; } catch (LogicException|InvalidArgumentException $e) { return true; }
}
function proofs(string $event): array {
    return match ($event) {
        'verify_release' => ['official_release_verified' => true, 'migration_history_unchanged' => true],
        'verify_restorable_backup' => ['exclusive_writer_lock' => true,
            'backup_integrity_verified' => true, 'isolated_restore_proven' => true,
            'write_quiescence_verified' => true],
        'durably_record_journal' => ['pending_db_journal_fsynced' => true],
        'write_files' => ['file_plan_verified' => true, 'journal_still_present' => true],
        'run_migration' => ['migration_exact_source_verified' => true, 'journal_still_present' => true],
        'verify_postflight' => ['code_version_verified' => true, 'database_history_verified' => true,
            'original_data_verified' => true],
        'commit_journal' => ['code_version_verified' => true, 'database_history_verified' => true,
            'journal_committed_durably' => true],
        default => throw new LogicException('Unknown fixture event.'),
    };
}

try {
    $events = ['verify_release', 'verify_restorable_backup', 'durably_record_journal',
        'write_files', 'run_migration', 'verify_postflight', 'commit_journal'];
    $states = [UpdateDatabaseOneClickState::RELEASE_VERIFIED,
        UpdateDatabaseOneClickState::RESTORE_PROVEN,
        UpdateDatabaseOneClickState::JOURNAL_DURABLE,
        UpdateDatabaseOneClickState::FILES_WRITTEN,
        UpdateDatabaseOneClickState::DATABASE_WRITTEN,
        UpdateDatabaseOneClickState::POSTFLIGHT_VERIFIED,
        UpdateDatabaseOneClickState::COMMITTED];
    $machine = new UpdateDatabaseOneClickState();
    foreach ($events as $i => $event) {
        qa(rejects(static function () use ($machine, $event): void {
            $machine->advance($event, []);
        }), 'Missing proof rejected before ' . $event);
        qa($machine->phase() === ($i === 0 ? UpdateDatabaseOneClickState::INITIAL : $states[$i-1]),
            'No transition on missing proof: ' . $event);
        qa($machine->advance($event, proofs($event)) === $states[$i], 'Verified transition: ' . $event);
    }
    qa($machine->phase() === UpdateDatabaseOneClickState::COMMITTED, 'All verified steps required for commit');
    qa(rejects(static fn() => $machine->advance('fault_or_restart')), 'Committed state cannot re-enter recovery');
    qa(rejects(static fn() => $machine->advance('write_files')), 'Committed state cannot write more files');

    foreach (range(0, count($events)-1) as $faultIndex) {
        $s = new UpdateDatabaseOneClickState();
        for ($i = 0; $i < $faultIndex; $i++) {
            $s->advance($events[$i], proofs($events[$i]));
        }
        $phase = $s->advance('fault_or_restart');
        $want = $faultIndex <= 2 ? UpdateDatabaseOneClickState::ABORTED
            : UpdateDatabaseOneClickState::RECOVERY_REQUIRED;
        qa($phase === $want, 'Fault injection at boundary ' . $faultIndex . ' fails closed');
        qa(rejects(static fn() => $s->advance('commit_journal', proofs('commit_journal'))),
            'Fault cannot be reclassified as successful install ' . $faultIndex);
        if ($want === UpdateDatabaseOneClickState::RECOVERY_REQUIRED) {
            qa(rejects(static fn() => $s->advance('verify_code_and_database_restored',
                ['code_restored'=>true, 'database_restored'=>false, 'source_checksums_restored'=>true])),
                'Partial restore is not accepted ' . $faultIndex);
            qa($s->advance('verify_code_and_database_restored',
                ['code_restored'=>true, 'database_restored'=>true, 'source_checksums_restored'=>true])
                === UpdateDatabaseOneClickState::RESTORED, 'Both restorations required ' . $faultIndex);
        }
    }
    qa(rejects(static fn() => new UpdateDatabaseOneClickState('unknown')),
        'Unknown persistent journal phase rejected');
    qa(rejects(static fn() => (new UpdateDatabaseOneClickState())->advance('run_migration',
        proofs('run_migration'))), 'Database migration cannot start before backup and durable journal');

    // Real, unchanged production HTTP recovery admission service tested in a
    // temporary synthetic fixture, never against the actual CMS storage.
    $public = $fixture . '/public_html/admin';
    $vault = $fixture . '/storage/update-recovery';
    if (!mkdir($public, 0700, true) || !mkdir($vault, 0700, true)) {
        throw new RuntimeException('Cannot create disposable fixture.');
    }
    $script = $public . '/tools.php';
    if (file_put_contents($script, "<?php // synthetic\n", LOCK_EX) === false) {
        throw new RuntimeException('Cannot create disposable test script.');
    }
    $gate = new UpdateDurableRecoveryGate($fixture);
    qa($gate->status() === UpdateDurableRecoveryGate::CLEAR, 'Fixture starts with clear recovery admission');
    $pending = $vault . '/.db-update-pending-' . str_repeat('a', 32);
    if (file_put_contents($pending, "fixture\n", LOCK_EX) === false) {
        throw new RuntimeException('Cannot write disposable DB recovery marker.');
    }
    qa($gate->status() === UpdateDurableRecoveryGate::DATABASE,
        'Recovery gate recognizes pending DB update (fixture)');
    qa(!$gate->permitsHttp($script, '/admin/tools/update', 'POST', ['mode'=>'update'],
        ['action'=>'restore_interrupted_update']),
        'File-only restore POST blocked during pending DB update');
    qa(!$gate->permitsHttp($script, '/admin/tools/update', 'POST', ['mode'=>'update'],
        ['action'=>'install_official_update']),
        'New update POST blocked during pending DB update');
    $blocked = $vault . '/.db-update-blocked-' . str_repeat('a', 32);
    if (!rename($pending, $blocked)) throw new RuntimeException('Cannot simulate blocked recovery state.');
    qa($gate->status() === UpdateDurableRecoveryGate::DATABASE,
        'Blocked DB recovery marker retains HTTP admission barrier');
    qa(!$gate->permitsHttp($script, '/admin/tools/update', 'POST', ['mode'=>'update'],
        ['action'=>'restore_interrupted_update']), 'Blocked DB journal cannot use file-only recovery');
    if (!unlink($blocked)) throw new RuntimeException('Cannot clean disposable marker.');
    qa($gate->status() === UpdateDurableRecoveryGate::CLEAR,
        'Disposable fixture recovery admission clears after marker removal');
    echo "RESULT: S5.2 QA3 STATE AND RECOVERY ADMISSION CONTRACT PASS\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'STOP: QA3 fixture failed: ' . $e->getMessage() . "\n");
    exit(1);
}
