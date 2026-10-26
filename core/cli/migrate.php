<?php
declare(strict_types=1);
/** Run explicitly: php core/cli/migrate.php --status | --apply */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$mode = $argv[1] ?? '--status';
if (!in_array($mode, ['--status', '--apply'], true) || count($argv) !== 2) {
    fwrite(STDERR, "Usage: php core/cli/migrate.php --status|--apply\n");
    exit(2);
}

define('ROOTPATH', dirname(__DIR__, 2));
require_once ROOTPATH . '/core/classes/Autoload.php';

try {
    $runner = new MigrationRunner();
    if ($mode === '--status') {
        $status = $runner->status();
        foreach ($status as $row) {
            echo str_pad($row['state'], 19) . ' ' . $row['id'] . "\n";
        }
        if (!$status) echo "No migrations found.\n";
        if (array_filter($status, static fn(array $row): bool => in_array($row['state'], ['missing-file', 'checksum-mismatch'], true))) {
            exit(1);
        }
    } else {
        $done = $runner->apply();
        foreach ($done as $id) echo 'Applied: ' . $id . "\n";
        echo $done ? 'Migration complete.' . "\n" : 'Already up to date.' . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Migration error: " . $e->getMessage() . "\n");
    exit(1);
}
