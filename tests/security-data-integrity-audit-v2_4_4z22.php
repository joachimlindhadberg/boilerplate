<?php

declare(strict_types=1);
/** V2.4.4z22 regression: never connects to a DB or invokes a web endpoint. */
$root = dirname(__DIR__);
$checks = 0;
$check = static function (bool $ok, string $description) use (&$checks): void {
    if (!$ok) { fwrite(STDERR, 'FAIL: z22 ' . $description . "\n"); exit(1); }
    ++$checks;
    echo 'PASS: z22 ' . $description . "\n";
};
$auditPath = $root . '/core/cli/security-audit.php';
$runnerPath = $root . '/tests/integration/run.sh';
$check(is_file($auditPath) && is_file($runnerPath), 'read-only auditor and runner exist');
$audit = (string)file_get_contents($auditPath);
$runner = (string)file_get_contents($runnerPath);
$check(str_contains($audit, "PHP_SAPI !== 'cli'"), 'audit restricted to CLI');
$check(str_contains($audit, "'/core/classes/AdminEscaper.php'") && str_contains($audit, "'/core/classes/AdminRequest.php'"), 'audit behavior probes load pure classes only');
$check(!preg_match('/\bnew\s+(?:Db|mysqli|Auth|MediaLibrary|SiteBackup)\s*\(/', $audit), 'audit never constructs database or stateful services');
$executable = implode('', array_map(
    static fn(array|string $token): string => is_array($token)
        ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true) ? ' ' : $token[1])
        : $token,
    token_get_all($audit)
));
$check(!preg_match('/\b(?:curl_exec|file_put_contents|unlink|rename|copy|mkdir|exec|shell_exec|passthru|system)\s*\(/', $executable), 'audit contains no write, subprocess or outgoing HTTP operations');
$check(!str_contains($audit, 'require_once $root . \'/public_html/incl/init.php\''), 'audit does not bootstrap application');
$check(str_contains($audit, 'never inspect the installed .env or user data'), 'audit excludes local private files');
$check(str_contains($runner, '    verify-security)') && str_contains($runner, '"$PHP_BIN" core/cli/security-audit.php'), 'dedicated shell mode exists');
$from = strpos($runner, '    verify-security)');
$to = strpos($runner, '    verify-fixture)');
$branch = $from !== false && $to !== false ? substr($runner, $from, $to - $from) : '';
$check($branch !== '' && !preg_match('~\b(?:normal_db|fixture_db|code_tests|http-smoke\.sh|curl|migrate\.php|db-smoke\.php|workflows\.php)\b~', $branch), 'security command excludes all database/HTTP/fixture branches');
$check(str_contains($runner, '    verify-fixture)') && str_contains($runner, '        fixture_db'), 'optional fixture remains separate and unchanged');
$check(is_file($root . '/docs/audits/SECURITY-DATA-INTEGRITY-AUDIT-V2.4.4z22.md'), 'prioritized security report shipped');
$check(is_file($root . '/docs/releases/SECURITY-DATA-INTEGRITY-AUDIT-V2.4.4z22.md'), 'release note shipped');
$check(preg_match("~BOILERPLATE_VERSION\x27, \x27([0-9.]+)\x27~", (string)file_get_contents($root . '/public_html/incl/init.php'), $versionMatch) === 1 && version_compare($versionMatch[1], '2.4.4.108', '>='), 'version metadata bumped');
$check(file_get_contents($root . '/public_html/admin/assets/css/admin.css') === file_get_contents($root . '/public_html/admin/assets/scss/admin.scss'), 'Core SCSS and CSS unchanged and identical');
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($auditPath) . ' --json 2>&1';
$output = [];
$status = -1;
exec($cmd, $output, $status);
$decoded = json_decode(implode("\n", $output), true);
$check($status === 0 && is_array($decoded), 'source audit exits successfully and outputs valid JSON');
$check(($decoded['summary']['failed'] ?? -1) === 0 && ($decoded['summary']['passed'] ?? 0) >= 55, 'at least 55 isolated audit conditions pass');
$check(count(array_unique(array_column($decoded['checks'] ?? [], 'category'))) >= 7, 'coverage spans seven security/integrity categories');
$check(($decoded['summary']['scope'] ?? null) === 'source-only; no DB / HTTP / fixture / mutation', 'machine-readable scope explicitly states limitations');
echo "Security/Data Integrity z22 regression: {$checks} PASS / 0 FAIL\n";
