<?php

declare(strict_types=1);

/**
 * Read-only file-only update policy regression. Uses a private disposable fixture;
 * no HTTP, production installation, MySQL, or active database is accessed.
 */
if (PHP_SAPI !== 'cli') exit(2);

$nativeZip = class_exists('ZipArchive');
if (!$nativeZip) {
    /** In-memory ZIP adapter for hosts where PHP zip is absent (e.g. CI). */
    class ZipArchive
    {
        public const RDONLY = 16;
        public const OPSYS_UNIX = 3;
        public int $numFiles = 0;
        private array $entries = [];

        public function open(string $path, int $flags = 0): bool
        {
            $input = json_decode((string)file_get_contents($path), true);
            if (!is_array($input) || !is_array($input['entries'] ?? null)) return false;
            $this->entries = $input['entries'];
            $this->numFiles = count($this->entries);
            return true;
        }
        public function statIndex(int $index): array|false
        {
            if (!isset($this->entries[$index])) return false;
            $bytes = base64_decode((string)$this->entries[$index]['data'], true);
            return ['name' => $this->entries[$index]['name'], 'size' => strlen($bytes),
                'comp_size' => strlen($bytes), 'encryption_method' => 0];
        }
        public function getFromIndex(int $index, int $length = 0): string|false
        {
            if (!isset($this->entries[$index])) return false;
            $bytes = base64_decode((string)$this->entries[$index]['data'], true);
            return $length > 0 ? substr($bytes, 0, $length) : $bytes;
        }
        public function getExternalAttributesIndex(int $index, int &$opsys, int &$attributes): bool
        {
            if (!isset($this->entries[$index])) return false;
            $opsys = self::OPSYS_UNIX;
            $attributes = 0100644 << 16;
            return true;
        }
        public function close(): bool { return true; }
    }
}

$root = dirname(__DIR__);
require_once $root . '/core/classes/UpdateReleaseVersion.php';
require_once $root . '/core/classes/UpdateRetiredFilePlan.php';
require_once $root . '/core/classes/UpdatePackageReview.php';

$checks = 0;
function qa(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    $checks++;
    echo 'PASS: ' . $description . "\n";
}
function fileWrite(string $path, string $content): void
{
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) {
        throw new RuntimeException('Cannot create fixture directory.');
    }
    if (file_put_contents($path, $content) === false) throw new RuntimeException('Cannot create fixture file.');
}
function zipCandidate(string $path, array $files): void
{
    global $nativeZip;
    if ($nativeZip) {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create fixture ZIP.');
        }
        foreach ($files as $relative => $content) {
            $name = 'boilerplate/' . $relative;
            if (!$zip->addFromString($name, $content)
                || !$zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
                throw new RuntimeException('Cannot prepare Unix ZIP member.');
            }
        }
        $zip->close();
    } else {
        $entries = [];
        foreach ($files as $relative => $content) {
            $entries[] = ['name' => 'boilerplate/' . $relative, 'data' => base64_encode($content)];
        }
        fileWrite($path, json_encode(['entries' => $entries], JSON_THROW_ON_ERROR));
    }
}
function removeFixture(string $folder): void
{
    if (!is_dir($folder) || is_link($folder)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($folder);
}

$fixture = sys_get_temp_dir() . '/bp-sql-source-qa-' . bin2hex(random_bytes(10));
if (!mkdir($fixture, 0700)) throw new RuntimeException('Cannot create isolated fixture.');
try {
    $installed = [
        'public_html/incl/init.php' => "<?php\ndefine('BOILERPLATE_VERSION', '2.5.1-beta.6');\n",
        'database/updates/20261013_000_core_schema.php' => "<?php\nreturn [];\n",
        'database/migrations/20261012_example.sql' => "CREATE TABLE example (id INT);\n",
        'database/install/empty-schema.sql' => "CREATE TABLE base (id INT);\n",
    ];
    foreach ($installed as $relative => $content) fileWrite($fixture . '/' . $relative, $content);
    $release = $installed;
    $release['public_html/incl/init.php'] = "<?php\ndefine('BOILERPLATE_VERSION', '2.5.1-beta.7');\n";
    foreach ([
        'public_html/index.php', 'public_html/admin/index.php',
        'public_html/.htaccess', 'public_html/admin/.htaccess', 'public_html/uploads/.htaccess',
        'core/classes/MigrationRunner.php', 'docs/deployment/UPDATING.md',
    ] as $file) $release[$file] = "test fixture\n";

    $reviewer = new UpdatePackageReview($fixture);
    $inspect = static function (array $candidate) use ($reviewer, $fixture): array {
        $path = $fixture . '/candidate.zip';
        zipCandidate($path, $candidate);
        return $reviewer->inspect($path, filesize($path));
    };
    $status = static function (array $result, string $label): ?string {
        foreach ($result['checks'] as $check) {
            if ($check['label'] === $label) return $check['status'];
        }
        return null;
    };

    $unchanged = $inspect($release);
    qa($unchanged['status'] === 'review', 'Unchanged historical SQL and baseline allow review');
    qa($status($unchanged, 'Database SQL sources') === 'good', 'Database SQL sources marked good');
    qa($unchanged['new_migrations'] === 0, 'Existing PHP migration counter stays unchanged');

    $modified = $release;
    $modified['database/migrations/20261012_example.sql'] .= '-- changed';
    $r = $inspect($modified);
    qa($r['status'] === 'blocked', 'Modified historical SQL blocks file-only update');
    qa($status($r, 'Database SQL sources') === 'blocked', 'Modified SQL reports blocking reason');

    $added = $release;
    $added['database/migrations/20261013_new.sql'] = 'ALTER TABLE example ADD column_name INT;';
    qa($inspect($added)['status'] === 'blocked', 'Added historical SQL blocks file-only update');

    $missing = $release;
    unset($missing['database/migrations/20261012_example.sql']);
    qa($inspect($missing)['status'] === 'blocked', 'Missing historical SQL blocks file-only update');

    $schemaChanged = $release;
    $schemaChanged['database/install/empty-schema.sql'] .= '-- changed';
    qa($inspect($schemaChanged)['status'] === 'blocked', 'Modified install baseline blocks file-only update');

    $schemaMissing = $release;
    unset($schemaMissing['database/install/empty-schema.sql']);
    qa($inspect($schemaMissing)['status'] === 'blocked', 'Missing install baseline blocks file-only update');

    $uppercase = $release;
    $uppercase['database/migrations/20261014_NEW.SQL'] = 'SELECT 1;';
    qa($inspect($uppercase)['status'] === 'blocked', 'Case-variant SQL source also blocked');

    $ordinary = $release;
    $ordinary['core/classes/Unrelated.php'] = '<?php // ordinary code change';
    qa($inspect($ordinary)['status'] === 'review', 'Ordinary code-only release remains supported');
    qa(file_get_contents($fixture . '/database/install/empty-schema.sql') === $installed['database/install/empty-schema.sql'],
        'No SQL or database modification during review');

    echo "RESULT: {$checks} PASS / 0 FAIL (" . ($nativeZip ? 'native ZipArchive' : 'isolated ZIP adapter') . ", NO MySQL)\n";
} finally {
    removeFixture($fixture);
}
