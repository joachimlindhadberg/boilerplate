<?php

declare(strict_types=1);

/** Read-only localization QA: php core/cli/translations.php */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ROOTPATH', dirname(__DIR__, 2));
require_once ROOTPATH . '/core/classes/Autoload.php';

if (count($argv) > 1) {
    fwrite(STDERR, "Usage: php core/cli/translations.php\n");
    exit(2);
}

$files = ['core' => ROOTPATH . '/core/lang/translations.json'];
foreach (glob(ROOTPATH . '/modules/*', GLOB_ONLYDIR) ?: [] as $directory) {
    if (is_file($directory . '/module.json')) {
        $files['module ' . basename($directory)] = $directory . '/lang/translations.json';
    }
}

$total = 0;
$failed = false;
foreach ($files as $name => $path) {
    $audit = TranslationCatalogValidator::inspect($path);
    $total += $audit['keys'];
    if ($audit['errors']) {
        $failed = true;
        echo "FAIL {$name}\n";
        foreach ($audit['errors'] as $error) echo "  - {$error}\n";
    } else {
        echo "PASS {$name}: {$audit['keys']} keys, 5 locales\n";
    }
}
echo 'Total: ' . $total . ' keys across ' . count($files) . " catalogs.\n";
exit($failed ? 1 : 0);
