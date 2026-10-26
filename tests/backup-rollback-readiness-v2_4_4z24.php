<?php

declare(strict_types=1);
/** Historical-test-compatible source-only regression for z24. No DB or HTTP. */
$root = dirname(__DIR__);
$passed = 0;
$failed = 0;
$check = static function (bool $ok, string $name) use (&$passed, &$failed): void {
    $ok ? ++$passed : ++$failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . "\n";
};
$read = static fn(string $name): string => (string)(file_get_contents($root . '/' . $name) ?: '');
$runner = $read('tests/integration/run.sh');
$source = $read('core/cli/backup-rollback-audit.php');
$archive = $read('tests/release/backup_archive_preflight.py');
$versions = $read('public_html/incl/init.php');
$check(str_contains($runner, '    verify-backup)') && str_contains($runner, 'backup-rollback-audit.php'), 'Dedicated source audit command');
$check(str_contains($source, 'PHP_SAPI') && !str_contains($source, 'require_once'), 'No CMS bootstrap in source audit');
$check(str_contains($source, "'core/classes/SiteBackup.php'") || str_contains($source, 'core/classes/SiteBackup.php'), 'Backup implementation inspected as data');
$check(str_contains($runner, '    verify-fixture)') && str_contains($runner, 'fixture_db'), 'Fixture branch retained but never invoked by backup preflight');
$begin = strpos($runner, '    verify-backup)');
$end = strpos($runner, '    verify-fixture)');
$branch = $begin !== false && $end !== false ? substr($runner, $begin, $end - $begin) : '';
$check($branch !== '' && !preg_match('~normal_db|fixture_db|code_tests|http-smoke|mysql|migrate|curl|--apply|--database~', $branch), 'Backup source preflight excludes DB and HTTP calls');
$check(str_contains($archive, "mode='r|gz'") && str_contains($archive, 'archive.extractfile(member)'), 'Reads compressed archive without extraction');
$check(str_contains($archive, 'gzip.open(') && str_contains($archive, 'Gzip checksum error'), 'Full gzip CRC validation');
$check(!preg_match('~(?:extractall|extract\(|file_put_contents|os\.system|subprocess|shutil\.copy|shutil\.move)\s*\(~', $archive), 'Archive preflight never extracts or executes writes');
$check(str_contains($archive, "'database_sha256'") && str_contains($archive, "'upload_sha256'"), 'Manifest hashes verified');
$check(str_contains($archive, 'member.issym()') && str_contains($archive, 'member.islnk()'), 'Tar links denied');
$check(str_contains($archive, 'Duplicate archive member'), 'Duplicate member denied');
$check(str_contains($archive, 'MAX_TOTAL_BYTES') && str_contains($archive, 'MAX_MEMBERS'), 'Tar size/member bounds');
$check(str_contains($archive, "part in ('', '.', '..')"), 'Traversal path handling');
$check(is_file($root . '/tests/release/test_backup_archive_preflight.py'), 'Synthetic archiver tests included');
$check(is_file($root . '/docs/audits/BACKUP-ROLLBACK-READINESS-V2.4.4z24.md'), 'Risks documented');
$check(is_file($root . '/docs/releases/BACKUP-ROLLBACK-READINESS-V2.4.4z24.md'), 'Restore/rollback procedure documented');
$check(preg_match("~define\('BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'\)~", $versions, $match) === 1 && version_compare($match[1], '2.4.4.110', '>='), 'Internal release retains z24 minimum version');
$check($read('public_html/admin/assets/scss/admin.scss') === $read('public_html/admin/assets/css/admin.css'), 'Core stylesheet still synchronized');
$check($read('core/classes/SiteBackup.php') !== '' && str_contains($read('core/classes/SiteBackup.php'), 'Fresh empty database only'), 'Current manual restore guard retained');
$check($read('tests/release/install_update_preflight.py') !== '' && str_contains($read('tests/release/install_update_preflight.py'), 'Historical migrations changed or removed'), 'Existing ZIP compatibility control retained');
echo "Backup/Rollback Readiness z24: $passed PASS / $failed FAIL\n";
exit($failed ? 1 : 0);
