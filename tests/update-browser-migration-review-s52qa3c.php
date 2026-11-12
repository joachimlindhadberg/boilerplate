<?php

declare(strict_types=1);
/** S5.2 QA3c QA1: no MySQL, no HTTP install, synthetic official ZIP review only. */
if (PHP_SAPI !== 'cli' || count($argv) !== 2 || !is_dir($argv[1])) {
    fwrite(STDERR, "Usage: php update-browser-migration-review-s52qa3c.php <candidate-source-root>\n");
    exit(2);
}
$source = realpath($argv[1]);
if (!is_string($source)) exit(2);
foreach (['core/classes/UpdateReleaseVersion.php', 'core/classes/UpdatePackageReview.php',
          'core/classes/UpdateRetiredFilePlan.php', 'core/classes/UpdateDatabaseReleasePreflight.php'] as $path) {
    require_once $source . '/' . $path;
}
// In local CI without ext-zip, provide a synthetic JSON-backed ZIP fixture.
// The real updater and real Mac QA must still use the native ZipArchive extension.
if (!class_exists('ZipArchive')) {
    class ZipArchive
    {
        public const CREATE = 1;
        public const OVERWRITE = 8;
        public const RDONLY = 16;
        public const OPSYS_UNIX = 3;
        public int $numFiles = 0;
        private array $members = [];
        private string $filename = '';
        private bool $writing = false;
        public function open(string $path, int $flags = 0): bool {
            $this->filename = $path;
            $this->writing = ($flags & (self::CREATE | self::OVERWRITE)) !== 0;
            if ($this->writing) { $this->members = []; $this->numFiles = 0; return true; }
            $v = json_decode((string)@file_get_contents($path), true);
            if (!is_array($v) || !is_array($v['members'] ?? null)) return false;
            $this->members = $v['members']; $this->numFiles = count($this->members);
            return true;
        }
        public function addFromString(string $name, string $bytes): bool {
            if (!$this->writing) return false;
            $this->members[] = ['name' => $name, 'data' => base64_encode($bytes), 'attributes' => 0100644 << 16];
            $this->numFiles++; return true;
        }
        public function setExternalAttributesName(string $name, int $os, int $attrs): bool {
            foreach ($this->members as &$item) if ($item['name'] === $name) { $item['attributes'] = $attrs; return true; }
            return false;
        }
        public function statIndex(int $i): array|false {
            if (!isset($this->members[$i])) return false;
            $v = $this->members[$i]; $n = strlen((string)base64_decode($v['data'], true));
            return ['name' => $v['name'], 'size' => $n, 'comp_size' => $n, 'encryption_method' => 0];
        }
        public function getFromIndex(int $i, int $limit = 0): string|false {
            if (!isset($this->members[$i])) return false;
            $v = base64_decode($this->members[$i]['data'], true);
            return $limit ? substr((string)$v, 0, $limit) : $v;
        }
        public function getExternalAttributesIndex(int $i, int &$os, int &$attrs): bool {
            if (!isset($this->members[$i])) return false;
            $os = self::OPSYS_UNIX; $attrs = $this->members[$i]['attributes']; return true;
        }
        public function close(): bool {
            if ($this->writing) file_put_contents($this->filename, json_encode(['members' => $this->members], JSON_THROW_ON_ERROR));
            return true;
        }
    }
}
$passed = 0;
$assert = static function (bool $truth, string $label) use (&$passed): void {
    if (!$truth) throw new RuntimeException('FAIL: ' . $label);
    $passed++;
    echo 'PASS: ' . $label . "\n";
};
$base = sys_get_temp_dir() . '/bp-s52qa3c-' . bin2hex(random_bytes(12));
$root = $base . '/installed';
$paths = ['public_html/incl', 'core/classes', 'database/updates',
          'database/migrations', 'database/install', 'modules/forms/migrations'];
