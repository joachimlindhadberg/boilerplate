<?php
/** Regression: per-install UI storage identity, no database, no writes to installed site. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/FirstInstall.php';
$installer = (string)file_get_contents($root . '/core/classes/FirstInstall.php');
$dashboard = (string)file_get_contents($root . '/public_html/admin/views/dashboard.php');
$script = (string)file_get_contents($root . '/public_html/admin/assets/js/admin.js');
$pass = 0; $fail = 0;
$check = static function(bool $passTest, string $label) use (&$pass, &$fail): void {
    echo ($passTest ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $passTest ? ++$pass : ++$fail;
};
$env = (new ReflectionMethod(FirstInstall::class, 'envText'));
$values = ['host'=>'localhost','port'=>3306,'name'=>'qa_synthetic','user'=>'qa_synthetic','pass'=>'synthetic_password'];
$textA = $env->invoke(null, $values, 'https://example.test');
$textB = $env->invoke(null, $values, 'https://example.test');
preg_match('/^CMS_SITE_INSTANCE_ID=\'([a-f0-9]{32})\'$/m', $textA, $matchA);
preg_match('/^CMS_SITE_INSTANCE_ID=\'([a-f0-9]{32})\'$/m', $textB, $matchB);
$check(isset($matchA[1],$matchB[1]) && $matchA[1] !== $matchB[1], 'New installations receive independent cryptographic instance IDs');
$check(str_contains($installer, '$env = self::envText($config, $site[\'site_url\']);') &&
    strpos($installer, '$env = self::envText(') < strpos($installer, '$handle = self::connect($config);'),
    'Instance ID generation and config validation precede any database DDL');
$check(str_contains($dashboard, "AppConfig::get('CMS_SITE_INSTANCE_ID'"), 'Dashboard reads instance ID from private configuration');
$check(str_contains($dashboard, "preg_match('/^[a-f0-9]{32}$/D'"), 'Only syntactically valid instance IDs reach markup');
$check(str_contains($dashboard, 'data-site-instance-id="<?= admin_e($siteInstanceId); ?>"'), 'Non-secret installation ID is HTML escaped');
$check(str_contains($script, 'dashboard-welcome.v4.instance-') && str_contains($script, ".user-' + accountId"), 'New preference namespace separates installation and user');
$check(str_contains($script, "instanceId !== null ? null") && str_contains($script, 'localStorage.getItem(legacyKey)'), 'Old browser state is only migrated for legacy installations');
$check(!str_contains($script, 'welcomeIntervalMs') && !str_contains($script, 'lastShownAt'), 'No timed reappearance after dismissal');
$fixture = $root . '/tests/fixtures/dashboard-welcome-browser-v2_4_4z20l.php';
$render = static fn(string $id): string => (string)shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' en 91 Test 1 1 unused 1 ' . escapeshellarg($id));
$check(str_contains($render($matchA[1]), 'data-site-instance-id="' . $matchA[1] . '"'), 'Rendered dashboard carries public instance namespace');
$check(str_contains($render('invalid'), 'data-site-instance-id=""'), 'Invalid IDs downgrade safely to legacy behavior');
echo "Dashboard Instance Scope QA1: $pass PASS / $fail FAIL\n";
exit($fail !== 0 ? 1 : 0);
