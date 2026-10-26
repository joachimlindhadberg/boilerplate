<?php

declare(strict_types=1);
/**
 * V2.4.4z25 final *SOURCE* QA. No application bootstrap, .env, session,
 * database, HTTP, file writes, commands, release building or migrations.
 * Green status verifies code invariants only, NOT production deployment.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(2);
}
if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--help')) {
    fwrite(STDERR, "Usage: php core/cli/final-production-audit.php [--help]\n");
    exit(2);
}
if (isset($argv[1])) {
    echo "Final production source QA; no database, HTTP, writes or go-live certification.\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
$passed = 0;
$failed = 0;
$check = static function (bool $ok, string $label) use (&$passed, &$failed): void {
    $ok ? ++$passed : ++$failed;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . "\n";
};
$read = static function (string $relative) use ($root): string {
    // Explicitly version-controlled code/docs only. Never open .env or uploads.
    if (str_contains($relative, '..') || !preg_match('~^(?:core|modules|public_html|tests|docs)/[a-zA-Z0-9_./-]+$~D', $relative)) {
        throw new LogicException('Unsafe source audit path');
    }
    $value = @file_get_contents($root . '/' . $relative);
    return $value === false ? '' : $value;
};
$all = static fn(string $text, array $needles): bool => !array_filter($needles,
    static fn(string $needle): bool => !str_contains($text, $needle));

$runner = $read('tests/integration/run.sh');
$version = $read('public_html/incl/init.php');
$codeAudit = $read('core/cli/code-audit.php');
$qa = $read('core/cli/qa.php');
$security = $read('core/cli/security-audit.php');
$install = $read('core/cli/install-update-audit.php');
$backup = $read('core/cli/backup-rollback-audit.php');
$packager = $read('tests/release/release_zip.py');
$preflight = $read('tests/release/install_update_preflight.py');
$archive = $read('tests/release/backup_archive_preflight.py');
$routes = $read('public_html/.htaccess');
$adminRoutes = $read('public_html/admin/.htaccess');
$init = $read('public_html/incl/init.php');
$policy = $read('docs/design/DATABASE-AND-RELEASE-POLICY-V1.0.md');
$productionPolicy = $read('docs/design/QA-PRODUCTION-CONTRACT.md');
$finalReport = $read('docs/audits/FINAL-PRODUCTION-QA-V2.4.4z25.md');

$versionMatch = [];
$check(preg_match("~define\('BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'\)~", $version, $versionMatch) === 1
    && version_compare($versionMatch[1], '2.4.4.112', '>='),
    'Version — z25 minimum >= 2.4.4.112 retained');
$check($all($runner, ['    verify-production)', 'core/cli/final-production-audit.php', '    verify-fixture)']),
    'Isolation — dedicated final source QA entry exists');
$begin = strpos($runner, '    verify-production)');
$end = strpos($runner, '    verify-fixture)');
$branch = ($begin !== false && $end !== false && $end > $begin) ? substr($runner, $begin, $end - $begin) : '';
$check($branch !== '' && $all($branch, [
    'core/cli/security-audit.php', 'core/cli/install-update-audit.php',
    'core/cli/backup-rollback-audit.php', 'core/cli/final-production-audit.php',
]) && !preg_match('~\b(?:normal_db|fixture_db|code_tests|curl|mysql|mysqldump|http-smoke|http[s]?://|--apply|--database|migrate\.php|SiteBackup::create|verify-site|verify-fixture|zipfile|archive_preflight|python)\b~i', $branch),
    'Isolation — final branch calls only four source audits');
$check($all($runner, ['    verify-security)', '    verify-install)', '    verify-backup)', '    verify)', '    verify-code)', '    verify-site)', '    verify-fixture)']),
    'Isolation — previous verification commands are preserved');
$check($all($runner, ['    fixture_db()']) === false && $all($runner, ['fixture_db() {', 'boilerplate_integration_only', 'cms_test_only']),
    'Isolation — explicit synthetic database restrictions remain present');
$check($all($security, ['Security & Data Integrity source audit', 'PHP_SAPI', 'Release —']) ||
       $all($security, ['Security & Data Integrity source audit', 'PHP_SAPI', 'Core SCSS/CSS byte-identical']),
    'Security — previous 60-check source audit remains present');
$check($all($install, ['Installation & Update source audit', 'Historical PHP migration files are shipped']),
    'Install — prior installation source audit retained');
$check($all($backup, ['Backup & Rollback source audit', 'Offline inspector']),
    'Backup — prior backup source audit retained');
$check($all($archive, ["mode='r|gz'", "'upload_sha256'", "member.issym()", 'archive.extractfile(member)']),
    'Backup — checksum, structure and link protection retained');
$check($all($packager, ['EXCLUDED_DIRS', 'EXCLUDED_FILES', 'EMPTY_RUNTIME_DIRS', 'z.testzip()', 'FILE_MODE', 'DIR_MODE']),
    'Package — release ZIP permissions and CRC checks retained');
$check($all($preflight, ['Historical migrations changed or removed', 'MAX_TOTAL_BYTES', 'archive.testzip()']),
    'Upgrade — immutable migration and archive checks retained');
$check($all($codeAudit, ['--release', 'SCSS/CSS']) || $all($codeAudit, ['--release', 'admin.scss']),
    'Code — comprehensive audit supports release validation');
$check($all($qa, ["'--production'", "'--database'", 'No changes were made.']),
    'Environment — optional production/database diagnostics are explicitly separate');
$check($all($qa, ['APP_ENV=production', 'Effective HTTPS origin', 'RATE_LIMIT_SECRET']),
    'Environment — HTTPS, production mode and stable rate-limit secret have diagnostic checks');
$check($all($qa, ['Private error-log storage', 'Production-only checks', 'Read-only migration status']),
    'Environment — private logs, staging warning and read-only DB status supported');
$check($all($routes, ['uploads|assets/uploads', 'incl(?:/|$)', 'admin/(?:incl|views)', 'X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy']),
    'Apache — source rules include upload execution deny, private view deny and safety headers');
$check($all($adminRoutes, ['^(?:incl|views)', '^assets/scss']),
    'Apache — admin-specific source/view restrictions retained');
$phpRequirement = $read('core/bootstrap/php-requirement.php');
$check($all($init, ['BOILERPLATE_VERSION', 'boilerplate_require_php_runtime();'])
    && $all($phpRequirement, ['PHP_VERSION_ID', '80300', '8.3.0']),
    'Runtime — shared PHP compatibility guard and version declaration retained');
$check($read('public_html/admin/assets/css/admin.css') !== '' &&
       $read('public_html/admin/assets/css/admin.css') === $read('public_html/admin/assets/scss/admin.scss'),
    'Styles — Core admin SCSS and CSS byte-identical');
$check($read('public_html/assets/css/style.css') !== '' && is_file($root . '/public_html/assets/scss/style.scss'),
    'Styles — frontend assets and theme sources present');
$check($all($policy, ['Udviklings-/lokal-test-ZIP', 'Produktions-/deployment-ZIP', 'schema_migrations']),
    'Release — development and production ZIP policy stays distinct');
$check($all($productionPolicy, ['staging', 'restore', 'PHP', 'cron', 'no tests/fixtures in production']) ||
       $all($productionPolicy, ['staging', 'restore', 'PHP', 'cron', 'no tests/fixtures']),
    'Deployment — staging, restore and deployment packaging remain separate gates');
$check(is_file($root . '/docs/audits/SECURITY-DATA-INTEGRITY-AUDIT-V2.4.4z22.md') &&
       is_file($root . '/docs/audits/INSTALLATION-UPDATE-READINESS-V2.4.4z23.md') &&
       is_file($root . '/docs/audits/BACKUP-ROLLBACK-READINESS-V2.4.4z24.md'),
    'Evidence — prior scoped risk reports retained');
$check($all($finalReport, ['NO-GO', 'restore', 'staging', 'verify-fixture', '0 upload', '2.4.4.111']),
    'Evidence — sign-off report explicitly preserves unverified gates and approved base');
$check(is_file($root . '/docs/releases/FINAL-PRODUCTION-QA-V2.4.4z25.md'),
    'Release — targeted z25 release notes present');
$check(is_file($root . '/tests/final-production-qa-v2_4_4z25.php'),
    'Regression — final source audit contract regression shipped');
$check(is_file($root . '/tests/release/test_backup_archive_preflight.py') &&
       is_file($root . '/tests/release/test_install_update_preflight.py') &&
       is_file($root . '/tests/integration/workflows.php'),
    'Regression — backup, update and optional workflow tests retained, not run');

// Historical migration integrity: pinned to locally approved z24a release.
// Unlike a migration COUNT test, this catches inadvertent mutations to history.
$approved = [
    'database/updates/20261013_000_core_schema.php' => '7361b12d42834fd9704c8d01d3c7c010d119871576dee53cb17d9a2a91fa3f66',
    'database/updates/20261013_010_media_reference_index.php' => '379b65e4df0f2fb799dd30292a2be6e3c8e1f6a10f403f01af348131cb3822cc',
    'database/updates/20261016_000_pages_author_audit.php' => 'eb65b376ab09efa930ddbe48bd4e81b6828d3c8113d20cb47e590224f4dd6125',
    'database/migrations/20261012_activity_log_v2_2_1.sql' => '8ebf4b2947273ac08f0048ba92ba572bee19e09d9a559b7ab5b246abc43a9889',
    'database/migrations/20261012_admin_localization_v2_2_0.sql' => '2c353cd4a4ae10f2826a9b38668003ec3f3c9897920bdae7f5556865ef222065',
    'database/migrations/20261012_page_revisions_v1_8_0.sql' => 'd7dc8694d0a9d56415b78b4e41e1e16af91c8d00b068e00dd1316f78722a70ca',
    'database/migrations/20261012_page_schedules_v1_9_0.sql' => '668185f8c2e15200b9cc216d766b507aba6847e34b4b0ce665ba6b176404c5e8',
    'database/migrations/20261013_security_rate_limits_v2_3_7.sql' => '85ea9806a4ff682f3bd8932595450e95424fb02422acca8732d52d042274e348',
    'database/migrations/20261015_forms_rows_v2_4_4t.sql' => '274dede7094e153d73785672f7bd34e73112875b910a8541c16bbcb69220ddc6',
    // New QA1 migration is separate; all previous approved hashes stay immutable.
    'database/migrations/20261023_auth_sessions_v2_5_0_qa1.sql' => '2196203f60a1aad95827688a131ec2280306f7ef2a999a1c7e78a62456125103',
    'modules/forms/migrations/20261013_100_forms_schema.php' => 'b44dd55b8f5c124bf70640dc5ea7e173896a2a9c80c581e20973fd844206010a',
    'modules/gallery/migrations/20261013_100_gallery_schema.php' => '7825a9e81c039476821d30f5db763d24296394c517b8b54ec7609833d98474b2',
    'modules/gallery/migrations/20261019_100_gallery_lightbox.php' => 'a1a35472662850c770e0839b4c660f7b758a5bda48768c1c8701edefaa731062',
    'database/updates/20261019_200_dashboard_cards.php' => 'b05146bb28f5ebe345be2e3ec3423f37a55f8cec797036a171a497bd512898a1',
];
$allHistorical = array_merge(
    glob($root . '/database/updates/*.php') ?: [],
    glob($root . '/database/migrations/*.sql') ?: [],
    glob($root . '/modules/*/migrations/*.php') ?: []
);
$match = count($allHistorical) === count($approved);
foreach ($approved as $relative => $hash) {
    $p = $root . '/' . $relative;
    $match = $match && is_file($p) && !is_link($p) && hash_equals($hash, hash_file('sha256', $p));
}
$check($match, 'Migration — approved historical checksums immutable; new 4A.6.8a SQL pinned');

$moduleDirs = glob($root . '/modules/*', GLOB_ONLYDIR) ?: [];
$moduleSlugs = array_map('basename', $moduleDirs);
sort($moduleSlugs, SORT_STRING);
$check($moduleSlugs === ['example', 'forms', 'gallery'], 'Modules — expected three module directories');
$manifestOk = true;
foreach ($moduleDirs as $dir) {
    $file = $dir . '/module.json';
    $json = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $manifestOk = $manifestOk && is_array($json) && ($json['slug'] ?? null) === basename($dir);
}
$check($manifestOk, 'Modules — manifests parse and match module directories');
$translationOk = true;
foreach (array_merge([$root . '/core/lang/translations.json'], glob($root . '/modules/*/lang/translations.json') ?: []) as $file) {
    $translationOk = $translationOk && is_file($file) && json_last_error() === JSON_ERROR_NONE;
    $raw = is_file($file) ? (string)file_get_contents($file) : '';
    $translationOk = $translationOk && $raw !== '' && json_decode($raw, true) !== null && json_last_error() === JSON_ERROR_NONE;
}
$check($translationOk, 'Localization — all Core/module catalogs are parseable JSON');

printf("Final Production SOURCE QA: %d PASS / %d FAIL (no DB / HTTP / writes)\n", $passed, $failed);
echo "READINESS: SOURCE AUDIT ONLY — production GO-LIVE remains NO-GO pending staging/restore gates\n";
exit($failed ? 1 : 0);
