<?php

declare(strict_types=1);
/**
 * php core/cli/media-index.php --status  # read only
 * php core/cli/media-index.php --audit   # read only, scans source data
 * php core/cli/media-index.php --rebuild # writes the index, never source data
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$mode = $argv[1] ?? '--status';
if (!in_array($mode, ['--status', '--audit', '--rebuild'], true) || count($argv) > 2) {
    fwrite(STDERR, "Usage: php core/cli/media-index.php --status|--audit|--rebuild\n");
    exit(2);
}
if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "This PHP CLI binary needs mysqli. Use your MAMP PHP binary.\n");
    exit(2);
}
define('ROOTPATH', dirname(__DIR__, 2));
require_once ROOTPATH . '/core/classes/Autoload.php';

// F3d.2b: hold the same F1c shared admission lock as web requests/cron for
// the entire CLI job, including database/file cleanup. External writers are
// still outside this cooperative boundary.
try {
    $updateWriterAdmission = UpdateCliWriterAdmission::acquire(ROOTPATH);
} catch (Throwable $ignored) {
    fwrite(STDERR, "CMS update maintenance gate busy or unavailable; retry later.\n");
    exit(75);
}

try {
    $index = new MediaReferenceIndex(new Db());
    $result = match ($mode) {
        '--status' => $index->status(),
        '--audit' => $index->audit(),
        '--rebuild' => $index->rebuild(),
    };
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    if ($mode === '--audit' && ($result['invalid_block_ids'] !== [] || $result['missing_media_ids'] !== [] || $result['missing_media_files'] !== [])) {
        exit(1); // Data integrity warnings need review; no changes were made.
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Media index error: " . $exception->getMessage() . "\n");
    exit(1);
}
