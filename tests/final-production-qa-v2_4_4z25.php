<?php

declare(strict_types=1);
/** V2.4.4z25 contract tests, source only; never include web bootstrap. */
$root = dirname(__DIR__);
$passed = 0;
$failed = 0;
$check = static function (bool $ok, string $name) use (&$passed, &$failed): void {
    $ok ? ++$passed : ++$failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . "\n";
};
$read = static fn(string $path): string => (string) (file_get_contents($root . '/' . $path) ?: '');
$runner = $read('tests/integration/run.sh');
$source = $read('core/cli/final-production-audit.php');
$report = $read('docs/audits/FINAL-PRODUCTION-QA-V2.4.4z25.md');
$release = $read('docs/releases/FINAL-PRODUCTION-QA-V2.4.4z25.md');
$version = $read('public_html/incl/init.php');
$check(str_contains($runner, '    verify-production)') && str_contains($runner, 'core/cli/final-production-audit.php'), 'Runner provides final production audit');
$check(str_contains($source, "PHP_SAPI !== 'cli'") && !str_contains($source, 'require_once'), 'CLI-only and no CMS bootstrap');
$check(!preg_match('~\b(?:mysqli|PDO|curl_exec|file_put_contents|unlink|rename|fwrite\s*\(\s*\$root|shell_exec|exec\s*\(|system\s*\()~', $source), 'Source audit cannot call database, network or site writes');
$check(!str_contains($source, 'getenv(') && !str_contains($source, '$_ENV') && !str_contains($source, '$_SERVER'), 'Source audit never loads environment variables');
$check(str_contains($source, 'hash_file(\'sha256\'') && str_contains($source, 'hash_equals('), 'Pinned SHA-256 migration checks present');
$check(str_contains($source, 'count($allHistorical) === count($approved)'), 'New historical migration files are detected');
$check(str_contains($report, 'NO-GO') && str_contains($report, 'IKKE VERIFICERET'), 'Risks and not-yet-tested gates remain explicit');
$check(str_contains($report, 'verify-fixture') && str_contains($report, 'restore'), 'User safe no fixture and restore limitations retained');
$check(str_contains($release, 'lokal-test-ZIP') && str_contains($release, 'database'), 'Release does not misrepresent deployment status');
$check(preg_match("~define\('BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'\)~", $version, $m) === 1 && version_compare($m[1], '2.4.4.112', '>='), 'z25 minimum version >= 2.4.4.112');
$check(str_contains($source, 'READINESS: SOURCE AUDIT ONLY') && str_contains($source, 'NO-GO'), 'Pass cannot be confused with go-live signoff');
$check($read('public_html/admin/assets/css/admin.css') === $read('public_html/admin/assets/scss/admin.scss'), 'Global SCSS/CSS unchanged and synchronized');
$check(is_file($root . '/tests/release/test_backup_archive_preflight.py'), 'Archive regression retained');
$check(is_file($root . '/tests/release/test_install_update_preflight.py'), 'Update regression retained');
$check(is_file($root . '/docs/design/QA-PRODUCTION-CONTRACT.md'), 'Authoritative production QA policy retained');
$check(is_file($root . '/docs/design/DATABASE-AND-RELEASE-POLICY-V1.0.md'), 'Database / release policy retained');
$check(str_contains($runner, '    verify-fixture)') && str_contains($runner, '    verify-backup)'), 'Existing commands preserved');
$check(!str_contains($runner, 'verify-production)\n        fixture_db') && !str_contains($runner, 'verify-production)\n        normal_db'), 'New command never directly invokes DB helpers');
$check(str_contains($source, "'modules/forms/migrations/20261013_100_forms_schema.php'") &&
    str_contains($source, "'modules/gallery/migrations/20261013_100_gallery_schema.php'"), 'Actual module migration filenames are pinned');
echo "Final Production QA z25: $passed PASS / $failed FAIL\n";
exit($failed ? 1 : 0);
