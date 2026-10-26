<?php

/**
 * Core redirect manager.
 *
 * Redirects belong to the CMS routing layer rather than a project module.
 * Sources are public request paths; targets may be internal paths or absolute
 * HTTP(S) URLs. Automatic redirects are created when published page URLs move.
 */
final class Redirects
{
    protected Db $db;

    /** Shared FROM/JOIN for redirect list and its filtered row count. */
    private const FILTER_SOURCE = 'FROM redirects r LEFT JOIN pages p ON p.id = r.page_id';

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    /** @deprecated Schema is managed exclusively by the migration runner. */
    public function ensureSchema(): void
    {
        // Kept for extensions calling this method; deliberately performs no DDL.
    }

    /** Installer-only schema creation. */
    public static function installSchema(Db $db): void
    {
        $db->query(
            "CREATE TABLE IF NOT EXISTS redirects (\n"
            . "id int NOT NULL AUTO_INCREMENT,\n"
            . "source_path varchar(512) NOT NULL,\n"
            . "target_url varchar(2048) NOT NULL,\n"
            . "redirect_type smallint unsigned NOT NULL DEFAULT 301,\n"
            . "is_active tinyint(1) NOT NULL DEFAULT 1,\n"
            . "is_automatic tinyint(1) NOT NULL DEFAULT 0,\n"
            . "page_id int DEFAULT NULL,\n"
            . "hit_count bigint unsigned NOT NULL DEFAULT 0,\n"
            . "last_hit_at datetime DEFAULT NULL,\n"
            . "created_at datetime DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY source_path (source_path),\n"
            . "KEY redirects_active (is_active),\n"
            . "KEY redirects_page (page_id),\n"
            . "CONSTRAINT redirects_page_fk FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public static function validTypes(): array
    {
        return [301, 302];
    }

    public function countFiltered(string $search = '', string $status = ''): int
    {
        [$where, $params] = $this->buildFilters($search, $status);
        return (int)$this->db->fetchValue(
            'SELECT COUNT(*) ' . self::FILTER_SOURCE . ($where !== '' ? ' WHERE ' . $where : ''),
            $params
        );
    }

    public function getFiltered(string $search = '', string $status = '', int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->buildFilters($search, $status);
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchArray(
            'SELECT r.*, p.page_title AS page_title '
            . self::FILTER_SOURCE
            . ($where !== '' ? ' WHERE ' . $where : '')
            . ' ORDER BY r.updated_at DESC, r.id DESC LIMIT ? OFFSET ?',
            $params
        );
    }

    public function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $rows = $this->db->fetchArray('SELECT * FROM redirects WHERE id = ? LIMIT 1', [$id]);
        return $rows[0] ?? null;
    }

    public function findBySource(string $sourcePath): ?array
    {
        $sourcePath = self::normalizeSourcePath($sourcePath);
        $rows = $this->db->fetchArray('SELECT * FROM redirects WHERE source_path = ? LIMIT 1', [$sourcePath]);
        return $rows[0] ?? null;
    }

    /**
     * Save an administrator-managed redirect. Editing an automatic redirect
     * turns it into a manual redirect because the administrator has taken
     * explicit ownership of the rule.
     */
    public function saveManual(?int $id, array $data): int
    {

        $source = self::normalizeSourcePath((string)($data['source_path'] ?? ''));
        $target = self::normalizeTargetUrl((string)($data['target_url'] ?? ''));
        $type = (int)($data['redirect_type'] ?? 301);
        $active = !empty($data['is_active']) ? 1 : 0;

        if (!in_array($type, self::validTypes(), true)) {
            throw new RuntimeException('Vælg enten 301 eller 302 som redirect-type.');
        }
        if ($active && $this->pageExistsAtPath($source)) {
            throw new RuntimeException('Kilde-URL’en bruges allerede af en side. Vælg en anden kilde-URL.');
        }
        if ($this->wouldLoop($source, $target, $id)) {
            throw new RuntimeException('Redirectet ville oprette en redirect-loop.');
        }

        $existingId = (int)($this->db->fetchValue(
            'SELECT id FROM redirects WHERE source_path = ?' . ($id && $id > 0 ? ' AND id <> ?' : '') . ' LIMIT 1',
            $id && $id > 0 ? [$source, $id] : [$source]
        ) ?? 0);
        if ($existingId > 0) {
            throw new RuntimeException('Der findes allerede et redirect med denne kilde-URL.');
        }

        if ($id !== null && $id > 0) {
            if (!$this->find($id)) {
                throw new RuntimeException('Redirectet findes ikke længere.');
            }
            $this->db->query(
                'UPDATE redirects SET source_path = ?, target_url = ?, redirect_type = ?, is_active = ?, is_automatic = 0, page_id = NULL, updated_at = NOW() WHERE id = ?',
                [$source, $target, $type, $active, $id]
            );
            return $id;
        }

        $this->db->query(
            'INSERT INTO redirects (source_path, target_url, redirect_type, is_active, is_automatic, page_id, hit_count, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, 0, NULL, 0, NOW(), NOW())',
            [$source, $target, $type, $active]
        );
        return $this->db->getInsertId();
    }

    public function delete(int $id): bool
    {
        if ($id < 1 || !$this->find($id)) {
            return false;
        }
        $this->db->query('DELETE FROM redirects WHERE id = ?', [$id]);
        return true;
    }

    /**
     * Make a page destination safe before publishing/moving a page there.
     * Old automatic rules are removed when a page returns to an earlier URL;
     * a manual active rule is never silently overwritten.
     */
    public function preparePageDestination(string $path): void
    {
        $path = self::normalizeSourcePath($path);
        $rows = $this->db->fetchArray('SELECT id, is_active, is_automatic FROM redirects WHERE source_path = ? LIMIT 1', [$path]);
        $redirect = $rows[0] ?? null;
        if (!$redirect || empty($redirect['is_active'])) {
            return;
        }
        if (!empty($redirect['is_automatic'])) {
            $this->db->query('DELETE FROM redirects WHERE id = ?', [(int)$redirect['id']]);
            return;
        }
        throw new RuntimeException('Den nye side-URL bruges af et aktivt manuelt redirect. Fjern eller deaktiver redirectet først.');
    }

    /**
     * Create/update an automatic permanent redirect for a moved published page.
     */
    public function registerAutomatic(string $sourcePath, string $targetUrl, int $pageId): void
    {
        $source = self::normalizeSourcePath($sourcePath);
        $target = self::normalizeTargetUrl($targetUrl);

        if ($source === self::targetPath($target)) {
            return;
        }
        if ($this->wouldLoop($source, $target, null)) {
            throw new RuntimeException('Den automatiske URL-ændring ville oprette en redirect-loop.');
        }

        $existing = $this->db->fetchArray('SELECT id, is_automatic FROM redirects WHERE source_path = ? LIMIT 1', [$source]);
        if ($existing) {
            $row = $existing[0];
            if (empty($row['is_automatic'])) {
                // A manual rule is explicit administrator intent and wins.
                return;
            }
            $this->db->query(
                'UPDATE redirects SET target_url = ?, redirect_type = 301, is_active = 1, page_id = ?, updated_at = NOW() WHERE id = ?',
                [$target, $pageId > 0 ? $pageId : null, (int)$row['id']]
            );
        } else {
            $this->db->query(
                'INSERT INTO redirects (source_path, target_url, redirect_type, is_active, is_automatic, page_id, hit_count, created_at, updated_at) '
                . 'VALUES (?, ?, 301, 1, 1, ?, 0, NOW(), NOW())',
                [$source, $target, $pageId > 0 ? $pageId : null]
            );
        }

        // Collapse older automatic redirects so repeated slug changes do not
        // build A -> B -> C chains. Manual redirects are never rewritten.
        $this->db->query(
            'UPDATE redirects SET target_url = ?, updated_at = NOW() '
            . 'WHERE is_automatic = 1 AND source_path <> ? AND target_url = ?',
            [$target, $source, $source]
        );
    }

    public function matchActive(string $requestPath): ?array
    {
        $path = self::normalizeRequestPath($requestPath);
        try {
            $rows = $this->db->fetchArray(
                'SELECT * FROM redirects WHERE source_path = ? AND is_active = 1 LIMIT 1',
                [$path]
            );
        } catch (Throwable $e) {
            // Upgrade compatibility: a V1.2.x database may not have the table
            // until the first request after deploying V1.3.0.
                $rows = $this->db->fetchArray(
                'SELECT * FROM redirects WHERE source_path = ? AND is_active = 1 LIMIT 1',
                [$path]
            );
        }
        if (!$rows) {
            return null;
        }

        // A real page always wins over a stale/imported redirect. This keeps
        // the frontend reachable even if redirect data was imported from a
        // different installation or an old rule was left behind.
        if ($this->pageExistsAtPath($path)) {
            return null;
        }

        return $rows[0];
    }

    public function registerHit(int $id): void
    {
        if ($id < 1) {
            return;
        }
        $this->db->query(
            'UPDATE redirects SET hit_count = hit_count + 1, last_hit_at = NOW() WHERE id = ?',
            [$id]
        );
    }

    public static function normalizeRequestPath(string $path): string
    {
        $path = (string)(parse_url($path, PHP_URL_PATH) ?? '/');
        $path = preg_replace('#/+#', '/', '/' . ltrim($path, '/')) ?: '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
    }

    public static function normalizeSourcePath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException('Angiv en kilde-URL.');
        }
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $value)) {
            throw new RuntimeException('Kilde-URL’en indeholder ugyldige tegn.');
        }
        if (str_contains($value, '://') || str_starts_with($value, '//')) {
            throw new RuntimeException('Kilde-URL’en skal være en intern sti, fx /gammel-side.');
        }
        $parts = parse_url($value);
        if ($parts === false || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('Kilde-URL’en må ikke indeholde query-parametre eller fragment.');
        }
        $path = self::normalizeRequestPath((string)($parts['path'] ?? '/'));
        self::assertPublicSourcePath($path);
        if (strlen($path) > 512) {
            throw new RuntimeException('Kilde-URL’en er for lang.');
        }
        return $path;
    }

    public static function normalizeTargetUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException('Angiv en destinations-URL.');
        }
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $value)) {
            throw new RuntimeException('Destinations-URL’en indeholder ugyldige tegn.');
        }
        if (strlen($value) > 2048) {
            throw new RuntimeException('Destinations-URL’en er for lang.');
        }

        if (str_starts_with($value, '/')) {
            if (str_starts_with($value, '//')) {
                throw new RuntimeException('Protocol-relative destinations-URL’er understøttes ikke.');
            }
            $parts = parse_url($value);
            if ($parts === false) {
                throw new RuntimeException('Destinations-URL’en er ugyldig.');
            }
            $path = self::normalizeRequestPath((string)($parts['path'] ?? '/'));
            if (strtolower($path) === '/forside') {
                $path = '/';
            }
            self::assertPublicTargetPath($path);
            $result = $path;
            if (isset($parts['query']) && $parts['query'] !== '') {
                $result .= '?' . $parts['query'];
            }
            if (isset($parts['fragment']) && $parts['fragment'] !== '') {
                $result .= '#' . $parts['fragment'];
            }
            return $result;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Destinations-URL’en skal være en intern sti eller en gyldig http(s)-URL.');
        }
        $parts = parse_url($value);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Kun http- og https-destinationer understøttes.');
        }
        return $value;
    }

    private function buildFilters(string $search, string $status): array
    {
        $where = [];
        $params = [];
        $status = strtolower(trim($status));
        if ($status === 'active') {
            $where[] = 'r.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'r.is_active = 0';
        } elseif ($status === 'automatic') {
            $where[] = 'r.is_automatic = 1';
        }

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(r.source_path LIKE ? OR r.target_url LIKE ? OR p.page_title LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    private function pageExistsAtPath(string $path): bool
    {
        $rows = $this->db->fetchArray('SELECT id, page_slug, parent_id FROM pages');
        if (!$rows) {
            return false;
        }
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['id']] = $row;
        }
        foreach ($byId as $row) {
            if ($this->pagePathFromMap($row, $byId) === $path) {
                return true;
            }
        }
        return false;
    }

    private function pagePathFromMap(array $page, array $byId): string
    {
        $slug = trim((string)($page['page_slug'] ?? ''));
        if ($slug === '' || $slug === 'forside') {
            return '/';
        }
        $segments = [$slug];
        $parentId = (int)($page['parent_id'] ?? 0);
        $visited = [];
        while ($parentId > 0 && isset($byId[$parentId]) && !isset($visited[$parentId])) {
            $visited[$parentId] = true;
            $parent = $byId[$parentId];
            $parentSlug = trim((string)($parent['page_slug'] ?? ''));
            if ($parentSlug !== '' && $parentSlug !== 'forside') {
                array_unshift($segments, $parentSlug);
            }
            $parentId = (int)($parent['parent_id'] ?? 0);
        }
        return '/' . implode('/', $segments);
    }

    private function wouldLoop(string $source, string $target, ?int $excludeId): bool
    {
        $targetPath = self::targetPath($target);
        if ($targetPath === null) {
            return false;
        }
        if ($targetPath === $source) {
            return true;
        }

        $visited = [$source => true];
        $current = $targetPath;
        for ($i = 0; $i < 20; $i++) {
            if (isset($visited[$current])) {
                return true;
            }
            $visited[$current] = true;
            $sql = 'SELECT id, target_url FROM redirects WHERE source_path = ? AND is_active = 1';
            $params = [$current];
            if ($excludeId !== null && $excludeId > 0) {
                $sql .= ' AND id <> ?';
                $params[] = $excludeId;
            }
            $sql .= ' LIMIT 1';
            $rows = $this->db->fetchArray($sql, $params);
            if (!$rows) {
                return false;
            }
            $next = self::targetPath((string)$rows[0]['target_url']);
            if ($next === null) {
                return false;
            }
            $current = $next;
        }
        return true;
    }

    private static function targetPath(string $target): ?string
    {
        if (!str_starts_with($target, '/')) {
            return null;
        }
        return self::normalizeRequestPath((string)(parse_url($target, PHP_URL_PATH) ?? '/'));
    }

    private static function assertPublicSourcePath(string $path): void
    {
        $lower = strtolower($path);
        foreach (['/admin', '/assets', '/incl'] as $prefix) {
            if ($lower === $prefix || str_starts_with($lower, $prefix . '/')) {
                throw new RuntimeException('Kilde-URL’en ligger i et reserveret CMS-område.');
            }
        }
        if (in_array($lower, ['/forside', '/index.php', '/robots.txt', '/sitemap.xml', '/sitemap-index.xml', '/site.webmanifest'], true)) {
            throw new RuntimeException('Denne system-URL kan ikke bruges som redirect-kilde.');
        }
    }

    private static function assertPublicTargetPath(string $path): void
    {
        $lower = strtolower($path);
        if ($lower === '/incl' || str_starts_with($lower, '/incl/')) {
            throw new RuntimeException('Destinations-URL’en peger på et internt CMS-område.');
        }
    }
}
