<?php

declare(strict_types=1);

/** F4b.1 synthetic-only; no real MySQL, CMS, Git or HTTP writes. */
require_once dirname(__DIR__) . '/core/classes/UpdateRecoveryDataArchive.php';
require_once dirname(__DIR__) . '/core/classes/UpdateDatabaseIsolatedRestore.php';

final class F4b1FakeTarget implements UpdateF4b1RestoreTarget
{
    public string $schema = 'boilerplate_f4b1_0123456789abcdef';
    public array $objects = [];
    public array $sent = [];
    public array $rows = ['users' => 2];
    public array $triggers = [];
    public int $failAfter = -1;
    public function databaseName(): string { return $this->schema; }
    public function existingObjects(): array { return $this->objects; }
    public function execute(string $sql): void
    {
        $this->sent[] = $sql;
        if ($this->failAfter >= 0 && count($this->sent) === $this->failAfter) {
            throw new RuntimeException('Injected SQL interruption.');
        }
    }
    public function tableRowCounts(): array { return $this->rows; }
    public function triggerNames(): array { return $this->triggers; }
}

$pass = 0;
$fail = 0;
$assert = static function (bool $ok, string $name) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "PASS: $name\n"; }
    else { $fail++; echo "FAIL: $name\n"; }
};
$refuses = static function (callable $fn): bool {
    try { $fn(); return false; } catch (Throwable $ignored) { return true; }
};
$dir = sys_get_temp_dir() . '/bp-f4b1-qa-' . bin2hex(random_bytes(12));
if (!mkdir($dir, 0700)) throw new RuntimeException('Unable to create F4b1 fixture directory.');
$paths = [];

