<?php

declare(strict_types=1);

/**
 * F4c.6 QA1: READ-ONLY browser/recovery authorization rehearsal.
 *
 * This policy is usable ONLY in a private, owner-only /tmp fixture named
 * bp-f4c6-browser-<16 lowercase hex>. It cannot execute SQL, create a job,
 * modify an existing recovery journal, bypass F4c.2, or unlock an update.
 * No live route includes or instantiates this class.
 *
 * The Auth instance MUST be initialized by the normal Core bootstrap in any
 * future production adaptation. This QA component deliberately has no such
 * bootstrap; the isolated harness simulates a checked administrator session.
 */
final class UpdateBrowserRecoveryAuthorizationQa
{
    private const ROOT_RE = '/\Abp-f4c6-browser-[a-f0-9]{16}\z/D';
    private const MARKER_RE = '/\A\.db-update-pending-([a-f0-9]{32})\z/D';
    private const SHA_RE = '/\A[a-f0-9]{64}\z/D';
    private const HEX_TOKEN_RE = '/\A[a-f0-9]{64}\z/D';
    private const FORMAT = 'boilerplate-f4c6-browser-authorization-qa1';

    private string $root;
    private string $vault;
    private UpdateDurableRecoveryGate $recovery;

    public function __construct(string $fixtureRoot)
    {
        if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
            throw new RuntimeException('F4c.6 browser authorization is QA-only.');
        }
        $tmp = realpath('/tmp');
        $root = realpath($fixtureRoot);
        if (!is_string($tmp) || !is_string($root) || is_link($fixtureRoot)
            || dirname($root) !== $tmp
            || preg_match(self::ROOT_RE, basename($root)) !== 1
            || !self::privateDirectory($root)) {
            throw new RuntimeException('F4c.6 requires a direct private /tmp QA fixture.');
        }
        $this->root = $root;
        $this->vault = $root . '/storage/update-recovery';
        $this->recovery = new UpdateDurableRecoveryGate($root);
    }

    /**
     * Read-only authorization policy using real Core capability checks and the
     * existing session-CSRF function. The accepted POST returns REVIEW ONLY:
     * it is NOT a migration authorization, database recovery bypass or SQL job.
     *
     * @param array<string,mixed> $server
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @return array{decision:string,blocked:bool,install_ready:bool,can_execute_sql:bool}
     */
    public function review(Auth $auth, array $server, array $query, array $post): array
    {
        $denied = self::result('denied');
        try {
            if (PHP_SAPI === 'cli-server'
                && (($server['SERVER_ADDR'] ?? $server['SERVER_NAME'] ?? '') !== '127.0.0.1'
                    || ($server['REMOTE_ADDR'] ?? '') !== '127.0.0.1')) return $denied;
            if (!$auth->check() || !$auth->canAccessAdmin()
                || !$auth->can('tools.manage') || $auth->mustChangePassword()) return $denied;
            $ownerId = $auth->id();
            if (!is_int($ownerId) || $ownerId < 1) return $denied;
            if (session_status() !== PHP_SESSION_ACTIVE) return $denied;

            $script = $server['SCRIPT_FILENAME'] ?? null;
            $uri = $server['REQUEST_URI'] ?? null;
            $method = $server['REQUEST_METHOD'] ?? null;
            if (!is_string($script) || !is_string($uri) || !is_string($method)
                || !in_array($method, ['GET', 'POST'], true)) return $denied;
            if (realpath($script) !== $this->root . '/public_html/admin/tools.php') return $denied;
            $path = parse_url($uri, PHP_URL_PATH);
            if (!is_string($path) || !in_array($path, [
                '/admin/tools/update', '/admin/vaerktoejer/update', '/admin/tools.php'
            ], true)) return $denied;
            if (AdminToolsRoute::resolve($uri, $query['mode'] ?? null) !== 'update') return $denied;
            if (array_diff(array_keys($query), ['mode']) !== []) return $denied;

            // F4c.2 MUST still block all database-recovery POST requests in
            // production. This QA policy cannot override permitsHttp().
            if ($this->recovery->status() !== UpdateDurableRecoveryGate::DATABASE) return $denied;
            $journal = $this->journal();
            if ($journal === null || $journal['owner_id'] !== $ownerId) return $denied;
            if ((int)($_SESSION['f4c6_qa_owner_id'] ?? 0) !== $ownerId) return $denied;
            $secret = $_SESSION['f4c6_qa_run_token'] ?? null;
            if (!is_string($secret) || preg_match(self::HEX_TOKEN_RE, $secret) !== 1
                || !hash_equals($journal['run_sha256'], hash('sha256', $secret))) return $denied;

            if ($method === 'GET') {
                if ($post !== [] || !$this->recovery->permitsHttp($script, $uri, 'GET', $query, [])) {
                    return $denied;
                }
                return self::result('read_only_status');
            }

            // This branch only demonstrates a future authorization *contract*.
            // F4c.2 still denies the corresponding real HTTP POST. Never
            // mistake a QA review decision for permission to run native SQL.
            if ($this->recovery->permitsHttp($script, $uri, 'POST', $query, $post)) return $denied;
            if (array_keys($post) !== ['action', 'csrf_token', 'run_token']
                || ($post['action'] ?? null) !== 'f4c6_qa_review_only'
                || !is_string($post['run_token'] ?? null)
                || !hash_equals($secret, $post['run_token'])) return $denied;
            if (!function_exists('admin_verify_csrf')
                || !admin_verify_csrf($post['csrf_token'] ?? null)) return $denied;
            if (!self::sameOrigin($server)) return $denied;

            return self::result('policy_review_only');
        } catch (Throwable $e) {
            // Corruption, missing functions or invalid PHP inputs fail closed.
            return $denied;
        }
    }

    /** @return array{decision:string,blocked:bool,install_ready:bool,can_execute_sql:bool} */
    private static function result(string $decision): array
    {
        return ['decision' => $decision, 'blocked' => true,
            'install_ready' => false, 'can_execute_sql' => false];
    }

    /** @param array<string,mixed> $server */
    private static function sameOrigin(array $server): bool
    {
        if (($server['SERVER_ADDR'] ?? $server['SERVER_NAME'] ?? null) !== '127.0.0.1') return false;
        if (PHP_SAPI === 'cli-server' && ($server['REMOTE_ADDR'] ?? null) !== '127.0.0.1') return false;
        $port = $server['SERVER_PORT'] ?? null;
        if ((!is_string($port) && !is_int($port))
            || !preg_match('/\A[1-9][0-9]{0,4}\z/D', (string)$port)
            || (int)$port > 65535) return false;
        $host = '127.0.0.1:' . (string)$port;
        if (($server['HTTP_HOST'] ?? null) !== $host) return false;
        if (($server['HTTP_ORIGIN'] ?? null) !== 'http://' . $host) return false;
        $site = $server['HTTP_SEC_FETCH_SITE'] ?? null;
        return $site === null || $site === 'same-origin';
    }

    /** Read and strictly validate one PRIVATE QA marker; never mutate it. */
    private function journal(): ?array
    {
        $storage = $this->root . '/storage';
        if (!self::privateDirectory($storage) || !self::privateDirectory($this->vault)) return null;
        $found = [];
        foreach (new DirectoryIterator($this->vault) as $entry) {
            if ($entry->isDot() || $entry->getFilename() === '.update-gate.lock') continue;
            $found[] = $entry->getPathname();
        }
        if (count($found) !== 1) return null;
        $file = $found[0];
        $name = basename($file);
        if (preg_match(self::MARKER_RE, $name, $m) !== 1
            || is_link($file) || !is_file($file)) return null;
        clearstatcache(true, $file);
        $mode = @fileperms($file);
        $size = @filesize($file);
        if (!is_int($mode) || ($mode & 0077) !== 0
            || !is_int($size) || $size < 2 || $size > 4096) return null;
        $raw = @file_get_contents($file);
        if (!is_string($raw) || strlen($raw) !== $size) return null;
        $j = json_decode($raw, true, 10, JSON_THROW_ON_ERROR);
        if (!is_array($j) || array_keys($j) !== [
            'format', 'id', 'phase', 'owner_id', 'run_sha256',
            'blocked', 'install_ready', 'production_migration'
        ] || $j['format'] !== self::FORMAT || $j['id'] !== $m[1]
            || $j['phase'] !== 'manual_recovery'
            || !is_int($j['owner_id']) || $j['owner_id'] < 1
            || !is_string($j['run_sha256'])
            || preg_match(self::SHA_RE, $j['run_sha256']) !== 1
            || $j['blocked'] !== true || $j['install_ready'] !== false
            || $j['production_migration'] !== false) return null;
        return $j;
    }

    private static function privateDirectory(string $path): bool
    {
        if (is_link($path) || !is_dir($path)) return false;
        clearstatcache(true, $path);
        $permissions = @fileperms($path);
        return is_int($permissions) && ($permissions & 0077) === 0;
    }
}
