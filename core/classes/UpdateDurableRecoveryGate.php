<?php

declare(strict_types=1);

/**
 * F4c.2: request-time, persistent, fail-closed recovery admission.
 *
 * READ ONLY. Never changes a journal, restores a file, runs SQL, authenticates a
 * user, or enables database updates. The recovery authority remains the
 * existing tools.manage + CSRF controller under UpdateMaintenanceGate.
 *
 * A crashed writer releases its flock. Persistent pending journal directories
 * must therefore be checked AFTER shared admission, before any CMS DB bootstrap.
 */
final class UpdateDurableRecoveryGate
{
    public const CLEAR = 'clear';
    public const FILE = 'file_recovery';
    public const DATABASE = 'database_recovery';
    public const MANUAL = 'manual_recovery';

    private string $root;

    public function __construct(?string $root = null)
    {
        $given = $root ?? dirname(__DIR__, 2);
        $resolved = realpath($given);
        if (!is_string($resolved) || !is_dir($resolved) || is_link($given)) {
            throw new RuntimeException('Unsafe recovery admission root.');
        }
        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    /**
     * Read persistent on-disk state. Does not trust PHP process memory/flock.
     * Unknown or corrupt recovery markers always require manual attention.
     */
    public function status(): string
    {
        $storage = $this->root . '/storage';
        $vault = $storage . '/update-recovery';
        if (is_link($storage) || (file_exists($storage) && !is_dir($storage))) {
            return self::MANUAL;
        }
        if (!is_dir($storage)) return self::CLEAR;
        if (is_link($vault) || (file_exists($vault) && !is_dir($vault))) {
            return self::MANUAL;
        }
        if (!is_dir($vault)) return self::CLEAR;
        clearstatcache(true, $vault);
        $perms = @fileperms($vault);
        if (!is_int($perms) || ($perms & 0077) !== 0 || !is_readable($vault)) {
            return self::MANUAL;
        }
        $filePending = 0;
        $dbPending = false;
        try {
            foreach (new DirectoryIterator($vault) as $entry) {
                if ($entry->isDot()) continue;
                $name = $entry->getFilename();
                $path = $entry->getPathname();
                if (str_starts_with($name, '.one-click-pending-')) {
                    if (!preg_match('/^\.one-click-pending-[a-f0-9]{32}$/D', $name)
                        || $entry->isLink() || !$entry->isDir()) {
                        return self::MANUAL;
                    }
                    $manifest = $path . '/manifest.json';
                    $directoryMode = @fileperms($path);
                    $manifestMode = @fileperms($manifest);
                    if (!is_int($directoryMode) || ($directoryMode & 0077) !== 0
                        || is_link($manifest) || !is_file($manifest)
                        || !is_int($manifestMode) || ($manifestMode & 0077) !== 0) {
                        return self::MANUAL;
                    }
                    $size = @filesize($manifest);
                    if (!is_int($size) || $size < 2 || $size > 2097152) return self::MANUAL;
                    $raw = @file_get_contents($manifest);
                    if (!is_string($raw) || strlen($raw) !== $size) return self::MANUAL;
                    try {
                        $journal = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
                    } catch (Throwable $e) {
                        return self::MANUAL;
                    }
                    if (!is_array($journal)
                        || ($journal['format'] ?? null) !== 'boilerplate-one-click-journal-v1'
                        || !is_array($journal['plan'] ?? null)
                        || count($journal['plan']) > 1500
                        || !is_string($journal['backup_id'] ?? null)
                        || !preg_match('/^recovery-[a-f0-9]{32}$/D', $journal['backup_id'])) {
                        return self::MANUAL;
                    }
                    $filePending++;
                } elseif (str_starts_with($name, '.db-update-')) {
                    // Reserved for the future database-aware executor. Never
                    // offer file-only restore as a remedy for a DB journal.
                    if (!preg_match('/^\.db-update-(?:pending|blocked)-[a-f0-9]{32}$/D', $name)
                        || $entry->isLink() || (!$entry->isFile() && !$entry->isDir())) {
                        return self::MANUAL;
                    }
                    $dbPending = true;
                }
            }
        } catch (Throwable $e) {
            return self::MANUAL;
        }
        if ($filePending > 1) return self::MANUAL;
        if ($dbPending) return self::DATABASE;
        return $filePending === 1 ? self::FILE : self::CLEAR;
    }

    /**
     * Pure routing decision. Does NOT confer authentication/CSRF permission.
     * The allowed login endpoints are narrowly scoped to reach the Core recovery
     * page after a process crash; all other requests stay blocked.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     */
    public function permitsHttp(
        string $scriptFilename,
        string $requestUri,
        string $method,
        array $query = [],
        array $post = []
    ): bool {
        $state = $this->status();
        if ($state === self::CLEAR) return true;
        $script = realpath($scriptFilename);
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($script) || !is_string($path)) return false;
        $method = strtoupper($method);
        $adminRoot = $this->root . '/public_html/admin/';
        if ($script === $adminRoot . 'tools.php') {
            $canonical = in_array($path, ['/admin/tools/update', '/admin/vaerktoejer/update'], true);
            $direct = $path === '/admin/tools.php';
            if ((!$canonical && !$direct) || ($query['mode'] ?? 'update') !== 'update') return false;
            if ($method === 'GET' || $method === 'HEAD') return true;
            return $method === 'POST' && $state === self::FILE
                && ($post['action'] ?? null) === 'restore_interrupted_update';
        }
        if ($script === $adminRoot . 'index.php') {
            if (!in_array($path, ['/admin', '/admin/', '/admin/index.php'], true)
                || ($query['legacy_path'] ?? '') !== '') return false;
            if ($method === 'POST') {
                return ($query['mode'] ?? '') === 'authenticate';
            }
            // GET displays login if unauthenticated; authenticated requests
            // must redirect to Tools -> Updates before dashboard code runs.
            return in_array($method, ['GET', 'HEAD'], true)
                && ($query['mode'] ?? '') === '';
        }
        return false;
    }

    /** Called after UpdateMaintenanceGate::guardHttp() has acquired its reader. */
    public static function guardHttp(string $root): void
    {
        if (PHP_SAPI === 'cli') return;
        try {
            $gate = new self($root);
            if ($gate->permitsHttp(
                (string)($_SERVER['SCRIPT_FILENAME'] ?? ''),
                (string)($_SERVER['REQUEST_URI'] ?? ''),
                (string)($_SERVER['REQUEST_METHOD'] ?? ''),
                $_GET,
                $_POST
            )) return;
        } catch (Throwable $e) {
            error_log('Recovery admission unavailable: ' . get_class($e));
        }
        http_response_code(503);
        header('Retry-After: 60');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'The website is temporarily unavailable while an update requires recovery.';
        exit;
    }
}
