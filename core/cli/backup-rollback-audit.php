<?php

declare(strict_types=1);
/**
 * V2.4.4z24 source-only backup and rollback readiness audit. Never includes
 * installed .env, bootstrap, DB connection or user files; never calls HTTP.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(2);
}
if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--help')) {
    fwrite(STDERR, "Usage: php core/cli/backup-rollback-audit.php [--help]\n");
    exit(2);
}
if (isset($argv[1])) {
    echo "Source-only backup/rollback audit. No database, HTTP, restore or writes.\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
$results = [];
$check = static function (bool $ok, string $message) use (&$results): void {
    $results[] = [$ok, $message];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $message . "\n";
};
$read = static function (string $relative) use ($root): string {
    if (!preg_match('~^(?:core|public_html|tests)/[a-zA-Z0-9_./-]+$~D', $relative) || str_contains($relative, '..')) {
        throw new LogicException('Invalid source-only audit path');
    }
    return (string)(@file_get_contents($root . '/' . $relative) ?: '');
};
$has = static fn(string $text, array $pieces): bool => count(array_filter($pieces,
    static fn(string $piece): bool => !str_contains($text, $piece))) === 0;
$backup = $read('core/classes/SiteBackup.php');
$runner = $read('tests/integration/run.sh');
$archive = $read('tests/release/backup_archive_preflight.py');
$zip = $read('tests/release/install_update_preflight.py');
$packager = $read('tests/release/release_zip.py');
$tools = $read('public_html/admin/tools.php');
$init = $read('public_html/incl/init.php');
$check(preg_match("~define\('BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'\)~", $init, $match) === 1 && version_compare($match[1], '2.4.4.110', '>='), 'Release metadata retains 2.4.4.110 minimum');
$check($has($runner, ['    verify-backup)', 'core/cli/backup-rollback-audit.php']), 'Dedicated source-only backup/rollback audit command');
$begin = strpos($runner, '    verify-backup)');
$end = strpos($runner, '    verify-fixture)');
$branch = ($begin !== false && $end !== false && $end > $begin) ? substr($runner, $begin, $end - $begin) : '';
$check($branch !== '' && !preg_match('~(?:normal_db|fixture_db|code_tests|http-smoke|curl|--apply|--database|workflows|SiteBackup::create|backup_archive_preflight)~', $branch), 'Safe audit branch excludes DB, HTTP, migrations, backup creation and archive parsing');
$check($has($runner, ['    verify-fixture)', '    verify-install)', '    verify-security)', '    verify-code)']), 'Earlier commands remain separate');
$check($has($backup, ["'format' => 'boilerplate-site-data-backup'", "'version' => 1", "'database_sha256'", "'upload_sha256'", "'database_tables'", "'database_triggers'"]), 'Backup manifest records version, database and file hashes');
$check($has($backup, ["'contains_sensitive_data' => true", "'excludes'", "'.env'", "'application code'"]), 'Backup scope excludes code and environment credentials but marks sensitive contents');
$check($has($backup, ["'database.sql'", "'manifest.json'", "'public_html/uploads/'", "'public_html/assets/uploads/'"]), 'Backup scope includes SQL, manifest and both managed upload roots');
$check($has($backup, ['START TRANSACTION WITH CONSISTENT SNAPSHOT', "'SHOW CREATE TABLE '", 'SELECT * FROM ', 'INFORMATION_SCHEMA.TRIGGERS']), 'Database dump includes table definitions/rows and registered triggers');
$check($has($backup, ['Referenced media is missing', "'public_html/' . \$candidate", 'hash_file(\'sha256\'']), 'Referenced media missing from archive fails and upload hashes are recorded');
$check($has($backup, ['is_link($base)', '$file->isLink()', '$file->isReadable()', "str_contains(\$relative, '..')"]), 'Backup file collection checks symbolic links and unsafe paths');
$check($has($backup, ['mkdir($work, 0700)', '@chmod($file, 0600)', '@chmod($destination, 0600)', 'finally {']), 'Temporary SQL and download have restricted permissions and cleanup');
$check($has($backup, ['Fresh empty database only', 'readDatabaseSql']) === false, 'No automatic restore method introduced into SiteBackup');
$check($has($archive, ["mode='r|gz'", 'archive.extractfile(member)', 'hashlib.sha256()', "'database.sql'", "'manifest.json'", "'upload_sha256'"]), 'Offline inspector streams archive, verifies SQL and uploads without extraction');
$check($has($archive, ['member.issym()', 'member.islnk()', 'Duplicate archive member', 'MAX_TOTAL_BYTES', 'MAX_MEMBERS']), 'Offline inspector rejects special entries, duplicates and over-limit archives');
$check($has($archive, ["parts = clean.split('/')", "part in ('', '.', '..')", 'Unexpected file outside backup data scope']), 'Offline inspector restricts canonical backup member paths');
$check(!preg_match('~(?:extractall|extract\(|subprocess|os\.system|shutil\.(?:copy|move)|urllib|requests|pymysql|mysql\.connector|file_put_contents)\s*\(~', $archive), 'Offline inspector has no extraction, system writes, subprocess, HTTP or DB operations');
$check($has($zip, ['def compare(', 'Historical migrations changed or removed', 'INFO: Overlay installation never removes old files']), 'Code rollback preparation reuses existing read-only version/migration comparer');
$check($has($packager, ['EXCLUDED_FILES', 'EXCLUDED_DIRS', 'EMPTY_RUNTIME_DIRS', 'z.testzip()']), 'Release ZIP excludes user data and verifies CRC');
$check(is_file($root . '/tests/release/test_backup_archive_preflight.py'), 'Synthetic malicious/valid backup archive tests included');
$check(is_file($root . '/docs/audits/BACKUP-ROLLBACK-READINESS-V2.4.4z24.md'), 'Prioritized backup/rollback limitations documented');
$check(is_file($root . '/docs/releases/BACKUP-ROLLBACK-READINESS-V2.4.4z24.md'), 'Release instructions present');
$check(is_file($root . '/docs/design/BACKUP-MEDIA-CONTRACT.md'), 'Existing Core backup safety contract preserved');
$check($has($tools, ['new SiteBackup()', 'admin_verify_csrf(', "'site_backup'"]), 'Existing backup action remains behind admin controller flow (static probe)');
$check($read('public_html/admin/assets/css/admin.css') === $read('public_html/admin/assets/scss/admin.scss'), 'Core SCSS and CSS byte-identical');
$errors = count(array_filter($results, static fn(array $r): bool => !$r[0]));
echo sprintf('Backup & Rollback source audit: %d PASS / %d FAIL (no DB / HTTP / writes)', count($results) - $errors, $errors) . "\n";
exit($errors ? 1 : 0);
