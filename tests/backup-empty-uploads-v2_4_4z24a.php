<?php
/** V2.4.4z24a: static regression contract for SiteBackup zero-upload JSON manifest. */
declare(strict_types=1);
$root = dirname(__DIR__);
$source = (string)file_get_contents($root . '/core/classes/SiteBackup.php');
$preflight = (string)file_get_contents($root . '/tests/release/backup_archive_preflight.py');
$tests = (string)file_get_contents($root . '/tests/release/test_backup_archive_preflight.py');
$version = (string)file_get_contents($root . '/public_html/incl/init.php');
$checks = [
    'PHP generator builds empty checksum PHP array' => str_contains($source, '$fileHashes = [];'),
    'PHP generator encodes manifest as JSON' => str_contains($source, 'json_encode($manifest'),
    'Archive preflight accepts the PHP empty-list form only with zero count' => str_contains($preflight, 'type(expected_uploads) is list and not expected_uploads') && str_contains($preflight, "manifest['upload_files'] == 0"),
    'Python regression covers PHP empty checksum list' => str_contains($tests, 'def test_php_empty_list_for_zero_uploads'),
    'Python regression rejects empty list with nonzero declared files' => str_contains($tests, 'def test_php_empty_list_rejected_when_count_nonzero'),
    'Python regression rejects unlisted actual uploads' => str_contains($tests, 'def test_php_empty_list_rejected_when_upload_exists'),
    'Python regression rejects nonempty JSON checksum lists' => str_contains($tests, 'def test_nonempty_checksum_list_rejected'),
    'Hotfix release metadata incremented' => (preg_match("~define\('BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'\)~", $version, $versionMatch) === 1 && version_compare($versionMatch[1], '2.4.4.111', '>=')),
];
$failed = 0;
foreach ($checks as $message => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$ok) $failed++;
}
printf("Backup zero-upload compatibility z24a: %d PASS / %d FAIL\n", count($checks)-$failed, $failed);
exit($failed ? 1 : 0);
