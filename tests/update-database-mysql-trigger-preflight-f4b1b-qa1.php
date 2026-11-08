<?php

declare(strict_types=1);

/** F4b.1b — synthetic-only; never connects to MySQL or reads DbConf. */
require_once dirname(__DIR__) . '/core/classes/UpdateRecoveryDataArchive.php';
require_once dirname(__DIR__) . '/core/classes/UpdateDatabaseIsolatedRestore.php';

final class F4b1bMockTarget implements UpdateF4b1RestoreTarget, UpdateF4b1TriggerPreflightTarget
{
    public string $schema = 'boilerplate_f4b1_0123456789abcdef';
    public array $existing = [];
    public array $sent = [];
    public array $counts = ['qa_f4b1b' => 1];
    public array $triggers = ['media_ref_qa_insert'];
    public bool $blocked = false;
    public bool $preflightCalled = false;
    public bool $throwSql = false;
    public int $observedTriggerCount = -1;

    public function databaseName(): string { return $this->schema; }
    public function existingObjects(): array { return $this->existing; }
    public function assertTriggerCreationCompatible(int $triggerCount): void
    {
        $this->preflightCalled = true;
        $this->observedTriggerCount = $triggerCount;
        if ($this->blocked) {
            UpdateF4b1TriggerCompatibility::assertCompatible(true, false, $triggerCount);
        } else {
            UpdateF4b1TriggerCompatibility::assertCompatible(true, true, $triggerCount);
        }
    }
    public function execute(string $sql): void
    {
        $this->sent[] = $sql;
        if ($this->throwSql) {
            throw new RuntimeException('synthetic SQL error', 1419);
        }
    }
    public function tableRowCounts(): array { return $this->counts; }
    public function triggerNames(): array { return $this->triggers; }
}

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $name) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "PASS: $name\n"; }
    else { $fail++; echo "FAIL: $name\n"; }
};
$rejects = static function (callable $action): bool {
    try { $action(); return false; } catch (RuntimeException $ignored) { return true; }
};

$settings = [
    [0, 0, true], [0, 1, true], [1, 1, true], [1, 0, false],
    ['OFF', 'OFF', true], ['ON', 'ON', true], ['ON', 'OFF', false],
    ['FALSE', 'TRUE', true], ['TRUE', 'FALSE', false],
];
foreach ($settings as [$logging, $trusted, $expected]) {
    $allowed = !$rejects(static fn() => UpdateF4b1TriggerCompatibility::assertCompatible($logging, $trusted, 1));
    $check($allowed === $expected, 'Trigger compatibility matches bounded logging/trust combination');
}
foreach ([['unknown','OFF'], ['ON','unknown'], [2,0], [1,2], ['',false]] as [$log, $trust]) {
    $check($rejects(static fn() => UpdateF4b1TriggerCompatibility::assertCompatible($log, $trust, 1)),
        'Unrecognized trigger setting fails closed');
}
$check(!$rejects(static fn() => UpdateF4b1TriggerCompatibility::assertCompatible('unknown', 'unknown', 0)),
    'Triggerless backup does not require trigger settings');
$check($rejects(static fn() => UpdateF4b1TriggerCompatibility::assertCompatible(false, false, -1)),
    'Invalid negative trigger inventory is blocked');
$check(class_exists(UpdateF4b1MysqliTarget::class)
    && in_array(UpdateF4b1TriggerPreflightTarget::class, class_implements(UpdateF4b1MysqliTarget::class), true),
    'Native gateway implements trigger compatibility contract');