foreach ($paths as $p) {
    if (!mkdir($root . '/' . $p, 0700, true) && !is_dir($root . '/' . $p)) {
        throw new RuntimeException('Cannot create QA fixture.');
    }
}
$old = 'database/updates/20261013_000_core_schema.php';
$mod = 'modules/forms/migrations/20261013_100_forms_schema.php';
$new = 'database/updates/20261105_000_page_access.php';
$sql = 'database/migrations/20261012_history.sql';
$baseSql = 'database/install/empty-schema.sql';
$oldCode = '<?php return static function (Db $db): void { $db->query("CREATE TABLE IF NOT EXISTS qa (id INT)"); };';
$sideEffect = $base . '/migration-was-executed';
$newCode = '<?php return static function (Db $db): void { file_put_contents(' . var_export($sideEffect, true) . ', "unacceptable"); };';
$original = [
    'public_html/incl/init.php' => "<?php define('BOILERPLATE_VERSION', '2.5.1-beta.9');",
    $old => $oldCode, $mod => $oldCode,
    $sql => 'CREATE TABLE old_history(id INT);',
    $baseSql => 'CREATE TABLE old_baseline(id INT);',
];
foreach ($original as $path => $data) {
    $file = $root . '/' . $path;
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0700, true);
    file_put_contents($file, $data);
}
$required = [
    'public_html/index.php' => '<?php',
    'public_html/admin/index.php' => '<?php',
    'public_html/.htaccess' => 'Options -Indexes',
    'public_html/admin/.htaccess' => 'Options -Indexes',
    'public_html/uploads/.htaccess' => 'Options -Indexes',
    'core/classes/MigrationRunner.php' => '<?php',
    'docs/deployment/UPDATING.md' => '# Synthetic update',
    'core/update-retired-files.json' => '{"schema":1,"retire":[]}',
];
$target = array_merge($original, $required);
$target['public_html/incl/init.php'] = "<?php define('BOILERPLATE_VERSION', '2.5.1');";
$target[$baseSql] = 'CREATE TABLE new_baseline(id INT);';
$target[$new] = $newCode;
$zipFile = $base . '/candidate.zip';
$makeZip = static function (array $sourceFiles) use ($zipFile): string {
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create QA ZIP.');
    }
    foreach ($sourceFiles as $path => $data) {
        $name = 'boilerplate/' . $path;
        if (!$zip->addFromString($name, $data)
            || !$zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
            throw new RuntimeException('Cannot write QA ZIP member: ' . $path);
        }
    }
    $zip->close();
    return (string)hash_file('sha256', $zipFile);
};
$states = [
    ['id' => 'core:20261013_000_core_schema', 'state' => 'applied'],
    ['id' => 'module:forms:20261013_100_forms_schema', 'state' => 'applied'],
];
$packageReview = new UpdatePackageReview($root);
$dbReview = new UpdateDatabaseReleasePreflight($root);
$review = static function (array $sourceFiles) use ($makeZip, $zipFile, $packageReview, $dbReview, $states): array {
    $digest = $makeZip($sourceFiles);
    return [$packageReview->inspect($zipFile, filesize($zipFile)),
        $dbReview->inspect($zipFile, $digest, $states)];
};
try {
    [$p, $d] = $review($target);
    $assert(($p['status'] ?? null) === 'review' && ($p['new_migrations'] ?? -1) === 1,
        'Migration-bearing release is reviewable rather than silently rejected');
    $assert(($d['status'] ?? null) === 'review' && ($d['install_ready'] ?? true) === false
        && ($d['new_migrations'] ?? null) === ['core:20261105_000_page_access'],
        'Database release preflight identifies migration but never authorizes installation');
    $assert(!file_exists($sideEffect), 'Preflight did not execute migration PHP');

    $changed = $target;
    unset($changed[$new]);
    [$p, $d] = $review($changed);
    $assert(($p['status'] ?? null) === 'blocked', 'Baseline-only change cannot bypass migration requirement');
    $assert(($d['status'] ?? null) === 'blocked', 'Database preflight blocks baseline-only change');

    $changed = $target;
    $changed[$old] .= ' // tampered';
    [$p, $d] = $review($changed);
    $assert(($p['status'] ?? null) === 'blocked', 'Altered historical PHP migration blocks ZIP review');
    $assert(($d['status'] ?? null) === 'blocked', 'Altered historical PHP migration blocks DB preflight');

    $changed = $target;
    $changed[$sql] .= ' DROP TABLE old_history;';
    [$p, $d] = $review($changed);
    $assert(($p['status'] ?? null) === 'blocked', 'Altered historical SQL source blocks ZIP review');
    $assert(($d['status'] ?? null) === 'blocked', 'Altered historical SQL source blocks DB preflight');

    $changed = $target;
    unset($changed[$baseSql]);
    [$p, $d] = $review($changed);
    $assert(($p['status'] ?? null) === 'blocked', 'Missing fresh-install baseline blocks ZIP review');
    $assert(($d['status'] ?? null) === 'blocked', 'Missing fresh-install baseline blocks DB preflight');

    $changed = $target;
    $changed['database/migrations/20261112_extra.sql'] = 'CREATE TABLE extra(id INT);';
    [$p, $d] = $review($changed);
    $assert(($p['status'] ?? null) === 'blocked', 'Added historical SQL source remains forbidden');
    $assert(($d['status'] ?? null) === 'blocked', 'Extra historical SQL source blocks DB preflight');

    $changed = $target;
    $changed[$baseSql] = $original[$baseSql];
    [$p, $d] = $review($changed);
    $assert(($p['status'] ?? null) === 'review' && ($d['status'] ?? null) === 'review',
        'New migration with unchanged baseline remains review-only');

    $changed = $target;
    $changed[$new] = '<?php invalid PHP';
    [, $d] = $review($changed);
    $assert(($d['status'] ?? null) === 'blocked', 'Malformed migration rejected without execution');

    $installer = file_get_contents($source . '/core/classes/UpdateOneClickInstaller.php');
    $controller = file_get_contents($source . '/public_html/admin/tools.php');
    $assert(is_string($installer)
        && str_contains($installer, "(\$review['new_migrations'] ?? -1) !== 0"),
        'Actual One-Click installer still blocks all new migrations');
    $assert(is_string($controller) && str_contains($controller, 'admin_verify_csrf')
        && str_contains($controller, "'install_official_update'"),
        'Real browser controller retains CSRF-protected official update action');
    $assert(!file_exists($sideEffect), 'No database migration code executed during any QA3c case');
    echo "RESULT: S5.2 QA3c browser release review: {$passed} PASS, 0 FAIL\n";
} finally {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) {
        if ($item->isDir()) rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($base);
}
