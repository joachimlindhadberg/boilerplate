<?php

declare(strict_types=1);

/** Release ZIP ships empty upload roots without ever bundling uploaded data. */
$root = dirname(__DIR__);
$script = (string)file_get_contents($root . '/tests/release/release_zip.py');
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
};

$assert(str_contains($script, "EMPTY_RUNTIME_DIRS = ('public_html/uploads',)"), 'Only the current upload root ships as a directory entry');
$assert(str_contains($script, "'uploads', 'storage', 'cache'"), 'Uploaded files and other runtime storage remain excluded from traversal');
$assert(str_contains($script, "entries.append((f'{TOP}{relative}/', p, True))"), 'Upload roots are ZIP directories, not files');
$assert(str_contains($script, "if p.is_symlink() or (p.exists() and not p.is_dir()):"), 'Unexpected upload symlinks and files are rejected');
$assert(str_contains($script, 'mtime = time.localtime((p if p.exists() else ROOT).stat().st_mtime)'), 'Empty upload roots can be virtual in a clean checkout');
$assert(str_contains($script, 'if rel in EMPTY_RUNTIME_DIRS:'), 'Repair leaves runtime upload folders and permissions untouched');
$assert(str_contains($script, "info.external_attr >> 16") && str_contains($script, 'DIR_MODE = stat.S_IFDIR | 0o755'), 'ZIP verifies canonical directory permissions');
$assert(str_contains($script, "EXCLUDED_FILES = {'.env',"), 'Credentials remain excluded');
$assert(str_contains($script, 'p.read_bytes()') && str_contains($script, "b'' if is_dir"), 'Upload-directory entries cannot embed user files');
$versionSource = (string)file_get_contents($root . '/public_html/incl/init.php');
$assert(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'/", $versionSource, $m) === 1
    && version_compare($m[1], '2.4.4.69', '>='), 'Release retains empty uploads fix (>= 2.4.4.69)');
echo "Release empty upload directories z12b: {$checks} PASS / 0 FAIL\n";
