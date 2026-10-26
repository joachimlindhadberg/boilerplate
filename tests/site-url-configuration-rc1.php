<?php
/** DB-free RC1 regression: never connects to a real CMS database. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/classes/Autoload.php';

$checks = 0;
$expect = static function (bool $condition, string $label) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        fwrite(STDERR, 'FAIL URL RC1: ' . $label . PHP_EOL);
        exit(1);
    }
};

foreach ([
    'https://Example.dk/' => 'https://example.dk',
    'http://LOCALHOST:8080/' => 'http://localhost:8080',
    'https://example.dk:8443' => 'https://example.dk:8443',
] as $given => $expected) {
    $expect(SiteUrl::normalizeOrigin($given) === $expected, 'normalize ' . $given);
}
foreach ([
    '', 'ftp://example.dk', 'https://user@example.dk', 'https://user:pass@example.dk',
    'https://example.dk/subdirectory', 'https://example.dk/?q=1',
    'https://example.dk/#fragment', 'https://example.dk/x\r\nInjected: 1',
    'javascript:alert(1)', 'https://example.dk:65536', 'https://example.dk:0',
] as $bad) {
    $expect(SiteUrl::normalizeOrigin($bad) === null, 'reject unsafe origin: ' . $bad);
}

$cases = [
    [['https://db.test/', 'http://app.test', true], ['url'=>'https://db.test','source'=>'database']],
    [['http://db.test:8080', 'https://app.test', false], ['url'=>'http://db.test:8080','source'=>'database']],
    [['', 'https://app.test/', true], ['url'=>'https://app.test','source'=>'APP_URL']],
    [['ftp://bad.test', 'https://app.test/', true], ['url'=>'https://app.test','source'=>'APP_URL']],
    [[null, null, false, 'localhost:9000'], ['url'=>'http://localhost:9000','source'=>'request']],
    [[null, null, false, 'localhost:9000', true], ['url'=>'https://localhost:9000','source'=>'request']],
    [[null, null, false, 'user@attacker.test'], ['url'=>'http://localhost','source'=>'request']],
    [[null, null, false, 'attacker.test/path'], ['url'=>'http://localhost','source'=>'request']],
];
foreach ($cases as [$arguments, $expected]) {
    $expect(SiteUrl::choose(...$arguments) === $expected, 'source priority: '.json_encode($arguments));
}
foreach ([[null, null, true], ['http://unsafe/path', null, true]] as $arguments) {
    try {
        SiteUrl::choose(...$arguments);
        $expect(false, 'production must fail without a trusted URL');
    } catch (RuntimeException) {
        $expect(true, 'production rejects missing origin');
    }
}

// No Settings object means no database is opened. A future first-run wizard
// can rely on the fallback even before it installs the settings table.
$expect(SiteUrl::choose(null, 'https://install.example', true)['url'] === 'https://install.example', 'installer fallback');
$source = file_get_contents(dirname(__DIR__) . '/public_html/incl/init.php');
$expect(strpos($source, "\$settings = model('settings');") < strpos($source, 'AppConfig::enforceCanonicalRequest('), 'canonical check runs after Settings bootstrap');
$expect(str_contains(file_get_contents(dirname(__DIR__) . '/public_html/incl/helpers/url_helpers.php'), 'SiteUrl::origin('), 'frontend uses common URL resolver');

echo 'PASS: ' . $checks . ' URL Configuration RC1 checks (database-free)' . PHP_EOL;
