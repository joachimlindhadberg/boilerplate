<?php

declare(strict_types=1);

/** Isolated deterministic QA: no GitHub, real database or live CMS is touched. */
$nativeZip = class_exists('ZipArchive', false);
if (!$nativeZip) {
class ZipArchive
{
    public const RDONLY = 16;
    public int $numFiles = 0;
    private array $files = [];
    public function open(string $path, int $flags = 0): bool
    {
        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || !is_array($data['files'] ?? null)) return false;
        $this->files = [];
        foreach ($data['files'] as $name => $content) $this->files[] = ['name' => $name, 'content' => $content];
        $this->numFiles = count($this->files);
        return true;
    }
    public function statIndex(int $i): array|false
    {
        if (!isset($this->files[$i])) return false;
        return ['name' => $this->files[$i]['name'], 'size' => strlen($this->files[$i]['content'])];
    }
    public function getFromIndex(int $i): string|false { return $this->files[$i]['content'] ?? false; }
    public function close(): bool { return true; }
}
}
final class UpdatePackageReview
{
    public const MAX_ARCHIVE_BYTES = 20971520;
    public static int $newMigrations = 0;
    public function __construct(private ?string $root = null) {}
    public function inspect(string $path, int $size): array
    {
        $record = json_decode((string)file_get_contents($this->root . '/storage/update-recovery/.official-stage.json'), true);
        return ['status' => 'review', 'target_version' => $record['version'],
            'new_migrations' => self::$newMigrations, 'files' => $record['files']];
    }
    public static function isSafeMember(string $name, bool $dir): bool
    {
        return str_starts_with($name, 'boilerplate/')
            && (bool)preg_match('~^boilerplate/(?:core|public_html|database|modules|docs)/[a-zA-Z0-9._/-]+$~D', $name)
            && !str_contains($name, '..') && !$dir;
    }
}
final class UpdateOfficialReleasePackage
{
    public function __construct(private ?string $root = null) {}
    public function preparedStatus(): array
    {
        return json_decode((string)file_get_contents($this->root . '/storage/update-recovery/.official-stage.json'), true);
    }
}
final class UpdateRecoveryBundle
{
    public function __construct(private ?string $root = null) {}
    public function create(): array { return ['id' => 'recovery-test-fixture']; }
    public function verify(string $id): array
    {
        if ($id !== 'recovery-test-fixture') throw new RuntimeException('Unexpected backup id.');
        return ['status' => 'verified_archives_restore_not_rehearsed'];
    }
    public function pruneAfterSuccessfulInstall(string $id): int
    {
        if ($id !== 'recovery-test-fixture') throw new RuntimeException('Unexpected backup id.');
        return 0;
    }
}
require dirname(__DIR__) . '/core/classes/UpdateReleaseVersion.php';
require dirname(__DIR__) . '/core/classes/UpdateOneClickInstaller.php';
require dirname(__DIR__) . '/core/classes/UpdateMaintenanceGate.php';

