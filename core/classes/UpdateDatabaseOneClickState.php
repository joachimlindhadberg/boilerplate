<?php

declare(strict_types=1);

/**
 * S5.2 QA3: pure database-aware update state contract.
 *
 * This class never reads or writes files, touches SQL, authorizes HTTP actions,
 * or enables database-aware updates. The production One-Click installer remains
 * file-only. Future executors MUST persist and fsync their own recovery journal
 * BEFORE acting on a "journal_durable" transition and must verify backups,
 * write quiescence and restore capability independently.
 */
final class UpdateDatabaseOneClickState
{
    public const INITIAL = 'initial';
    public const RELEASE_VERIFIED = 'release_verified';
    public const RESTORE_PROVEN = 'restore_proven';
    public const JOURNAL_DURABLE = 'journal_durable';
    public const FILES_WRITTEN = 'files_written';
    public const DATABASE_WRITTEN = 'database_written';
    public const POSTFLIGHT_VERIFIED = 'postflight_verified';
    public const COMMITTED = 'committed';
    public const ABORTED = 'aborted_before_changes';
    public const RECOVERY_REQUIRED = 'recovery_required';
    public const RESTORED = 'restored';

    private const FORWARD = [
        self::INITIAL => ['verify_release' => self::RELEASE_VERIFIED],
        self::RELEASE_VERIFIED => ['verify_restorable_backup' => self::RESTORE_PROVEN],
        self::RESTORE_PROVEN => ['durably_record_journal' => self::JOURNAL_DURABLE],
        self::JOURNAL_DURABLE => ['write_files' => self::FILES_WRITTEN],
        self::FILES_WRITTEN => ['run_migration' => self::DATABASE_WRITTEN],
        self::DATABASE_WRITTEN => ['verify_postflight' => self::POSTFLIGHT_VERIFIED],
        self::POSTFLIGHT_VERIFIED => ['commit_journal' => self::COMMITTED],
    ];

    private string $phase;

    public function __construct(string $phase = self::INITIAL)
    {
        $valid = [
            self::INITIAL, self::RELEASE_VERIFIED, self::RESTORE_PROVEN,
            self::JOURNAL_DURABLE, self::FILES_WRITTEN, self::DATABASE_WRITTEN,
            self::POSTFLIGHT_VERIFIED, self::COMMITTED, self::ABORTED,
            self::RECOVERY_REQUIRED, self::RESTORED,
        ];
        if (!in_array($phase, $valid, true)) {
            throw new InvalidArgumentException('Unknown database update phase.');
        }
        $this->phase = $phase;
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function requiresRecovery(): bool
    {
        return $this->phase === self::RECOVERY_REQUIRED;
    }

    /**
     * Pure transition validation. The caller must provide external, verified
     * evidence; these boolean proofs are not themselves a security authority.
     *
     * @param array<string,mixed> $proof
     */
    public function advance(string $event, array $proof = []): string
    {
        if ($event === 'fault_or_restart') {
            if (in_array($this->phase, [self::INITIAL, self::RELEASE_VERIFIED, self::RESTORE_PROVEN], true)) {
                return $this->phase = self::ABORTED;
            }
            if (in_array($this->phase, [self::JOURNAL_DURABLE, self::FILES_WRITTEN,
                self::DATABASE_WRITTEN, self::POSTFLIGHT_VERIFIED], true)) {
                return $this->phase = self::RECOVERY_REQUIRED;
            }
            throw new LogicException('Terminal update state cannot be silently restarted.');
        }

        if ($event === 'verify_code_and_database_restored') {
            if ($this->phase !== self::RECOVERY_REQUIRED) {
                throw new LogicException('Restore approval requires a recovery state.');
            }
            self::requireProofs($proof, ['code_restored', 'database_restored', 'source_checksums_restored']);
            return $this->phase = self::RESTORED;
        }

        $next = self::FORWARD[$this->phase][$event] ?? null;
        if (!is_string($next)) {
            throw new LogicException('Illegal database-aware update transition.');
        }
        $required = match ($event) {
            'verify_release' => ['official_release_verified', 'migration_history_unchanged'],
            'verify_restorable_backup' => ['exclusive_writer_lock', 'backup_integrity_verified',
                'isolated_restore_proven', 'write_quiescence_verified'],
            'durably_record_journal' => ['pending_db_journal_fsynced'],
            'write_files' => ['file_plan_verified', 'journal_still_present'],
            'run_migration' => ['migration_exact_source_verified', 'journal_still_present'],
            'verify_postflight' => ['code_version_verified', 'database_history_verified',
                'original_data_verified'],
            'commit_journal' => ['code_version_verified', 'database_history_verified',
                'journal_committed_durably'],
            default => throw new LogicException('Invalid transition contract.'),
        };
        self::requireProofs($proof, $required);
        return $this->phase = $next;
    }

    /** @param array<string,mixed> $proof @param list<string> $keys */
    private static function requireProofs(array $proof, array $keys): void
    {
        foreach ($keys as $key) {
            if (($proof[$key] ?? null) !== true) {
                throw new LogicException('Required update proof absent: ' . $key);
            }
        }
    }
}
