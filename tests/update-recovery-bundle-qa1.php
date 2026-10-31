<?php

declare(strict_types=1);

/** Synthetic only: no database, no network, no installed-site writes. */
require_once dirname(__DIR__) . '/core/classes/UpdateRecoveryCodeSnapshot.php';
require_once dirname(__DIR__) . '/core/classes/UpdateRecoveryDataArchive.php';
require_once dirname(__DIR__) . '/core/classes/UpdateRecoveryBundle.php';

$passed = 0; $failed = 0;
$check = static function (bool $ok, string $why) use (&$passed, &$failed): void {
    $ok ? ++$passed : ++$failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $why . "\n";
};
$throws = static function (callable $task): bool {
    try { $task(); return false; } catch (RuntimeException $e) { return true; }
};
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (new DirectoryIterator($path) as $item) {
        if (!$item->isDot()) $remove($item->getPathname());
    }
    @rmdir($path);
};
$base = sys_get_temp_dir() . '/cms-recovery-bundle-test-' . bin2hex(random_bytes(12));
if (!mkdir($base, 0700)) throw new RuntimeException('Fixture failed.');
$factory = static function (string $sql = 'CREATE TABLE test (id INT);', string $media = 'fixture picture'): string {
    $work = sys_get_temp_dir() . '/cms-backup-fixture-' . bin2hex(random_bytes(12));
    if (!mkdir($work, 0700)) throw new RuntimeException('Cannot create backup fixture.');
    $archive = sys_get_temp_dir() . '/cms-download-' . bin2hex(random_bytes(12));
    $manifest = [
        'format' => 'boilerplate-site-data-backup', 'version' => 1,
        'contains_sensitive_data' => true,
        'database_tables' => ['test' => 1], 'database_rows' => 1,
        'database_triggers' => 0,
        'database_sha256' => hash('sha256', $sql),
        'upload_files' => 1,
        'upload_sha256' => ['public_html/uploads/picture.jpg' => hash('sha256', $media)],
    ];
    file_put_contents($work . '/database.sql', $sql);
    file_put_contents($work . '/image.jpg', $media);
    file_put_contents($work . '/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    try {
        $tar = new PharData($work . '/site-data.tar');
        $tar->addFile($work . '/database.sql', 'database.sql');
        $tar->addFile($work . '/manifest.json', 'manifest.json');
        $tar->addFile($work . '/image.jpg', 'public_html/uploads/picture.jpg');
        unset($tar);
        $tar = new PharData($work . '/site-data.tar');
        $tar->compress(Phar::GZ);
        unset($tar);
        if (!rename($work . '/site-data.tar.gz', $archive)) throw new RuntimeException('Cannot move synthetic archive.');
        @chmod($archive, 0600);
    } finally {
        foreach (['site-data.tar', 'site-data.tar.gz', 'database.sql', 'manifest.json', 'image.jpg'] as $name) {
            @unlink($work . '/' . $name);
        }
        @rmdir($work);
    }
    return $archive;
};
try {
    foreach (['core/classes', 'public_html/incl', 'public_html/uploads', 'storage/logs', 'modules/custom'] as $dir) {
        if (!mkdir($base . '/' . $dir, 0700, true)) throw new RuntimeException('Fixture directory failed.');
    }
    file_put_contents($base . '/.env', 'LOCAL_PASSWORD=fixture-only');
    file_put_contents($base . '/core/classes/Sample.php', '<?php // fixture');
    file_put_contents($base . '/core/classes/Autoload.php', '<?php // fixture');
    file_put_contents($base . '/public_html/incl/init.php', '<?php // fixture');
    file_put_contents($base . '/public_html/uploads/media.jpg', 'should not be in code backup');
    file_put_contents($base . '/modules/custom/module.php', '<?php // custom');
    file_put_contents($base . '/storage/logs/debug.log', 'should not be in code backup');
    $validator = new UpdateRecoveryDataArchive();
    $fixture = $factory();
    copy($fixture, $base . '/synthetic.tar.gz');
    $valid = $validator->verify($base . '/synthetic.tar.gz');
    $check($valid['files'] === 1 && $valid['tables'] === 1 && $valid['triggers'] === 0, 'Synthetic SQL/media archive validated');
    // Phar may canonicalize the parent directory (notably macOS /var => /private/var).
    // A symlinked PARENT is not a symlinked archive file; verification must still work.
    $alias = rtrim(sys_get_temp_dir(), '/') . '/cms-phar-parent-alias-' . bin2hex(random_bytes(10));
    if (!symlink($base, $alias)) throw new RuntimeException('Cannot prepare canonical-path fixture.');
    $viaAlias = $validator->verify($alias . '/synthetic.tar.gz');
    $check($viaAlias['sha256'] === $valid['sha256'], 'Data verification accepts canonicalized Phar parent path');
    $codeViaAlias = (new UpdateRecoveryCodeSnapshot($base))->create();
    $codeAlias = rtrim(sys_get_temp_dir(), '/') . '/cms-phar-code-alias-' . bin2hex(random_bytes(10));
    if (!symlink(dirname($codeViaAlias['path']), $codeAlias)) throw new RuntimeException('Cannot prepare code alias fixture.');
    try {
        $viaAliasCode = (new UpdateRecoveryCodeSnapshot($base))->verify($codeAlias . '/code.tar.gz');
        $check($viaAliasCode['files'] === $codeViaAlias['files'], 'Code snapshot verification accepts canonicalized Phar parent path');
    } finally {
        @unlink($codeAlias);
        (new UpdateRecoveryCodeSnapshot($base))->discard($codeViaAlias['path']);
    }
    // Compressed code snapshots are verified through a private plain TAR;
    // PharData's direct gzip-entry reader is not used on PHP 8.5.
    $codeCheckRoot = new UpdateRecoveryCodeSnapshot($base);
    $codeCheck = $codeCheckRoot->create();
    $beforeChecks = glob(rtrim(sys_get_temp_dir(), '/') . '/cms-recovery-check-*');
    $verifiedCode = $codeCheckRoot->verify($codeCheck['path']);
    $check($verifiedCode['files'] === $codeCheck['files'] && $verifiedCode['has_env'],
        'Code archive verifies through bounded private uncompressed TAR');
    $afterChecks = glob(rtrim(sys_get_temp_dir(), '/') . '/cms-recovery-check-*');
    $check($beforeChecks === $afterChecks, 'Private verification TAR is removed after success');
    $badHeader = $base . '/not-really-gzip.tar.gz';
    file_put_contents($badHeader, 'not a valid gzip archive');
    $check($throws(static fn() => $codeCheckRoot->verify($badHeader)), 'Fake gzip code archive rejected');
    $rawCode = gzdecode(file_get_contents($codeCheck['path']));
    if (!is_string($rawCode)) throw new RuntimeException('Cannot decode synthetic code TAR fixture.');
    $tamperedCodeTar = $base . '/bad-code-manifest.tar';
    file_put_contents($tamperedCodeTar, $rawCode);
    $mutableCode = new PharData($tamperedCodeTar);
    $mutableCode->addFromString('manifest.json', 'not valid json');
    unset($mutableCode);
    $badCode = $base . '/bad-code-manifest.tar.gz';
    file_put_contents($badCode, gzencode(file_get_contents($tamperedCodeTar)));
    $check($throws(static fn() => $codeCheckRoot->verify($badCode)),
        'Invalid snapshot JSON rejected safely as runtime verification error');
    $check($afterChecks === glob(rtrim(sys_get_temp_dir(), '/') . '/cms-recovery-check-*'),
        'Private verification TAR is removed after failed validation');
    $codeCheckRoot->discard($codeCheck['path']);
    $broken = $factory('some different sql', 'other media');
    copy($broken, $base . '/tampered.tar.gz');
    $old = new PharData($base . '/tampered.tar.gz');
    $old->decompress(); unset($old);
    $modified = new PharData($base . '/tampered.tar');
    $modified->addFromString('database.sql', 'tampered database.sql');
    unset($modified);
    @unlink($base . '/tampered.tar.gz');
    rename($base . '/tampered.tar', $base . '/altered.tar');
    $modified = new PharData($base . '/altered.tar');
    $modified->compress(Phar::GZ); unset($modified);
    @unlink($base . '/altered.tar');
    $check($throws(static fn() => $validator->verify($base . '/altered.tar.gz')), 'Modified SQL rejected by independent manifest hash');
    @unlink($broken);
    // A valid manifest must never make altered media or unknown members acceptable.
    $mediaSource = $factory();
    copy($mediaSource, $base . '/media-before.tar.gz');
    $tar = new PharData($base . '/media-before.tar.gz');
    $tar->decompress(); unset($tar);
    $tar = new PharData($base . '/media-before.tar');
    $tar->addFromString('public_html/uploads/picture.jpg', 'altered media payload'); unset($tar);
    rename($base . '/media-before.tar', $base . '/media-after.tar');
    $tar = new PharData($base . '/media-after.tar');
    $tar->compress(Phar::GZ); unset($tar);
    $check($throws(static fn() => $validator->verify($base . '/media-after.tar.gz')), 'Changed media is rejected even if SQL checksum matches');
    @unlink($mediaSource);
    $rogueSource = $factory();
    copy($rogueSource, $base . '/extra-before.tar.gz');
    $tar = new PharData($base . '/extra-before.tar.gz');
    $tar->decompress(); unset($tar);
    $tar = new PharData($base . '/extra-before.tar');
    $tar->addFromString('unexpected.php', 'fixture'); unset($tar);
    rename($base . '/extra-before.tar', $base . '/extra-after.tar');
    $tar = new PharData($base . '/extra-after.tar');
    $tar->compress(Phar::GZ); unset($tar);
    $check($throws(static fn() => $validator->verify($base . '/extra-after.tar.gz')), 'Undeclared archive file rejected');
    @unlink($rogueSource);
    $tempAlias = rtrim(sys_get_temp_dir(), '/') . '/cms-phar-tmp-alias-' . bin2hex(random_bytes(10));
    $realTemp = realpath(sys_get_temp_dir());
    if ($realTemp === false || !symlink($realTemp, $tempAlias)) throw new RuntimeException('Cannot prepare temp alias fixture.');
    $aliasedSource = null;
    $aliasedFactory = static function () use ($factory, $tempAlias, &$aliasedSource): string {
        $source = $factory();
        $aliasedSource = $source;
        return $tempAlias . '/' . basename($source);
    };
    $bundle = new UpdateRecoveryBundle($base, $aliasedFactory);
    $created = $bundle->create();
    $check($aliasedSource !== null && !file_exists($aliasedSource), 'Temporary SQL/media backup cleaned through canonicalized parent path');
    $id = $created['id'];
    $location = $created['location'];
    $check(preg_match('/^recovery-[0-9a-f]{32}$/D', $id) === 1 && is_dir($location), 'Private recovery set committed under opaque identifier');
    $check((fileperms($location) & 0777) === 0700 && (fileperms(dirname($location)) & 0777) === 0700, 'Vault and bundle access restricted to owner');
    $check((fileperms($location . '/code.tar.gz') & 0777) === 0600 && (fileperms($location . '/data.tar.gz') & 0777) === 0600, 'Both archive permissions owner-only');
    $check((fileperms($location . '/manifest.json') & 0777) === 0600, 'Bundle metadata owner-only');
    $check($created['status'] === 'verified_archives_restore_not_rehearsed', 'Creation explicitly does not claim restore readiness');
    $status = $bundle->verify($id);
    $check($status['code_files'] >= 4 && $status['media_files'] === 1 && $status['has_env'], 'Saved bundle independently verifies code, configuration, SQL and media');
    $check($throws(static fn() => $bundle->verify('../etc')), 'Traversal in recovery ID rejected');
    $manifest = json_decode(file_get_contents($location . '/manifest.json'), true, 16, JSON_THROW_ON_ERROR);
    $check($manifest['approved_for_automatic_update'] === false && $manifest['requires_write_quiescence'] === true
        && $manifest['requires_restore_rehearsal'] === true, 'No unearned safety or automatic-install claim');
    $check(!file_exists($base . '/storage/update-recovery/.pending-' . substr($id, 9)), 'No pending uncommitted sensitive copy left');
    $check(!str_contains(file_get_contents($location . '/manifest.json'), 'LOCAL_PASSWORD='), 'Secret content does not appear in bundle manifest');
    $check($throws(static fn() => $bundle->verify('recovery-' . str_repeat('0', 32))), 'Nonexistent recovery identifier fails closed');
    $check($throws(static function () use ($base): void {
        $bad = $base . '/storage/update-recovery/recovery-' . str_repeat('b', 32);
        symlink($base . '/core/classes/Sample.php', $bad);
        try { (new UpdateRecoveryBundle($base))->verify(basename($bad)); }
        finally { unlink($bad); }
    }), 'Symlink bundle traversal rejected');
    $check($throws(static function () use ($base, $factory): void {
        $vault = $base . '/storage/update-recovery';
        $lock = fopen($vault . '/.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Fixture lock failure.');
        try { (new UpdateRecoveryBundle($base, $factory))->create(); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
    }), 'Concurrent recovery creation blocked by lock');
    $failedFactory = static function (): string { throw new RuntimeException('Synthetic DB backup failure.'); };
    $check($throws(static fn() => (new UpdateRecoveryBundle($base, $failedFactory))->create()), 'Data backup failure aborts whole recovery set');
    $pending = glob($base . '/storage/update-recovery/.pending-*');
    $check(is_array($pending) && $pending === [], 'Failed recovery work cleans temporary sensitive copies');
    $check($bundle->verify($id)['media_files'] === 1, 'Previous verified bundle survives failed attempt');
    $check($bundle->create()['status'] === 'verified_archives_restore_not_rehearsed', 'Second bundle can be created separately');
    $check($bundle->create()['status'] === 'verified_archives_restore_not_rehearsed', 'Third bundle can be created separately');
    $latest = $bundle->create();
    $check($latest['status'] === 'verified_archives_restore_not_rehearsed', 'Temporary fourth backup can be created before any pruning');
    $check($throws(static fn() => $bundle->create()), 'Fifth recovery set fails closed without deleting backups');
    $check(count(glob($base . '/storage/update-recovery/recovery-*')) === 4, 'Four verified recovery points retained before successful-commit cleanup');
    mkdir($base . '/storage/update-recovery/.one-click-pending-' . str_repeat('a', 32), 0700);
    $check($throws(static fn() => $bundle->pruneAfterSuccessfulInstall($latest['id'])), 'Pending update journal forbids recovery pruning');
    $check(count(glob($base . '/storage/update-recovery/recovery-*')) === 4, 'Pending journal preserves all four backups');
    rmdir($base . '/storage/update-recovery/.one-click-pending-' . str_repeat('a', 32));
    $check($bundle->pruneAfterSuccessfulInstall($latest['id']) === 1, 'After successful commit exactly one oldest backup retired');
    $check(count(glob($base . '/storage/update-recovery/recovery-*')) === 3, 'Three recovery points remain after successful rotation');
    $check($bundle->verify($latest['id'])['media_files'] === 1, 'Newly created recovery point is always preserved');
    $check($bundle->pruneAfterSuccessfulInstall($latest['id']) === 0, 'Cleanup is idempotent');
    file_put_contents($latest['location'] . '/data.tar.gz', 'tampered', FILE_APPEND);
    $check($throws(static fn() => $bundle->verify($latest['id'])), 'Tampered durable SQL/media archive detected');
    $check($throws(static fn() => $validator->verify($base . '/core/classes/Sample.php')), 'Non-archive data rejected');
    $check($throws(static fn() => (new UpdateRecoveryBundle($base . '/storage'))->verify($id)), 'Wrong vault cannot enumerate bundle');
} finally {
    @unlink($fixture ?? '');
    foreach ([$alias ?? null, $codeAlias ?? null, $tempAlias ?? null] as $link) {
        if (is_string($link) && is_link($link)) @unlink($link);
    }
    $remove($base);
}
echo "Update Recovery Bundle QA1: $passed PASS / $failed FAIL (synthetic only; no database, HTTP, update or live-site files)\n";
exit($failed === 0 ? 0 : 1);