$checks = 0;
function check(bool $truth, string $label): void
{
    global $checks;
    if (!$truth) throw new RuntimeException('FAIL ' . $label);
    $checks++;
    echo "PASS $label\n";
}
function writeFixture(string $path, string $contents): void
{
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
    file_put_contents($path, $contents);
}
function fakeStage(string $root, string $version, array $files): string
{
    $vault = $root . '/storage/update-recovery';
    if (!is_dir($vault)) mkdir($vault, 0700, true);
    chmod($vault, 0700);
    if (class_exists('ZipArchive') && defined('ZipArchive::CREATE')) {
        // On macOS/Homebrew this exercises the REAL ZipArchive read path.
        $candidate = $vault . '/.fixture-candidate.zip';
        $zip = new ZipArchive();
        if ($zip->open($candidate, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create isolated native ZIP fixture.');
        }
        foreach ($files as $path => $bytes) $zip->addFromString('boilerplate/' . $path, $bytes);
        $zip->close();
        $json = (string)file_get_contents($candidate);
        unlink($candidate);
    } else {
        $json = json_encode(['version' => $version,
            'files' => array_combine(array_map(fn($p) => 'boilerplate/' . $p, array_keys($files)), array_values($files))], JSON_THROW_ON_ERROR);
    }
    $sha = hash('sha256', $json);
    writeFixture($vault . '/.official-release-' . $sha . '.zip', $json);
    writeFixture($vault . '/.official-stage.json', json_encode(['status' => 'prepared_for_review_only',
        'version' => $version, 'sha256' => $sha, 'files' => count($files)]));
    return $sha;
}
function deleteFixture(string $dir): void
{
    if (!is_dir($dir) || is_link($dir)) return;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iter as $item) {
        if ($item->isDir() && !$item->isLink()) rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($dir);
}
$root = sys_get_temp_dir() . '/bp-oneclick-qa-' . bin2hex(random_bytes(12));
mkdir($root, 0700);
try {
    $oldInit = "<?php\ndefine('BOILERPLATE_VERSION', '2.5.0');\n";
    $newInit = "<?php\ndefine('BOILERPLATE_VERSION', '2.5.1');\n";
    $oldFeature = "<?php\n// Fixture old feature.\n";
    $newFeature = "<?php\n// Fixture new feature.\n";
    writeFixture($root . '/public_html/incl/init.php', $oldInit);
    writeFixture($root . '/core/classes/Feature.php', $oldFeature);
    $installer = new UpdateOneClickInstaller($root);
    $sha = fakeStage($root, '2.5.1', [
        'public_html/incl/init.php' => $newInit,
        'core/classes/Feature.php' => $newFeature,
        'core/classes/Added.php' => "<?php\n// Added fixture.\n",
    ]);
    $gate1 = new UpdateMaintenanceGate($root);
    $gate2 = new UpdateMaintenanceGate($root);
    check($gate1->acquireReader(), 'reader lock admitted');
    check(!$gate2->acquireExclusive(), 'exclusive lock refused during reader');
    $gate1->release();
    check($gate2->acquireExclusive(), 'exclusive lease obtained after readers finished');
    $result = $installer->install('2.5.1', $sha);
    check($result['status'] === 'updated', 'fixture one-click code installation');
    check(file_get_contents($root . '/public_html/incl/init.php') === $newInit, 'target version written');
    check(file_get_contents($root . '/core/classes/Feature.php') === $newFeature, 'existing code replaced');
    check(is_file($root . '/core/classes/Added.php'), 'new code added');
    check(!$installer->hasInterruptedUpdate(), 'successful install has no pending journal');
    $gate2->release();

    UpdatePackageReview::$newMigrations = 1;
    $sha2 = fakeStage($root, '2.5.2', ['public_html/incl/init.php' => "<?php\ndefine('BOILERPLATE_VERSION', '2.5.2');\n"]);
    check($installer->install('2.5.2', $sha2)['status'] === 'unsupported_release', 'migration-containing release refused');
    check(file_get_contents($root . '/public_html/incl/init.php') === $newInit, 'no file mutation on migration refusal');
    UpdatePackageReview::$newMigrations = 0;

    $work = $root . '/storage/update-recovery/.one-click-pending-' . str_repeat('a', 32);
    mkdir($work, 0700);
    writeFixture($work . '/old/core/classes/Feature.php', $oldFeature);
    writeFixture($work . '/manifest.json', json_encode(['format' => 'boilerplate-one-click-journal-v1',
        'version' => '2.5.1', 'archive_sha256' => $sha, 'backup_id' => 'recovery-test-fixture',
        'plan' => [['path' => 'core/classes/Feature.php', 'old' => hash('sha256', $oldFeature),
            'new' => hash('sha256', $newFeature)] ]], JSON_THROW_ON_ERROR));
    check($installer->hasInterruptedUpdate(), 'pending journal detected');
    check($installer->restoreInterrupted()['status'] === 'restored', 'explicit recovery restores old file');
    check(file_get_contents($root . '/core/classes/Feature.php') === $oldFeature, 'restored content matches original');
    check(!$installer->hasInterruptedUpdate(), 'recovery clears journal');
    // The same existing code-only installer must also handle canonical beta versions.
    $previewInit = "<?php\ndefine('BOILERPLATE_VERSION', '2.5.3-beta.1');\n";
    $betaSha = fakeStage($root, '2.5.3-beta.1', [
        'public_html/incl/init.php' => $previewInit,
    ]);
    $betaGate = new UpdateMaintenanceGate($root);
    check($betaGate->acquireExclusive(), 'exclusive lease for beta fixture');
    check($installer->install('2.5.3-beta.1', $betaSha, 'beta')['status'] === 'updated', 'beta installation reuses code-only installer');
    check(file_get_contents($root . '/public_html/incl/init.php') === $previewInit, 'installed beta version is recorded exactly');
    $betaGate->release();
    echo "RESULT: $checks PASS / 0 FAIL (" . ($GLOBALS['nativeZip'] ? 'native ZipArchive' : 'stub ZipArchive') . ", synthetic backup, NO MySQL)\n";
} finally {
    deleteFixture($root);
}
