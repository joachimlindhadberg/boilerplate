<?php

declare(strict_types=1);

/**
 * Inspect a candidate release ZIP, without extracting, executing, storing or applying it.
 * This is NOT a trusted-release verifier or authorization to update. A separate
 * source-authentication and full restore test is still required before deployment.
 */
final class UpdatePackageReview
{
    public const MAX_ARCHIVE_BYTES = 20971520; // 20 MiB transmitted
    private const MAX_ENTRIES = 1500;
    private const MAX_EXPANDED_BYTES = 134217728; // 128 MiB declared total
    private const MAX_SINGLE_BYTES = 12582912; // 12 MiB per member
    private const MAX_MIGRATION_BYTES = 1048576;

    public function __construct(private ?string $root = null)
    {
        $this->root = rtrim($root ?? dirname(__DIR__, 2), DIRECTORY_SEPARATOR);
    }

    /**
     * The passed path MUST come from a server-verified PHP uploaded temp file.
     * @return array{status:string, checks:list<array{label:string,status:string,detail:string}>, current_version:string,target_version:string,sha256:string,files:int, new_migrations:int}
     */
    public function inspect(string $path, int $uploadedSize): array
    {
        $result = [
            'status' => 'blocked', 'checks' => [],
            'current_version' => $this->currentVersion(), 'target_version' => 'unknown',
            'sha256' => '', 'files' => 0, 'new_migrations' => 0,
        ];
        $add = static function (string $label, string $status, string $detail = '') use (&$result): void {
            $result['checks'][] = ['label' => $label, 'status' => $status, 'detail' => $detail];
        };
        if ($uploadedSize < 1 || $uploadedSize > self::MAX_ARCHIVE_BYTES || !is_file($path)
            || filesize($path) !== $uploadedSize) {
            $add('Archive upload', 'blocked', 'Invalid or oversized file (maximum 20 MiB).');
            return $result;
        }
        if (!class_exists('ZipArchive')) {
            $add('PHP ZipArchive', 'blocked', 'PHP zip extension is required to inspect a release archive.');
            return $result;
        }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            $add('Archive checksum', 'blocked', 'Cannot read archive.');
            return $result;
        }
        $result['sha256'] = $hash;
        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::RDONLY);
        if ($opened !== true) {
            $add('ZIP archive', 'blocked', 'Not a readable ZIP file.');
            return $result;
        }
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) {
                $add('ZIP contents', 'blocked', 'Archive contains too many or too few entries.');
                return $result;
            }
            $files = [];
            $normalized = [];
            $sum = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if (!is_array($entry) || !isset($entry['name'], $entry['size'], $entry['comp_size'])) {
                    $add('ZIP contents', 'blocked', 'Unreadable member metadata.');
                    return $result;
                }
                $name = (string)$entry['name'];
                if ((int)($entry['encryption_method'] ?? 0) !== 0) {
                    $add('ZIP contents', 'blocked', 'Encrypted ZIP members are not supported.');
                    return $result;
                }
                $isDir = str_ends_with($name, '/');
                if (!self::isSafeMember($name, $isDir)) {
                    $add('ZIP paths', 'blocked',
                        'Rejected entry: ' . self::diagnosticMember($name)
                        . '. Use the complete Production/Runtime ZIP, not a DEV, hotfix or repacked archive.');
                    return $result;
                }
                $lower = strtolower(rtrim($name, '/'));
                if (isset($normalized[$lower])) {
                    $add('ZIP paths', 'blocked',
                        'Duplicate or case-colliding entry: ' . self::diagnosticMember($name) . '.');
                    return $result;
                }
                $normalized[$lower] = true;
                $size = (int)$entry['size'];
                $packed = (int)$entry['comp_size'];
                if ($size < 0 || $packed < 0 || $size > self::MAX_SINGLE_BYTES || ($isDir && $size !== 0)) {
                    $add('ZIP sizes', 'blocked', 'Unexpected file or directory size.');
                    return $result;
                }
                $sum += $size;
                if ($sum > self::MAX_EXPANDED_BYTES) {
                    $add('ZIP sizes', 'blocked', 'Uncompressed archive exceeds the review limit.');
                    return $result;
                }
                $opsys = 0;
                $attributes = 0;
                if (!$zip->getExternalAttributesIndex($i, $opsys, $attributes) || $opsys !== ZipArchive::OPSYS_UNIX) {
                    $add('ZIP permissions', 'blocked', 'Archive lacks expected Unix file metadata.');
                    return $result;
                }
                $type = (($attributes >> 16) & 0170000);
                if ($type !== ($isDir ? 0040000 : 0100000)) {
                    $add('ZIP permissions', 'blocked', 'Archive contains a link or non-regular file.');
                    return $result;
                }
                if (!$isDir) {
                    $files[substr($name, strlen('boilerplate/'))] = $i;
                }
            }
            $result['files'] = count($files);
            // The official distribution contains public_html/.user.ini for
            // PHP upload limits. It is not a secret file, but alterations to
            // its host-level PHP directives require a separate manual review.
            if (isset($files['public_html/.user.ini'])) {
                $iniIndex = $files['public_html/.user.ini'];
                $iniInfo = $zip->statIndex($iniIndex);
                if (!is_array($iniInfo) || (int)$iniInfo['size'] > 65536) {
                    $add('PHP hosting configuration', 'blocked', 'Oversized PHP configuration file.');
                    return $result;
                }
                $iniContent = $zip->getFromIndex($iniIndex, 65536);
                $installedIni = @file_get_contents($this->root . '/public_html/.user.ini');
                if (!is_string($iniContent)) {
                    $add('PHP hosting configuration', 'blocked', 'Unreadable PHP configuration file.');
                    return $result;
                }
                $add('PHP hosting configuration', is_string($installedIni) && hash_equals($installedIni, $iniContent) ? 'good' : 'review',
                    is_string($installedIni) && hash_equals($installedIni, $iniContent)
                        ? 'Public PHP upload settings match the installed CMS.'
                        : 'PHP hosting settings differ or cannot be compared; review manually.');
            }
            $mandatory = [
                'public_html/incl/init.php', 'public_html/index.php', 'public_html/admin/index.php',
                'public_html/.htaccess', 'public_html/admin/.htaccess', 'public_html/uploads/.htaccess',
                'core/classes/MigrationRunner.php', 'docs/deployment/UPDATING.md',
                'database/updates/20261013_000_core_schema.php',
            ];
            foreach ($mandatory as $required) {
                if (!isset($files[$required])) {
                    $add('Production package', 'blocked',
                        'Required runtime file is missing: ' . self::diagnosticMember('boilerplate/' . $required) . '.');
                    return $result;
                }
            }
            $add('Package structure', 'good', $result['files'] . ' regular files; no extraction performed.');
            $initIndex = $files['public_html/incl/init.php'];
            $initInfo = $zip->statIndex($initIndex);
            if (!is_array($initInfo) || (int)$initInfo['size'] > 131072) {
                $add('Target version', 'blocked', 'Invalid version source.');
                return $result;
            }
            $init = $zip->getFromIndex($initIndex, 131072);
            if (!is_string($init) || !preg_match(UpdateReleaseVersion::SOURCE_PATTERN, $init, $matches)) {
                $add('Target version', 'blocked', 'Cannot identify the target release version.');
                return $result;
            }
            $target = $matches[1];
            $result['target_version'] = $target;
            if (!UpdateReleaseVersion::valid($result['current_version'])
                || version_compare($target, $result['current_version'], '<=')) {
                $add('Target version', 'blocked', 'Target ' . $target . ' is not newer than installed ' . $result['current_version'] . '.');
            } else {
                $add('Target version', 'good', 'Installed ' . $result['current_version'] . ' → target ' . $target . '.');
            }
            $currentMigrations = $this->currentMigrationHashes();
            $targetMigrations = [];
            foreach ($files as $name => $index) {
                if (!self::isMigration($name)) continue;
                $info = $zip->statIndex($index);
                if (!is_array($info) || (int)$info['size'] > self::MAX_MIGRATION_BYTES) {
                    $add('Migration history', 'blocked', 'Oversized migration source.');
                    return $result;
                }
                $content = $zip->getFromIndex($index, self::MAX_MIGRATION_BYTES);
                if (!is_string($content)) {
                    $add('Migration history', 'blocked', 'Unreadable migration source.');
                    return $result;
                }
                $targetMigrations[$name] = hash('sha256', $content);
            }
            $changed = 0;
            foreach ($currentMigrations as $name => $digest) {
                if (!isset($targetMigrations[$name]) || !hash_equals($digest, $targetMigrations[$name])) {
                    $changed++;
                }
            }
            $new = count(array_diff_key($targetMigrations, $currentMigrations));
            $result['new_migrations'] = $new;
            $add('Migration history', $changed ? 'blocked' : ($new ? 'review' : 'good'),
                $changed . ' missing/changed historical migration(s); ' . $new . ' new migration(s).');
            // A well-formed ZIP is not proof of authentic/untampered software.
            $add('Publisher authenticity', 'review', 'No trusted signature or authenticated release manifest is verified.');
            $add('Backup and rollback', 'review', 'No full backup, staging upgrade or rollback has been verified.');
            $blocked = array_filter($result['checks'], static fn(array $check): bool => $check['status'] === 'blocked');
            $result['status'] = $blocked ? 'blocked' : 'review';
            return $result;
        } finally {
            $zip->close();
        }
    }

    /**
     * Short, printable, HTML-escaped-at-view diagnostics. Never interpret or
     * extract an uploaded member name. Preserve path information for QA while
     * stripping control/invalid UTF-8 bytes and limiting the display length.
     */
    private static function diagnosticMember(string $name): string
    {
        $printable = preg_replace('/[^\x20-\x7E]/', '?', $name);
        return '"' . substr(is_string($printable) ? $printable : '[unreadable]', 0, 160) . '"';
    }

    public static function isSafeMember(string $name, bool $isDir): bool
    {
        if (!str_starts_with($name, 'boilerplate/') || strlen($name) > 280
            || str_contains($name, '\\') || str_contains($name, "\0")
            || !preg_match('~^[A-Za-z0-9._/-]+$~D', $name)) return false;
        if ($isDir !== str_ends_with($name, '/')) return false;
        $relative = substr($name, strlen('boilerplate/'));
        if ($relative === '') return $isDir;
        $parts = explode('/', rtrim($relative, '/'));
        if (in_array('', $parts, true) || in_array('.', $parts, true) || in_array('..', $parts, true)) return false;
        $path = rtrim($relative, '/');
        $allowed = ['LICENSE', 'README.md', 'core', 'database', 'docs', 'modules', 'public_html'];
        $top = $parts[0];
        if (!in_array($top, $allowed, true)) return false;
        if (in_array($top, ['README.md', 'LICENSE'], true)) return $path === $top && !$isDir;
        if (str_starts_with($path, 'docs/releases') || str_starts_with($path, 'docs/audits')
            || str_starts_with($path, 'public_html/assets/uploads') || str_starts_with($path, 'tests/')
            || preg_match('~(^|/)(?:\.env(?:\..+)?|\.git|\.ds_store|\.svn|node_modules|vendor)(?:/|$)~', strtolower($path))) return false;
        // Allow ONLY the PHP settings file shipped in official releases.
        // Never permit arbitrary .user.ini elsewhere, including nested dirs.
        if (str_contains(strtolower($path), '.user.ini') && ($path !== 'public_html/.user.ini' || $isDir)) return false;
        if (str_starts_with($path, 'public_html/uploads/')) return $path === 'public_html/uploads/.htaccess' && !$isDir;
        if ($path === 'public_html/uploads') return $isDir;
        if (str_starts_with($path, 'core/cli/') && $top === 'core') {
            $cli = basename($path);
            if (!in_array($cli, ['migrate.php', 'create-admin.php', 'qa.php', 'media-index.php'], true)) return false;
        }
        return true;
    }

    private function currentVersion(): string
    {
        if (defined('BOILERPLATE_VERSION')) return (string)BOILERPLATE_VERSION;
        $content = @file_get_contents($this->root . '/public_html/incl/init.php');
        if (is_string($content) && preg_match(UpdateReleaseVersion::SOURCE_PATTERN, $content, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    private function currentMigrationHashes(): array
    {
        $roots = [$this->root . '/database/updates'];
        foreach (glob($this->root . '/modules/*/migrations', GLOB_ONLYDIR) ?: [] as $path) $roots[] = $path;
        $result = [];
        foreach ($roots as $directory) {
            foreach (glob($directory . '/*.php') ?: [] as $path) {
                if (is_link($path)) throw new RuntimeException('Symlink in installed migration path.');
                $relative = substr($path, strlen($this->root) + 1);
                $digest = hash_file('sha256', $path);
                if (!is_string($digest)) throw new RuntimeException('Unable to read installed migration.');
                $result[$relative] = $digest;
            }
        }
        return $result;
    }

    private static function isMigration(string $path): bool
    {
        return (bool)preg_match('~^(?:database/updates|modules/[a-z0-9_-]+/migrations)/[0-9]{8}_[a-z0-9_-]+\.php$~D', $path);
    }
}