/** @param array<string,int> $tables */
$archive = static function (string $sql, array $tables = ['users' => 2], int $triggers = 0, ?array $extra = null) use (&$paths, $dir): string {
    $id = bin2hex(random_bytes(6));
    $base = $dir . '/' . $id;
    mkdir($base, 0700);
    $manifest = [
        'format' => 'boilerplate-site-data-backup', 'version' => 1,
        'contains_sensitive_data' => true, 'database_tables' => $tables,
        'database_triggers' => $triggers, 'database_rows' => array_sum($tables),
        'database_sha256' => hash('sha256', $sql),
        'upload_sha256' => [], 'upload_files' => 0,
    ];
    if ($extra) $manifest = array_replace($manifest, $extra);
    file_put_contents($base . '/database.sql', $sql);
    file_put_contents($base . '/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $tar = new PharData($base . '/data.tar');
    $tar->addFile($base . '/database.sql', 'database.sql');
    $tar->addFile($base . '/manifest.json', 'manifest.json');
    unset($tar);
    $tar = new PharData($base . '/data.tar');
    $tar->compress(Phar::GZ);
    unset($tar);
    $path = $base . '/data.tar.gz';
    $paths[] = $base;
    return $path;
};

$header = "-- Boilerplate CMS site data backup. RESTORE TO AN EMPTY DATABASE ONLY.\n"
    . "-- Contains accounts/password hashes and form submissions. Store securely.\n"
    . "SET NAMES utf8mb4;\nSET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
$table = "-- Table: users\nCREATE TABLE `users` (\n `id` int NOT NULL PRIMARY KEY,\n `note` varchar(40) DEFAULT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
$entries = "INSERT INTO `users` VALUES ('1', 'alpha; beta');\n"
    . "INSERT INTO `users` VALUES ('2', 'escaped \\' quote');\n";
$foot = "\n-- Restore database triggers (required for media integrity)\nDELIMITER $$\nDELIMITER ;\nSET FOREIGN_KEY_CHECKS = 1;\n";
$valid = $header . $table . $entries . $foot;
$engine = new UpdateDatabaseIsolatedRestore();

try {
    $good = $archive($valid);
    $report = $engine->inspect($good);
    $assert($report['status'] === 'review' && $report['install_ready'] === false
        && $report['tables'] === 1 && $report['rows'] === 2 && $report['triggers'] === 0,
        'Valid SiteBackup SQL passes review-only preflight');
    $mock = new F4b1FakeTarget();
    $result = $engine->rehearse($good, $mock);
    $assert($result['status'] === 'isolated_restore_verified' && !$result['install_ready']
        && count($mock->sent) === 7, 'Complete isolated import sequence executes in mock gateway');
    $assert($mock->sent[0] === 'SET NAMES utf8mb4' && end($mock->sent) === 'SET FOREIGN_KEY_CHECKS = 1',
        'Strict session setup and restored FK setting');
    $assert(str_contains(implode('\n', $mock->sent), "alpha; beta"),
        'Quoted semicolons in values are not interpreted as separators');
    $assert(str_contains(implode('\n', $mock->sent), "escaped \\' quote"),
        'Escaped single quote preserved without SQL reinterpretation');

    $notEmpty = new F4b1FakeTarget(); $notEmpty->objects = ['pages'];
    $assert($refuses(static fn() => $engine->rehearse($good, $notEmpty)) && $notEmpty->sent === [],
        'Non-empty target rejected before SQL');
    $active = new F4b1FakeTarget(); $active->schema = 'boilerplate';
    $assert($refuses(static fn() => $engine->rehearse($good, $active)) && $active->sent === [],
        'Active CMS database name refused');
    $near = new F4b1FakeTarget(); $near->schema = 'boilerplate_f4b1_0123456789abcdeg';
    $assert($refuses(static fn() => $engine->rehearse($good, $near)) && $near->sent === [],
        'Near-match schema name refused');
    $short = new F4b1FakeTarget(); $short->schema = 'boilerplate_f4b1_123';
    $assert($refuses(static fn() => $engine->rehearse($good, $short)) && $short->sent === [],
        'Short fake schema name refused');

    $errorMock = new F4b1FakeTarget(); $errorMock->failAfter = 5;
    $assert($refuses(static fn() => $engine->rehearse($good, $errorMock))
        && end($errorMock->sent) === 'SET FOREIGN_KEY_CHECKS = 1',
        'Injected SQL failure never claims success and restores FK session setting');
    $badRows = new F4b1FakeTarget(); $badRows->rows = ['users' => 1];
    $assert($refuses(static fn() => $engine->rehearse($good, $badRows)),
        'Post-import missing row rejected');
    $extra = new F4b1FakeTarget(); $extra->rows['other'] = 1;
    $assert($refuses(static fn() => $engine->rehearse($good, $extra)),
        'Post-import extra table rejected');
    $badTriggers = new F4b1FakeTarget(); $badTriggers->triggers = ['extra'];
    $assert($refuses(static fn() => $engine->rehearse($good, $badTriggers)),
        'Post-import unexpected trigger rejected');

    $invalidSql = [
        'bad header' => "-- Forged backup\n" . substr($valid, strlen(explode("\n", $valid)[0]) + 1),
        'extra DELETE' => str_replace('DELIMITER $$', 'DELETE FROM `users`;\nDELIMITER $$', $valid),
        'extra INSERT' => str_replace("DELIMITER $$\n", "INSERT INTO `users` VALUES ('3', 'extra');\nDELIMITER $$\n", $valid),
        'missing row' => str_replace("INSERT INTO `users` VALUES ('2', 'escaped \\' quote');\n", '', $valid),
        'duplicate CREATE' => str_replace($entries, $table . $entries, $valid),
        'unknown CREATE' => str_replace('CREATE TABLE `users`', 'CREATE TABLE `another`', $valid),
        'multi-statement' => str_replace("'alpha; beta');", "'alpha; beta'); DROP TABLE `users`;", $valid),
        'missing FK close' => str_replace('SET FOREIGN_KEY_CHECKS = 1;', '', $valid),
        'fake delimiter' => str_replace('DELIMITER $$', 'DELIMITER //', $valid),
        'unbalanced quote' => str_replace("'alpha; beta'", "'alpha; beta", $valid),
        'wrong table engine' => str_replace('ENGINE=InnoDB', 'ENGINE=MyISAM', $valid),
        'wrong SQL session' => str_replace('NO_AUTO_VALUE_ON_ZERO', 'ANSI', $valid),
        'missing DELIMITER reset' => str_replace('DELIMITER ;', '', $valid),
    ];
    foreach ($invalidSql as $name => $sql) {
        $p = $archive($sql);
        $assert($refuses(static fn() => $engine->inspect($p)), 'Unsafe SQL rejected: ' . $name);
    }
    $p = $archive($valid, ['users' => 3]);
    $assert($refuses(static fn() => $engine->inspect($p)), 'Inaccurate row inventory rejected');
    $p = $archive($valid, ['users' => 2], 1);
    $assert($refuses(static fn() => $engine->inspect($p)), 'Inaccurate trigger inventory rejected');
    $p = $archive($valid, ['users' => 2], 0, ['database_sha256' => str_repeat('0', 64)]);
    $assert($refuses(static fn() => $engine->inspect($p)), 'Modified SQL manifest digest rejected');
    $p = $archive($valid, ['users' => 2], 0, ['database_rows' => 3]);
    $assert($refuses(static fn() => $engine->inspect($p)), 'Incorrect overall database row count rejected');
    $p = $archive($valid, ['users' => 2], 0, ['upload_files' => 1]);
    $assert($refuses(static fn() => $engine->inspect($p)), 'Invalid upload manifest rejected by independent archive verifier');
    $p = $archive($valid, ['users' => 2], 0, ['contains_sensitive_data' => false]);
    $assert($refuses(static fn() => $engine->inspect($p)), 'Unrecognized SiteBackup manifest refused');

    $trigger = "CREATE TRIGGER `media_ref_blocks_insert` BEFORE INSERT ON `users` FOR EACH ROW BEGIN UPDATE `users` SET `id` = NEW.id; END$$\n";
    $withTrigger = str_replace('DELIMITER $$\n', 'DELIMITER $$\n' . $trigger, $valid);
    // The literal replace above is intentional to keep fixture clarity; use real LF.
    $withTrigger = str_replace("DELIMITER $$\n", "DELIMITER $$\n" . $trigger, $valid);
    $tp = $archive($withTrigger, ['users' => 2], 1);
    $tReport = $engine->inspect($tp);
    $assert($tReport['triggers'] === 1, 'Compound MySQL trigger with internal semicolon parsed');
    $tm = new F4b1FakeTarget(); $tm->triggers = ['media_ref_blocks_insert'];
    $assert($engine->rehearse($tp, $tm)['triggers'] === 1, 'Expected trigger inventory verified after restore');
    $double = str_replace("DELIMITER $$\n", "DELIMITER $$\n" . $trigger . $trigger, $valid);
    $assert($refuses(static fn() => $engine->inspect($archive($double, ['users' => 2], 2))),
        'Duplicate trigger refused');
    $danger = str_replace('UPDATE `users`', 'DROP TABLE `users`', $trigger);
    $assert($refuses(static fn() => $engine->inspect($archive(str_replace("DELIMITER $$\n", "DELIMITER $$\n" . $danger, $valid), ['users' => 2], 1))),
        'Destructive compound trigger body refused');
    $wrongTrigger = str_replace('`media_ref_blocks_insert`', '`noncms_trigger`', $trigger);
    $assert($refuses(static fn() => $engine->inspect($archive(str_replace("DELIMITER $$\n", "DELIMITER $$\n" . $wrongTrigger, $valid), ['users' => 2], 1))),
        'Unknown trigger namespace refused');

    $nine = '';
    for ($i = 1; $i <= 9; $i++) {
        $nine .= "CREATE TRIGGER `media_ref_test_$i` BEFORE INSERT ON `media_reference_state` FOR EACH ROW BEGIN SET @x = 1; END$$\n";
    }
    $tSql = str_replace($entries, $entries . "CREATE TABLE `media_reference_state` (`id` int NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n", $valid);
    $tSql = str_replace("DELIMITER $$\n", "DELIMITER $$\n" . $nine, $tSql);
    $assert($engine->inspect($archive($tSql, ['users' => 2, 'media_reference_state' => 0], 9))['triggers'] === 9,
        'Nine CMS media-reference triggers accepted for indexed database');
    $assert($refuses(static fn() => $engine->inspect($archive($tSql, ['users' => 2, 'media_reference_state' => 0], 8))),
        'Wrong media-reference trigger count rejected');

    $assert(class_exists(UpdateF4b1MysqliTarget::class)
        && interface_exists(UpdateF4b1RestoreTarget::class),
        'Native isolated target adapter exists without connecting during synthetic QA');
    $assert($refuses(static fn() => new UpdateF4b1MysqliTarget('remote.host', 'f4b1', '', 'boilerplate_f4b1_0123456789abcdef')),
        'Remote MySQL host refused without opening connection');
    $assert($refuses(static fn() => new UpdateF4b1MysqliTarget('127.0.0.1', 'root', '', 'boilerplate_f4b1_0123456789abcdef')),
        'Root MySQL account refused without connecting');
    $assert($refuses(static fn() => new UpdateF4b1MysqliTarget('127.0.0.1', 'f4b1', '', 'boilerplate')),
        'Unscoped MySQL schema refused without connecting');
} finally {
    foreach ($paths as $base) {
        foreach (['data.tar.gz','data.tar','database.sql','manifest.json'] as $file) {
            if (is_file($base . '/' . $file)) @unlink($base . '/' . $file);
        }
        @rmdir($base);
    }
    @rmdir($dir);
}
echo "F4b1 Isolated Database Restore QA1: $pass PASS / $fail FAIL (synthetic only; no MySQL, CMS, Git or HTTP writes)\n";
exit($fail === 0 ? 0 : 1);
