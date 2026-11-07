<?php

declare(strict_types=1);

/** F4a synthetic read-only QA; NEVER touches MySQL or an installed CMS. */
if (PHP_SAPI !== 'cli') exit(2);

$nativeZip = class_exists('ZipArchive');
if (!$nativeZip) {
    // The production class still requires native ZipArchive. This adapter is
    // limited to this isolated test, mirroring the existing CI ZIP fixtures.
    class ZipArchive
    {
        public const RDONLY = 16;
        public const OPSYS_UNIX = 3;
        public int $numFiles = 0;
        private array $entries = [];
        public function open(string $path, int $flags = 0): bool
        {
            $data = json_decode((string)file_get_contents($path), true);
            if (!is_array($data) || !is_array($data['entries'] ?? null)) return false;
            $this->entries = $data['entries'];
            $this->numFiles = count($this->entries);
            return true;
        }
        public function statIndex(int $i): array|false
        {
            if (!isset($this->entries[$i])) return false;
            $data = base64_decode((string)$this->entries[$i]['data'], true);
            return ['name' => $this->entries[$i]['name'], 'size' => strlen((string)$data),
                'comp_size' => strlen((string)$data), 'encryption_method' => 0];
        }
        public function getFromIndex(int $i, int $limit = 0): string|false
        {
            if (!isset($this->entries[$i])) return false;
            $data = base64_decode((string)$this->entries[$i]['data'], true);
            return $limit ? substr((string)$data, 0, $limit) : $data;
        }
        public function getExternalAttributesIndex(int $i, int &$opsys, int &$attributes): bool
        {
            if (!isset($this->entries[$i])) return false;
            $opsys = self::OPSYS_UNIX;
            $attributes = (($this->entries[$i]['symlink'] ?? false) ? 0120777 : 0100644) << 16;
            return true;
        }
        public function close(): bool { return true; }
    }
}
require_once dirname(__DIR__) . '/core/classes/UpdatePackageReview.php';
require_once dirname(__DIR__) . '/core/classes/UpdateDatabaseReleasePreflight.php';

$passed = 0;
$failed = 0;
$check = static function (bool $pass, string $description) use (&$passed, &$failed): void {
    if ($pass) { ++$passed; echo 'PASS: ' . $description . "\n"; }
    else { ++$failed; echo 'FAIL: ' . $description . "\n"; }
};

$base = sys_get_temp_dir() . '/bp-f4a-qa1-' . bin2hex(random_bytes(10));
if (!mkdir($base . '/installed/database/updates', 0700, true)
    || !mkdir($base . '/installed/modules/forms/migrations', 0700, true)
    || !mkdir($base . '/installed/database/migrations', 0700, true)
    || !mkdir($base . '/installed/database/install', 0700, true)) {
    throw new RuntimeException('Cannot create temporary QA fixture.');
}
$root = $base . '/installed';
$src = '<?php declare(strict_types=1); return static function (Db $db): void { $db->query("CREATE TABLE IF NOT EXISTS qa (id INT)"); };';
$oldCore = 'database/updates/20261013_000_core_schema.php';
$oldModule = 'modules/forms/migrations/20261013_100_forms_schema.php';
$sql = 'database/migrations/20261012_activity_log.sql';
$schema = 'database/install/empty-schema.sql';
$installed = [
    $oldCore => $src,
    $oldModule => $src,
    $sql => 'CREATE TABLE historical (id INT);',
    $schema => 'CREATE TABLE first_install (id INT);',
];
foreach ($installed as $relative => $content) {
    if (file_put_contents($root . '/' . $relative, $content) !== strlen($content)) {
        throw new RuntimeException('Cannot write synthetic fixture.');
    }
}
$states = [
    ['id' => 'core:20261013_000_core_schema', 'state' => 'applied'],
    ['id' => 'module:forms:20261013_100_forms_schema', 'state' => 'applied'],
];
$newPath = 'database/updates/20261108_000_new_feature.php';
$sideEffect = $base . '/should-not-exist.txt';
$newSource = '<?php declare(strict_types=1); return static function (Db $db): void { file_put_contents(' . var_export($sideEffect, true) . ', "DANGER"); };';
$path = $base . '/candidate.zip';

