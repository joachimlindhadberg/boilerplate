<?php
declare(strict_types=1);
/** Guard for the user's separately provisioned disposable integration DB. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(2); }
$required = [
    'APP_ENV' => 'testing',
    'DB_NAME' => 'boilerplate_integration_only',
    'DB_USER' => 'cms_test_only',
];
foreach ($required as $key => $expect) {
    if (getenv($key) !== $expect) {
        fwrite(STDERR, "FAIL: dedicated fixture environment variable {$key} mismatch\n");
        exit(2);
    }
}
if (!in_array(getenv('DB_HOST'), ['localhost', '127.0.0.1'], true) || trim((string)getenv('DB_PASS')) === '') {
    fwrite(STDERR, "FAIL: local fixture DB_HOST/DB_PASS required\n");
    exit(2);
}
define('ROOTPATH', dirname(__DIR__, 2));
require ROOTPATH . '/core/classes/Autoload.php';
try {
    $db = new Db();
    $schema = $db->fetchValue('SELECT DATABASE()');
    $user = (string)$db->fetchValue('SELECT CURRENT_USER()');
    if ($schema !== 'boilerplate_integration_only' || !str_starts_with($user, 'cms_test_only@')) {
        throw new RuntimeException('Effective MySQL database/user mismatch');
    }
    $exists = (int)$db->fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='cms_integration_marker'");
    if ($exists !== 1) throw new RuntimeException('Marker table absent');
    $marker = $db->fetchValue('SELECT token FROM cms_integration_marker WHERE id=1');
    if ($marker !== 'boilerplate-z8-synthetic-only-v1') {
        throw new RuntimeException('Fixture marker mismatch');
    }
    echo "PASS: database={$schema}, dedicated fixture account, marker verified\n";
    $db->close();
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: fixture environment is not safely isolated: ' . $error->getMessage() . "\n");
    exit(2);
}
