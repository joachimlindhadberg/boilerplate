<?php

declare(strict_types=1);
/**
 * V2.4.4z23 read-only installation/update readiness audit. No CMS bootstrap,
 * DB, sessions, HTTP, migration execution or writes to the site.
 * It inspects a fixed allowlist of CODE files, never .env or live uploads.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(2);
}
if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--help')) {
    fwrite(STDERR, "Usage: php core/cli/install-update-audit.php [--help]\n");
    exit(2);
}
if (isset($argv[1])) {
    echo "Read-only source audit. No database, HTTP, filesystem mutation or migration execution.\n";
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
    return (string) (@file_get_contents($root . '/' . $relative) ?: '');
};
$contains = static fn(string $haystack, array $needles): bool =>
    count(array_filter($needles, static fn(string $needle): bool => !str_contains($haystack, $needle))) === 0;

$init = $read('public_html/incl/init.php');
$version = '';
if (preg_match("~define\('BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'\)~", $init, $match)) {
    $version = $match[1];
}
$check($version !== '' && version_compare($version, '2.4.4.109', '>='), 'Release metadata 2.4.4.109 or later');

$runner = $read('tests/integration/run.sh');
$cli = $read('core/cli/migrate.php');
$migrate = $read('core/classes/MigrationRunner.php');
$packager = $read('tests/release/release_zip.py');
$preflight = $read('tests/release/install_update_preflight.py');
$security = $read('core/cli/security-audit.php');
$check($contains($runner, ['verify-install)', 'core/cli/install-update-audit.php']), 'Separate verify-install source-only command exists');
$installStart = strpos($runner, '    verify-install)');
$fixtureStart = strpos($runner, '    verify-fixture)');
$installBlock = ($installStart !== false && $fixtureStart !== false && $installStart < $fixtureStart)
    ? substr($runner, $installStart, $fixtureStart - $installStart) : '';
$check($installBlock !== '' && str_contains($installBlock, 'install-update-audit.php') &&
    !preg_match('~fixture_db|normal_db|code_tests|http-smoke|curl|--apply|--database|verify-site|verify-fixture~', $installBlock),
    'verify-install never contacts a database/HTTP or invokes migrations');
$check($contains($runner, ['verify-security)', 'verify-fixture)', 'normal_db() {', 'fixture_db() {']), 'Existing commands are retained');
$check($contains($security, ['Security & Data Integrity source audit', 'Core SCSS/CSS byte-identical']), 'Previously approved security audit retained');
$check($contains($cli, ["'--status'", "'--apply'", 'PHP_SAPI', '$runner->status()', '$runner->apply()']), 'Explicit CLI migration modes retained');
$check(strpos($cli, '$runner->status()') !== false && strpos($cli, '$runner->apply()') !== false &&
    strpos($cli, "if (\$mode === '--status')") !== false, 'Status and applying migrations are separate branches');
$check($contains($migrate, ['public function status()', 'public function apply()', 'hash_equals(', 'checksum-mismatch', 'missing-file']),
    'Migration status and immutable checksum enforcement retained');
$check($contains($migrate, ['assertSupportedBase()', 'SELECT GET_LOCK', 'RELEASE_LOCK', 'CREATE TABLE IF NOT EXISTS schema_migrations']),
    'Historical apply still checks supported schema and migration lock');
$check(strpos($migrate, 'public function status()') < strpos($migrate, 'public function apply()') &&
    !str_contains(substr($migrate, strpos($migrate, 'public function status()'), strpos($migrate, 'public function apply()') - strpos($migrate, 'public function status()')), '$this->db->query('),
    'Status path does not issue a direct database write');
$check($contains($migrate, ['database/updates', "'/migrations'",  "'/*.php'", "'sha256'", "Invalid migration filename or symlink"]),
    'PHP migration discovery scopes, hashes and symlink guard retained');

$migrationFiles = array_merge(glob($root . '/database/updates/*.php') ?: [], glob($root . '/modules/*/migrations/*.php') ?: []);
$check(count($migrationFiles) >= 3, 'Historical PHP migration files are shipped');
$validNames = true;
foreach ($migrationFiles as $file) {
    $validNames = $validNames && !is_link($file) && (bool)preg_match('~^[0-9]{8}_[a-z0-9_-]+\.php$~D', basename($file));
}
$check($validNames, 'Discovered migration files have safe canonical names and no symlinks');
$check(count(glob($root . '/database/migrations/*.sql') ?: []) >= 1, 'Historical manual SQL migrations are retained separately');
$check($contains($packager, ['EXCLUDED_FILES', 'EXCLUDED_DIRS', 'EMPTY_RUNTIME_DIRS', 'FILE_MODE', 'DIR_MODE', 'z.testzip()', 'zipfile.ZipFile']),
    'Release ZIP packager preserves existing exclusion and CRC checks');
$check($contains($preflight, ['def check_path(', 'def prohibited(', 'class Archive:', 'def compare(', 'migration_hashes(', 'archive.testzip()', '--from-zip', '--release']),
    'ZIP update preflight checks members and migration history');
$check($contains($preflight, ['.env.', 'uploads', 'stat.S_IFREG', 'stat.S_IFDIR', 'MAX_TOTAL_BYTES', 'PreflightFailure']),
    'Preflight explicitly rejects private/runtime content and unsafe member types');
$check(!preg_match('~(?:extractall|extract\(|subprocess|os\.system|urllib|requests|pymysql|mysql\.connector|shutil\.(?:copy|move))\s*\(~', $preflight),
    'Preflight has no extraction, command execution or copy path');
$check($contains($preflight, ['VERSION_RX', 'version_tuple(', 'Historical migrations changed or removed']),
    'Preflight checks version ordering and historical migrations');
$check(is_file($root . '/docs/design/DATABASE-AND-RELEASE-POLICY-V1.0.md'), 'Binding database and release policy preserved');
$check(is_file($root . '/docs/design/QA-PRODUCTION-CONTRACT.md'), 'Production readiness and staging contract preserved');
$check(is_file($root . '/docs/releases/INSTALLATION-UPDATE-READINESS-V2.4.4z23.md'), 'Release documentation present');
$check(is_file($root . '/docs/audits/INSTALLATION-UPDATE-READINESS-V2.4.4z23.md'), 'Installation/update audit findings present');

$errors = count(array_filter($results, static fn(array $row): bool => !$row[0]));
echo sprintf('Installation & Update source audit: %d PASS / %d FAIL (no DB / HTTP / writes)', count($results) - $errors, $errors) . "\n";
exit($errors === 0 ? 0 : 1);