// zipCandidate accepts an *ordered* list, so duplicate ZIP names are testable.
$zipCandidate = static function (array $files) use ($path, $nativeZip): string {
    if ($nativeZip) {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot open test ZIP.');
        }
        foreach ($files as $item) {
            $name = $item['name'];
            if (!$zip->addFromString($name, $item['content'])
                || !$zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX,
                    (($item['symlink'] ?? false) ? 0120777 : 0100644) << 16)) {
                throw new RuntimeException('Cannot create test ZIP member.');
            }
        }
        $zip->close();
    } else {
        $entries = [];
        foreach ($files as $item) {
            $entries[] = ['name' => $item['name'], 'data' => base64_encode($item['content']),
                'symlink' => (bool)($item['symlink'] ?? false)];
        }
        file_put_contents($path, json_encode(['entries' => $entries], JSON_THROW_ON_ERROR));
    }
    return hash_file('sha256', $path);
};
$toZip = static function (array $source): array {
    $out = [];
    foreach ($source as $name => $content) {
        $out[] = ['name' => 'boilerplate/' . $name, 'content' => $content];
    }
    return $out;
};
$preflight = new UpdateDatabaseReleasePreflight($root);
$review = static function (array $files, ?array $status = null, ?string $sha = null) use ($zipCandidate, $toZip, $preflight, $states, $path): array {
    $actual = $zipCandidate($toZip($files));
    return $preflight->inspect($path, $sha ?? $actual, $status ?? $states);
};
try {
    $r = $review($installed);
    $check($r['status'] === 'review' && $r['reason'] === 'no_new_migrations'
        && $r['new_migrations'] === [] && $r['install_ready'] === false,
        'Unchanged histories remain review-only, never install-ready');
    $check($r['existing_migrations'] === 2, 'Both installed core and module migrations counted');
    $r = $review($installed + [$newPath => $newSource]);
    $check($r['status'] === 'review' && $r['reason'] === 'new_migrations_require_restore_proof'
        && $r['new_migrations'] === ['core:20261108_000_new_feature']
        && $r['install_ready'] === false, 'New core migration identified without authorizing execution');
    $check(!file_exists($sideEffect), 'New PHP migration is not executed during preflight');
    $r = $review($installed + ['modules/forms/migrations/20261108_010_optional.php' => $src]);
    $check($r['status'] === 'review'
        && $r['new_migrations'] === ['module:forms:20261108_010_optional'],
        'New module migration namespace is recognized');
    $changed = $installed;
    $changed[$oldCore] .= ' //changed';
    $check($review($changed)['reason'] === 'historical_php_migrations', 'Changed core migration blocks');
    $changed = $installed;
    $changed[$oldModule] .= ' //changed';
    $check($review($changed)['reason'] === 'historical_php_migrations', 'Changed module migration blocks');
    $changed = $installed;
    unset($changed[$oldCore]);
    $check($review($changed)['reason'] === 'historical_php_migrations', 'Missing historical migration blocks');
    $changed = $installed;
    $changed[$sql] .= '\nDROP TABLE historical;';
    $check($review($changed)['reason'] === 'historical_sql_sources', 'Changed historical SQL source blocks');
    $changed = $installed;
    $changed['database/migrations/20261108_untracked.sql'] = 'SELECT 1;';
    $check($review($changed)['reason'] === 'historical_sql_sources', 'New untracked SQL source blocks');
    $changed = $installed;
    unset($changed[$schema]);
    $check($review($changed)['reason'] === 'historical_sql_sources', 'Missing fresh-install schema blocks');
    $changed = $installed;
    $changed[$newPath] = '<?php return static function (Db $db): void { invalid PHP';
    $check($review($changed)['reason'] === 'migration_php_syntax', 'Malformed new PHP migration is refused');
    $changed = $installed;
    $changed[$newPath] = '<?php return 123;';
    $check($review($changed)['reason'] === 'migration_php_format', 'Non-closure migration format is refused');
    $pending = $states;
    $pending[0]['state'] = 'pending';
    $check($review($installed, $pending)['reason'] === 'installed_migration_status', 'Pending installed migration blocks');
    $mismatch = $states;
    $mismatch[1]['state'] = 'checksum-mismatch';
    $check($review($installed, $mismatch)['reason'] === 'installed_migration_status', 'Checksum mismatch blocks');
    $check($review($installed, [$states[0]])['reason'] === 'installed_migration_status',
        'Incomplete database status snapshot blocks');
    $unknown = $states;
    $unknown[] = ['id' => 'module:obsolete:20260101_missing', 'state' => 'missing-file'];
    $check($review($installed, $unknown)['reason'] === 'installed_migration_status',
        'Unrecognized database migration history blocks');
    $check($review($installed, $states, str_repeat('a', 64))['reason'] === 'archive_identity',
        'Release ZIP digest mismatch blocks');
    $changed = $installed;
    $changed['database/updates/invalid-name.php'] = $src;
    $check($review($changed)['reason'] === 'preflight_failed', 'Invalid migration path blocks');
    $changed = $installed;
    $changed[$newPath] = str_repeat('A', 1048577);
    $check($review($changed)['reason'] === 'preflight_failed', 'Oversized new migration blocks');
    $candidate = $toZip($installed);
    $candidate[] = ['name' => 'boilerplate/database/updates/20261013_000_CORE_SCHEMA.php', 'content' => $src];
    $sha = $zipCandidate($candidate);
    $check($preflight->inspect($path, $sha, $states)['reason'] === 'preflight_failed',
        'Duplicate archive member blocks');
    $candidate = $toZip($installed);
    $candidate[] = ['name' => 'boilerplate/../private.php', 'content' => 'secret'];
    $sha = $zipCandidate($candidate);
    $check($preflight->inspect($path, $sha, $states)['reason'] === 'preflight_failed',
        'Traversal member blocks');
    $candidate = $toZip($installed);
    $candidate[] = ['name' => 'boilerplate/' . $newPath, 'content' => $src, 'symlink' => true];
    $sha = $zipCandidate($candidate);
    $check($preflight->inspect($path, $sha, $states)['reason'] === 'preflight_failed',
        'Symlink archive member blocks');
    $check(!file_exists($sideEffect), 'No migration side effects after all fault cases');
    $check(!file_exists($root . '/storage'), 'Preflight did not create CMS recovery storage');
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $member) {
        if ($member->isDir()) rmdir($member->getPathname());
        else unlink($member->getPathname());
    }
    rmdir($base);
}
echo "Database Release Preflight F4a QA1: {$passed} PASS / {$failed} FAIL (synthetic only; no DB, CMS update, or Git writes)\n";
exit($failed ? 1 : 0);