$work = sys_get_temp_dir() . '/bp-f4b1b-qa-' . bin2hex(random_bytes(12));
if (!mkdir($work, 0700)) throw new RuntimeException('Cannot initialize private QA fixture.');
try {
    $sql = "-- Boilerplate CMS site data backup. RESTORE TO AN EMPTY DATABASE ONLY.\n"
        . "-- Contains accounts/password hashes and form submissions. Store securely.\n"
        . "SET NAMES utf8mb4;\nSET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS = 0;\n\n"
        . "-- Table: qa_f4b1b\nCREATE TABLE `qa_f4b1b` (\n `id` int NOT NULL PRIMARY KEY,\n `name` varchar(30) NOT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n"
        . "INSERT INTO `qa_f4b1b` VALUES (1, 'safe');\n\n"
        . "-- Restore database triggers (required for media integrity)\nDELIMITER $$\n"
        . "CREATE TRIGGER `media_ref_qa_insert` BEFORE INSERT ON `qa_f4b1b` FOR EACH ROW BEGIN SET NEW.name = TRIM(NEW.name); END$$\n"
        . "DELIMITER ;\nSET FOREIGN_KEY_CHECKS = 1;\n";
    $manifest = [
        'format' => 'boilerplate-site-data-backup', 'version' => 1,
        'contains_sensitive_data' => true, 'database_tables' => ['qa_f4b1b' => 1],
        'database_triggers' => 1, 'database_rows' => 1,
        'database_sha256' => hash('sha256', $sql), 'upload_sha256' => [], 'upload_files' => 0,
    ];
    file_put_contents($work . '/database.sql', $sql);
    file_put_contents($work . '/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $tar = new PharData($work . '/data.tar');
    $tar->addFile($work . '/database.sql', 'database.sql');
    $tar->addFile($work . '/manifest.json', 'manifest.json');
    unset($tar);
    $tar = new PharData($work . '/data.tar');
    $tar->compress(Phar::GZ);
    unset($tar);
    $archive = $work . '/data.tar.gz';
    $engine = new UpdateDatabaseIsolatedRestore();
    $summary = $engine->inspect($archive);
    $check($summary['triggers'] === 1 && $summary['rows'] === 1 && $summary['install_ready'] === false,
        'Trigger-bearing backup parses without SQL execution');

    $blocked = new F4b1bMockTarget(); $blocked->blocked = true;
    $check($rejects(static fn() => $engine->rehearse($archive, $blocked))
        && $blocked->preflightCalled && $blocked->observedTriggerCount === 1
        && $blocked->sent === [], 'Incompatible MySQL settings stop before first SQL statement');

    $allowed = new F4b1bMockTarget();
    $result = $engine->rehearse($archive, $allowed);
    $check($result['status'] === 'isolated_restore_verified' && $allowed->preflightCalled
        && $allowed->observedTriggerCount === 1 && count($allowed->sent) === 7,
        'Compatible settings allow mock import and preserve review-only status');
    $check($result['install_ready'] === false, 'Never grants production install readiness');

    $nonEmpty = new F4b1bMockTarget(); $nonEmpty->existing = ['not_empty'];
    $check($rejects(static fn() => $engine->rehearse($archive, $nonEmpty))
        && !$nonEmpty->preflightCalled && $nonEmpty->sent === [],
        'Nonempty schema is refused before preflight and SQL');

    $badSchema = new F4b1bMockTarget(); $badSchema->schema = 'boilerplate';
    $check($rejects(static fn() => $engine->rehearse($archive, $badSchema))
        && !$badSchema->preflightCalled && $badSchema->sent === [],
        'Active schema name refused before preflight and SQL');

    $failed = new F4b1bMockTarget(); $failed->throwSql = true;
    $previous = null;
    try { $engine->rehearse($archive, $failed); }
    catch (RuntimeException $e) { $previous = $e->getPrevious(); }
    $check($previous instanceof RuntimeException && $previous->getCode() === 1419,
        'Underlying SQL failure code retained for safe CLI-only diagnostics');
    $check($failed->sent === ['SET NAMES utf8mb4', 'SET FOREIGN_KEY_CHECKS = 1'],
        'SQL failure attempts session FK reset and reports no restore success');
} finally {
    foreach (['data.tar.gz', 'data.tar', 'manifest.json', 'database.sql'] as $name) {
        @unlink($work . '/' . $name);
    }
    @rmdir($work);
}

echo "F4b1b MySQL Trigger Compatibility QA1: $pass PASS / $fail FAIL (synthetic; no MySQL or CMS writes)\n";
exit($fail ? 1 : 0);
