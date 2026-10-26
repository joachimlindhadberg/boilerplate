<?php

declare(strict_types=1);
$root = dirname(__DIR__);
$checks = 0;
$failures = 0;
$check = static function (bool $pass, string $message) use (&$checks, &$failures): void {
    ++$checks;
    if (!$pass) ++$failures;
    echo ($pass ? 'PASS: ' : 'FAIL: ') . $message . "\n";
};
$runner = file_get_contents($root . '/tests/integration/run.sh');
$audit = file_get_contents($root . '/core/cli/install-update-audit.php');
$preflight = file_get_contents($root . '/tests/release/install_update_preflight.py');
$version = file_get_contents($root . '/public_html/incl/init.php');
$check(str_contains($runner, 'verify-install)') && str_contains($runner, 'install-update-audit.php'), 'Standalone safe install audit command');
$check(str_contains($audit, "No database") || str_contains($audit, 'No CMS bootstrap'), 'Source-only audit explains safe operating scope');
$check(str_contains($preflight, 'archive.testzip()'), 'Independent ZIP CRC verification');
$check(str_contains($preflight, "if rel == ''") && str_contains($preflight, "if raw.endswith('/') != is_dir"), 'Safe ZIP root and directory normalization');
$check(str_contains($preflight, 'Historical migrations changed or removed'), 'Existing migration checksum differences are fatal');
$check(str_contains($preflight, 'REVIEW REQUIRED: new migration'), 'New migration requires human approval');
$check(str_contains($preflight, 'def prohibited(') && str_contains($preflight, 'public_html'), 'Runtime and private data prohibitions');
$check(!str_contains($preflight, 'archive.extractall('), 'Preflight never extracts ZIP into local site');
$check(is_file($root . '/tests/release/test_install_update_preflight.py'), 'Adversarial synthetic ZIP tests included');
$check(preg_match("~define\('BOILERPLATE_VERSION', '([0-9.]+)'\)~", $version, $m) === 1 && version_compare($m[1], '2.4.4.109', '>='), 'Release version at least 2.4.4.109');
$check(is_file($root . '/docs/releases/INSTALLATION-UPDATE-READINESS-V2.4.4z23.md'), 'Release notes exist');
$check(is_file($root . '/docs/audits/INSTALLATION-UPDATE-READINESS-V2.4.4z23.md'), 'Prioritized audit exists');
echo "Installation Update Readiness z23: " . ($checks - $failures) . " PASS / $failures FAIL\n";
exit($failures ? 1 : 0);
