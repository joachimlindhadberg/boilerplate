<?php
declare(strict_types=1);
/** Non-mutating V2.4.4z8 macOS native integration harness contract. */
$root = dirname(__DIR__);
$pass = 0;
$check = static function (bool $ok, string $label) use (&$pass): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
    ++$pass;
    echo "PASS: {$label}\n";
};
foreach ([
    'tests/integration/run.sh', 'tests/integration/http-smoke.sh',
    'tests/integration/fixture-guard.php', 'tests/integration/db-smoke.php',
    'tests/integration/fixtures/base-schema.sql',
    'tests/integration/fixtures/synthetic-data.sql',
    'docs/design/INTEGRATION-TEST-ENVIRONMENT-V1.0.md',
    'docs/releases/INTEGRATION-TEST-ENVIRONMENT-V2.4.4z8.md',
    'docs/releases/INTEGRATION-TEST-ENVIRONMENT-V2.4.4z8b.md',
] as $file) $check(is_file($root . '/' . $file), 'Native integration asset: ' . $file);
$runner = (string)file_get_contents($root . '/tests/integration/run.sh');
$http = (string)file_get_contents($root . '/tests/integration/http-smoke.sh');
$guard = (string)file_get_contents($root . '/tests/integration/fixture-guard.php');
$schema = (string)file_get_contents($root . '/tests/integration/fixtures/base-schema.sql');
$fixture = (string)file_get_contents($root . '/tests/integration/fixtures/synthetic-data.sql');
$check(str_contains($runner, 'verify-code') && str_contains($runner, 'verify-site') && str_contains($runner, 'verify-fixture'), 'Native code, site and fixture entrypoints');
$check(str_contains($runner, 'qa.php --database') && str_contains($runner, 'migrate.php --status'), 'Read-only normal DB verification');
$check(str_contains($runner, 'fixture-guard.php') && strpos($runner, 'fixture-guard.php') < strpos($runner, 'migrate.php --apply'), 'Fixture guard precedes all DB migrations');
$check(str_contains($runner, "DB_NAME:-}" ) && str_contains($runner, 'cms_test_only'), 'Dedicated fixture database account is enforced');
$check(!str_contains($runner, 'docker ') && !str_contains($runner, 'compose ') && !str_contains($runner, '--destroy-fixtures'), 'No Docker/reset dependency');
$check(str_contains($http, 'http://boilerplate.test') && str_contains($http, 'https://boilerplate.test') && str_contains($http, '--max-redirs 0'), 'Local HTTP default and optional HTTPS, no redirects');
$check(str_contains($http, "127.0.0.1") && str_contains($http, '--resolve') && str_contains($http, '--noproxy'), 'VirtualHost requests pinned to local loopback');
$check(str_contains($http, 'CMS_TEST_TRUST_LOCAL_CERT') && str_contains($http, '--insecure'), 'TLS bypass is explicit and local-host only');
$check(str_contains($http, "check '/admin' 301") && str_contains($http, "check '/admin/' 200"), 'Apache admin DirectorySlash redirect is verified before login');
$check(str_contains($http, '"$base/admin/"|/admin/') && str_contains($http, 'unsafe or unexpected admin redirect'), 'Redirect destination is exact local admin URL only');
$check(!str_contains($http, '--location') && !str_contains($http, ' --location ') && !str_contains($http, ' -L '), 'HTTP checker does not automatically follow redirects');
$check(str_contains($guard, 'SELECT DATABASE()') && str_contains($guard, 'SELECT CURRENT_USER()') && str_contains($guard, 'cms_integration_marker'), 'Effective fixture DB identity + marker are validated');
$check(substr_count($schema, 'CREATE TABLE `') === 25, 'Synthetic schema has CMS tables including auth_sessions + fixture marker');
$check(!preg_match('/\bINSERT INTO\s+users\b/i', $fixture), 'Fixture contains no accounts or password hashes');
$check(str_contains($fixture, 'boilerplate-z8-synthetic-only-v1') && str_contains($schema, 'cms_integration_marker'), 'Fixture marker is created and seeded');
$version = (string)file_get_contents($root . '/public_html/incl/init.php');
preg_match("/define\('BOILERPLATE_VERSION', '([0-9.]+)'\)/", $version, $match);
$check(isset($match[1]) && version_compare($match[1], '2.4.4.60', '>='), 'Internal version retains native harness >= 2.4.4.60');
$check(file_get_contents($root . '/public_html/admin/assets/scss/admin.scss') === file_get_contents($root . '/public_html/admin/assets/css/admin.css'), 'Core SCSS and CSS remain synchronized');
echo "Native integration harness contract: {$pass} PASS / 0 FAIL\n";
