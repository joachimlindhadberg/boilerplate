<?php

declare(strict_types=1);

/**
 * Module asset version guard QA1.
 * Real ModuleManager::resolvePublicScript() with filesystem-only fixtures:
 * no DB, HTTP, module PHP loading, or writes outside a temporary directory.
 */
require_once __DIR__ . '/../core/classes/ModuleManager.php';

$results = [];
$check = static function (bool $passed, string $label) use (&$results): void {
    $results[] = $passed;
    echo ($passed ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
};

$root = sys_get_temp_dir() . '/bp-module-assets-' . bin2hex(random_bytes(6));
$moduleDir = $root . '/example';
$assets = $moduleDir . '/assets';
if (!mkdir($assets, 0700, true)) {
    throw new RuntimeException('Could not create isolated fixture');
}
file_put_contents($assets . '/example.js', 'console.log("fixture");');
file_put_contents($root . '/outside.js', 'console.log("outside");');

$manager = (new ReflectionClass(ModuleManager::class))->newInstanceWithoutConstructor();
$set = static function (string $name, mixed $value) use ($manager): void {
    (new ReflectionProperty(ModuleManager::class, $name))->setValue($manager, $value);
};
$module = [
    'slug' => 'example', 'version' => '1.0.2', 'valid' => true,
    'requires' => '', 'requires_php' => '',
];
$row = ['module_slug' => 'example', 'is_active' => 1, 'installed_version' => '1.0.2'];
$set('modulesPath', $root);
$set('discovered', ['example' => $module]);
$set('registeredRows', ['example' => $row]);

try {
    $asset = $manager->resolvePublicScript('example', 'example.js');
    $check(is_array($asset) && $asset['path'] === realpath($assets . '/example.js'), 'Matching active module serves its local JavaScript');
    $check(is_array($asset) && $asset['mime'] === 'application/javascript; charset=UTF-8', 'JavaScript MIME type remains controlled by Core');

    $row['installed_version'] = '1.0.1';
    $set('registeredRows', ['example' => $row]);
    $check($manager->resolvePublicScript('example', 'example.js') === null, 'Pending module upgrade blocks unconfirmed JavaScript');

    $row['installed_version'] = '1.0.3';
    $set('registeredRows', ['example' => $row]);
    $check($manager->resolvePublicScript('example', 'example.js') === null, 'Module downgrade mismatch blocks JavaScript');

    $row['installed_version'] = '1.0.2';
    $set('registeredRows', ['example' => $row]);
    $module['version'] = '1.0.3-beta.1';
    $set('discovered', ['example' => $module]);
    $check($manager->resolvePublicScript('example', 'example.js') === null, 'Prerelease version mismatch blocks JavaScript');

    $module['version'] = '1.0.2';
    $set('discovered', ['example' => $module]);
    $row['is_active'] = 0;
    $set('registeredRows', ['example' => $row]);
    $check($manager->resolvePublicScript('example', 'example.js') === null, 'Inactive module does not serve JavaScript');

    $row['is_active'] = 1;
    $set('registeredRows', ['example' => $row]);
    $module['valid'] = false;
    $set('discovered', ['example' => $module]);
    $check($manager->resolvePublicScript('example', 'example.js') === null, 'Invalid manifest does not serve JavaScript');

    $module['valid'] = true;
    $set('discovered', ['example' => $module]);
    $check($manager->resolvePublicScript('../example', 'example.js') === null, 'Unsafe module slug is rejected');
    $check($manager->resolvePublicScript('example', '../outside.js') === null, 'Traversal path is rejected');
    $check($manager->resolvePublicScript('example', 'example.php') === null, 'PHP files are not exposed as module assets');

    if (function_exists('symlink') && symlink($root . '/outside.js', $assets . '/outside.js')) {
        $check($manager->resolvePublicScript('example', 'outside.js') === null, 'Symlink escaping asset root is rejected');
    } else {
        echo 'SKIP: Symlink test unavailable on this filesystem' . PHP_EOL;
    }

    $row['installed_version'] = '';
    $set('registeredRows', ['example' => $row]);
    $check($manager->resolvePublicScript('example', 'example.js') !== null, 'Legacy empty installed version follows existing PHP boot behavior');

} finally {
    @unlink($assets . '/outside.js');
    @unlink($assets . '/example.js');
    @unlink($root . '/outside.js');
    @rmdir($assets);
    @rmdir($moduleDir);
    @rmdir($root);
}
$failures = count(array_filter($results, static fn(bool $passed): bool => !$passed));
echo 'RESULT: ' . (count($results) - $failures) . ' PASS / ' . $failures . ' FAIL (filesystem-only, NO MySQL/HTTP)' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
