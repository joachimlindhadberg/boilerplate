<?php

declare(strict_types=1);

/**
 * F1c: internal process-wide admission barrier for PHP requests and CMS cron.
 * All guarded PHP entry points take a shared flock. A future updater may take
 * the exclusive flock only after all currently guarded requests have ended.
 *
 * This is NOT a transaction-level database lock, SQL restore, or permission to
 * update: external writers, third-party processes and server-level operations
 * must be accounted for before any automatic update can be enabled.
 */
final class UpdateMaintenanceGate
{
    private string $root;
    /** @var resource|null */
    private $handle = null;
    private ?string $mode = null;
    /** @var list<self> Retain HTTP reader locks for the whole request. */
    private static array $webReaders = [];

    public function __construct(?string $root = null)
    {
        $source = $root ?? dirname(__DIR__, 2);
        $resolved = realpath($source);
        if (!is_string($resolved) || !is_dir($resolved) || is_link($source)) {
            throw new RuntimeException('Unsafe CMS root for update maintenance barrier.');
        }
        $this->root = rtrim($resolved, '/');
    }

    /** Acquire shared access or report that maintenance is already in progress. */
    public function acquireReader(): bool
    {
        return $this->acquire(LOCK_SH, 'reader');
    }

    /**
     * Nonblocking exclusive barrier: never wait indefinitely for live writers.
     * Caller MUST hold the returned exclusive barrier through backup, release
     * verification, install AND all recovery/cleanup steps.
     */
    public function acquireExclusive(): bool
    {
        return $this->acquire(LOCK_EX, 'exclusive');
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            @flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
        $this->mode = null;
    }

    public function __destruct()
    {
        $this->release();
    }

    /**
     * All normal HTTP routes use the shared barrier before CMS bootstrap or
     * scheduled-on-traffic tasks execute. A locked request gets a plain 503;
     * do not expose technical vault details, credentials or exception content.
     */
    public static function guardHttp(string $root): void
    {
        if (PHP_SAPI === 'cli') return;
        try {
            $gate = new self($root);
            if (!$gate->acquireReader()) {
                self::maintenance503();
            }
            self::$webReaders[] = $gate;
        } catch (Throwable $e) {
            error_log('Update maintenance gate unavailable: ' . get_class($e));
            self::maintenance503();
        }
    }

    /**
     * Only an already authenticated, CSRF-verified update controller may call
     * this before trying acquireExclusive(). All existing CMS request readers
     * are released; other workers continue to hold their own shared locks.
     */
    public static function releaseCurrentHttpReadersForUpdate(): void
    {
        foreach (self::$webReaders as $reader) {
            $reader->release();
        }
        self::$webReaders = [];
    }

    private static function maintenance503(): never
    {
        http_response_code(503);
        header('Retry-After: 60');
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'The website is temporarily unavailable for maintenance.';
        exit;
    }

    private function acquire(int $operation, string $mode): bool
    {
        if ($this->handle !== null) {
            throw new RuntimeException('Update gate is already held by this instance.');
        }
        $dir = $this->root . '/storage';
        $vault = $dir . '/update-recovery';
        if (is_link($dir) || (file_exists($dir) && !is_dir($dir))) {
            throw new RuntimeException('Unsafe CMS recovery storage directory.');
        }
        if (!is_dir($dir) && !@mkdir($dir, 0700)) {
            throw new RuntimeException('Cannot initialize CMS recovery storage.');
        }
        if (is_link($vault) || (file_exists($vault) && !is_dir($vault))) {
            throw new RuntimeException('Unsafe CMS recovery vault.');
        }
        if (!is_dir($vault) && !@mkdir($vault, 0700)) {
            throw new RuntimeException('Cannot initialize CMS recovery vault.');
        }
        @chmod($vault, 0700);
        clearstatcache(true, $vault);
        if ((fileperms($vault) & 0077) !== 0) {
            throw new RuntimeException('CMS recovery vault must be private.');
        }
        $path = $vault . '/.update-gate.lock';
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new RuntimeException('Unsafe CMS update gate file.');
        }
        $stream = @fopen($path, 'c+b');
        if ($stream === false) throw new RuntimeException('Cannot open CMS update gate.');
        try {
            if (is_link($path)) throw new RuntimeException('Unsafe CMS update gate file.');
            @chmod($path, 0600);
            clearstatcache(true, $path);
            $stat = fstat($stream);
            $actual = stat($path);
            if (!is_array($stat) || !is_array($actual) || $stat['ino'] !== $actual['ino']
                || $stat['dev'] !== $actual['dev'] || ($stat['mode'] & 0170000) !== 0100000
                || (fileperms($path) & 0077) !== 0) {
                throw new RuntimeException('Unsafe CMS update gate handle.');
            }
            if (!flock($stream, $operation | LOCK_NB)) {
                fclose($stream);
                return false;
            }
            $this->handle = $stream;
            $this->mode = $mode;
            return true;
        } catch (Throwable $e) {
            fclose($stream);
            throw $e;
        }
    }
}
