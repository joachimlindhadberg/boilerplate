<?php
/**
 * Pages model
 *
 * Public page lookup/navigation plus CMS CRUD helpers.
 */
class Pages
{
    protected Db $db;
    protected array $cache = [];
    protected ?array $publishedCache = null;

    private const SYSTEM_SLUGS = ['forside'];

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    public static function validStatuses(): array
    {
        return ['draft', 'published', 'protected'];
    }

    public static function systemSlugs(): array
    {
        return self::SYSTEM_SLUGS;
    }

    public static function isSystemSlug(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), self::SYSTEM_SLUGS, true);
    }

    public function findBySlug(string $slug): ?array
    {
        if (isset($this->cache[$slug])) {
            return $this->cache[$slug];
        }

        $result = $this->db->fetchArray('SELECT * FROM pages WHERE page_slug = ? LIMIT 1', [$slug]);
        if (!$result) {
            return null;
        }

        return $this->cache[$slug] = $result[0];
    }

    public function all(): array
    {
        return $this->db->fetchArray('SELECT * FROM pages ORDER BY page_title ASC') ?: [];
    }

    public function allPublished(): array
    {
        if ($this->publishedCache !== null) {
            return $this->publishedCache;
        }

        $rows = $this->db->fetchArray("SELECT * FROM pages WHERE page_status = 'published' ORDER BY menu_order ASC");
        return $this->publishedCache = $rows ?: [];
    }

    /**
     * Legacy create/upsert API retained for backwards compatibility.
     *
     * Uses an explicit lookup/update instead of MySQL's deprecated
     * VALUES(column) upsert syntax, keeping this compatible with modern MySQL
     * and MariaDB variants.
     */
    public function set(array $data): int
    {
        $fields = [
            'page_title', 'page_slug', 'parent_id', 'page_description',
            'page_content', 'content_mode', 'page_status', 'menu_order', 'show_in_menu',
            'meta_title', 'meta_description'
        ];

        $slug = trim((string)($data['page_slug'] ?? ''));
        if ($slug === '') {
            throw new InvalidArgumentException('page_slug is required.');
        }

        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $field === 'content_mode'
                ? ($data[$field] ?? 'legacy')
                : ($data[$field] ?? null);
        }

        $existing = $this->findBySlug($slug);
        if ($existing) {
            $assignments = implode(', ', array_map(
                static fn(string $field): string => $field . ' = ?',
                $fields
            ));
            $params = array_values($values);
            $params[] = (int)$existing['id'];
            $this->db->query('UPDATE pages SET ' . $assignments . ' WHERE id = ?', $params);
            $id = (int)$existing['id'];
        } else {
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $this->db->query(
                'INSERT INTO pages (' . implode(', ', $fields) . ') VALUES (' . $placeholders . ')',
                array_values($values)
            );
            $id = $this->db->getInsertId();
        }

        $this->clearCaches();
        return $id;
    }

    /** Resolve an exact frontend path, including its parent hierarchy. */
    public function findByPublicPath(string $requestPath): ?array
    {
        $path = (string)(parse_url($requestPath, PHP_URL_PATH) ?? '/');
        $path = preg_replace('#/+#', '/', '/' . ltrim($path, '/')) ?: '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== ''));
        $slug = $segments ? (string)end($segments) : 'forside';
        $page = $this->findBySlug($slug);
        if (!$page) {
            return null;
        }

        return $this->buildPublicUrl($page) === $path ? $page : null;
    }

    public function delete(int $id): void
    {
        $this->db->query('DELETE FROM pages WHERE id = ?', [$id]);
        $this->clearCaches();
    }

    /* ================================================================
     * Admin CRUD
     * ================================================================ */

    public function getStatusCounts(): array
    {
        $counts = ['all' => 0, 'published' => 0, 'draft' => 0, 'protected' => 0];
        foreach ($this->db->fetchArray('SELECT page_status, COUNT(*) AS total FROM pages GROUP BY page_status') as $row) {
            $status = (string)($row['page_status'] ?? '');
            $total = (int)($row['total'] ?? 0);
            if (array_key_exists($status, $counts)) {
                $counts[$status] = $total;
            }
            $counts['all'] += $total;
        }
        return $counts;
    }

    public function countFiltered(string $search = '', string $status = ''): int
    {
        [$where, $params] = $this->buildAdminFilters($search, $status);
        return (int)$this->db->fetchValue('SELECT COUNT(*) FROM pages p' . ($where !== '' ? ' WHERE ' . $where : ''), $params);
    }

    public function getFiltered(string $search = '', string $status = '', int $limit = 10, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->buildAdminFilters($search, $status);

        $sql = "SELECT p.*, s.publish_at_utc, s.unpublish_at_utc,
                       EXISTS(SELECT 1 FROM pages child WHERE child.parent_id = p.id) AS has_children,
                       parent.page_title AS parent_title,
                       parent.page_slug AS parent_slug
                FROM pages p
                LEFT JOIN page_schedules s ON s.page_id = p.id
                LEFT JOIN pages parent ON parent.id = p.parent_id"
             . ($where !== '' ? ' WHERE ' . $where : '')
             . " ORDER BY COALESCE(p.menu_order, 999999) ASC, p.page_title ASC, p.id ASC
                 LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        return $this->db->fetchArray($sql, $params);
    }

    public function findAdmin(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $rows = $this->db->fetchArray(
            'SELECT p.*, parent.page_title AS parent_title, parent.page_slug AS parent_slug, '
            . "TRIM(CONCAT_WS(' ', creator.firstname, creator.lastname)) AS created_by_name, "
            . "TRIM(CONCAT_WS(' ', editor.firstname, editor.lastname)) AS updated_by_name "
            . 'FROM pages p '
            . 'LEFT JOIN pages parent ON parent.id = p.parent_id '
            . 'LEFT JOIN users creator ON creator.id = p.created_by '
            . 'LEFT JOIN users editor ON editor.id = p.updated_by '
            . 'WHERE p.id = ? LIMIT 1',
            [$id]
        );
        return $rows[0] ?? null;
    }

    public function getParentOptions(?int $excludeId = null): array
    {
        $rows = $this->db->fetchArray('SELECT id, page_title, page_slug, parent_id FROM pages ORDER BY page_title ASC');
        if (!$excludeId || $excludeId < 1) {
            return $rows;
        }

        $blocked = [$excludeId => true];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $parentId = (int)($row['parent_id'] ?? 0);
                if ($parentId > 0 && isset($blocked[$parentId]) && !isset($blocked[$id])) {
                    $blocked[$id] = true;
                    $changed = true;
                }
            }
        }

        return array_values(array_filter($rows, static fn(array $row): bool => !isset($blocked[(int)$row['id']])));
    }

    public function createAdmin(array $data, bool $manageTransaction = true, ?int $actorId = null): int
    {
        $slug = $this->uniqueSlug((string)($data['page_slug'] ?? ''), (string)($data['page_title'] ?? 'side'));
        $status = in_array(($data['page_status'] ?? 'draft'), self::validStatuses(), true) ? $data['page_status'] : 'draft';
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        if ($parentId !== null && !$this->pageIdExists($parentId)) {
            throw new RuntimeException('Den valgte overordnede side findes ikke længere.');
        }
        $redirects = new Redirects($this->db);

        if ($manageTransaction) $this->db->beginTransaction();
        try {
            $menuOrder = (new AdminOrdering($this->db))->prepareInsert('pages', $data['menu_order'], $parentId);
            $this->db->query(
                'INSERT INTO pages (page_title, page_slug, parent_id, page_description, page_content, content_mode, page_status, menu_order, show_in_menu, created, updated, meta_title, meta_description, created_by, updated_by) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?)',
                [
                    $data['page_title'], $slug, $parentId,
                    $data['page_description'] ?: null, $data['page_content'], $this->normalizeContentMode($data['content_mode'] ?? 'builder'), $status,
                    $menuOrder, !empty($data['show_in_menu']) ? 1 : 0,
                    $data['meta_title'] ?: null, $data['meta_description'] ?: null,
                    $actorId !== null && $actorId > 0 ? $actorId : null,
                    $actorId !== null && $actorId > 0 ? $actorId : null,
                ]
            );
            $id = $this->db->getInsertId();
            $createdPage = $this->findAdmin($id);
            if ($createdPage) {
                $redirects->preparePageDestination($this->buildPublicUrl($createdPage));
            }
            if ($manageTransaction) $this->db->commit();
            $this->clearCaches();
            return $id;
        } catch (Throwable $e) {
            if ($manageTransaction) $this->db->rollback();
            throw $e;
        }
    }

    public function updateAdmin(int $id, array $data, bool $manageTransaction = true, ?int $actorId = null): bool
    {
        $existing = $this->findAdmin($id);
        if (!$existing) return false;
        $isSystem = self::isSystemSlug((string)$existing['page_slug']);
        $slug = $isSystem
            ? (string)$existing['page_slug']
            : $this->uniqueSlug((string)($data['page_slug'] ?? ''), (string)($data['page_title'] ?? 'side'), $id);
        $parentId = $isSystem
            ? (!empty($existing['parent_id']) ? (int)$existing['parent_id'] : null)
            : (!empty($data['parent_id']) ? (int)$data['parent_id'] : null);
        if (!$isSystem && $parentId !== null && !$this->parentIsAllowed($id, $parentId)) {
            throw new RuntimeException('Den valgte overordnede side ville oprette en ugyldig sidestruktur.');
        }
        $status = in_array(($data['page_status'] ?? 'draft'), self::validStatuses(), true) ? $data['page_status'] : 'draft';
        $redirects = new Redirects($this->db);

        $beforePaths = $this->subtreeUrlSnapshot($id);
        if ($manageTransaction) $this->db->beginTransaction();
        try {
            $menuOrder = (new AdminOrdering($this->db))->move('pages', $id, $data['menu_order'], $parentId);
            $this->db->query(
                'UPDATE pages SET page_title = ?, page_slug = ?, parent_id = ?, page_description = ?, page_content = ?, content_mode = ?, '
                . 'page_status = ?, menu_order = ?, show_in_menu = ?, meta_title = ?, meta_description = ?, updated = NOW(), updated_by = ? WHERE id = ?',
                [
                    $data['page_title'], $slug, $parentId,
                    $data['page_description'] ?: null, $data['page_content'], $this->normalizeContentMode($data['content_mode'] ?? ($existing['content_mode'] ?? 'legacy')), $status,
                    $menuOrder, !empty($data['show_in_menu']) ? 1 : 0,
                    $data['meta_title'] ?: null, $data['meta_description'] ?: null,
                    $actorId !== null && $actorId > 0 ? $actorId : null, $id,
                ]
            );
            $afterPaths = $this->subtreeUrlSnapshot($id);

            // A page (and its descendants) can move when either its slug or
            // parent changes. Reserve every new destination before creating
            // the old -> new redirects so existing manual rules are never
            // silently overwritten.
            foreach ($afterPaths as $pageId => $after) {
                $before = $beforePaths[$pageId] ?? null;
                if (!$before || (string)$before['url'] === (string)$after['url']) {
                    continue;
                }
                $redirects->preparePageDestination((string)$after['url']);
            }

            foreach ($afterPaths as $pageId => $after) {
                $before = $beforePaths[$pageId] ?? null;
                if (!$before || (string)$before['url'] === (string)$after['url']) {
                    continue;
                }
                if (($before['status'] ?? '') === 'published' && ($after['status'] ?? '') === 'published') {
                    $redirects->registerAutomatic((string)$before['url'], (string)$after['url'], (int)$pageId);
                }
            }

            if ($manageTransaction) $this->db->commit();
            $this->clearCaches();
            return true;
        } catch (Throwable $e) {
            if ($manageTransaction) $this->db->rollback();
            throw $e;
        }
    }

    public function deleteAdmin(int $id): array
    {
        $page = $this->findAdmin($id);
        if (!$page) return ['ok' => false, 'message' => 'Siden findes ikke længere.'];
        if (self::isSystemSlug((string)$page['page_slug'])) {
            return ['ok' => false, 'message' => 'Denne systemside kan ikke slettes permanent.'];
        }
        $children = (int)$this->db->fetchValue('SELECT COUNT(*) FROM pages WHERE parent_id = ?', [$id]);
        if ($children > 0) {
            return ['ok' => false, 'message' => 'Siden kan ikke slettes, fordi den har undersider. Flyt eller slet undersiderne først.', 'children' => $children];
        }
        $parentId = !empty($page['parent_id']) ? (int)$page['parent_id'] : null;
        $this->db->beginTransaction();
        try {
            $this->db->query('DELETE FROM pages WHERE id = ?', [$id]);
            (new AdminOrdering($this->db))->normalize('pages', $parentId);
            $this->db->commit();
            $this->clearCaches();
            return ['ok' => true, 'page' => $page];
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function childCount(int $id): int
    {
        return $id > 0 ? (int)$this->db->fetchValue('SELECT COUNT(*) FROM pages WHERE parent_id = ?', [$id]) : 0;
    }

    public function buildPublicUrl(array $page): string
    {
        $slug = trim((string)($page['page_slug'] ?? ''));
        if ($slug === 'forside') {
            return '/';
        }
        if ($slug === '') {
            return '/';
        }

        $segments = [$slug];
        $parentId = (int)($page['parent_id'] ?? 0);
        $visited = [];
        while ($parentId > 0 && !isset($visited[$parentId])) {
            $visited[$parentId] = true;
            $parent = $this->findAdmin($parentId);
            if (!$parent) {
                break;
            }
            $parentSlug = trim((string)($parent['page_slug'] ?? ''));
            if ($parentSlug !== '' && $parentSlug !== 'forside') {
                array_unshift($segments, $parentSlug);
            }
            $parentId = (int)($parent['parent_id'] ?? 0);
        }
        return '/' . implode('/', $segments);
    }

    /**
     * Snapshot public URLs for a page subtree. Parent URL changes alter every
     * descendant path, so redirects must be generated for the whole subtree.
     */
    private function subtreeUrlSnapshot(int $rootId): array
    {
        $rows = $this->db->fetchArray('SELECT id, page_slug, parent_id, page_status FROM pages');
        $byId = [];
        $children = [];
        foreach ($rows as $row) {
            $rowId = (int)$row['id'];
            $byId[$rowId] = $row;
            $parentId = (int)($row['parent_id'] ?? 0);
            $children[$parentId][] = $rowId;
        }
        if (!isset($byId[$rootId])) {
            return [];
        }

        $ids = [];
        $stack = [$rootId];
        while ($stack) {
            $current = (int)array_pop($stack);
            if (isset($ids[$current])) {
                continue;
            }
            $ids[$current] = true;
            foreach ($children[$current] ?? [] as $childId) {
                $stack[] = (int)$childId;
            }
        }

        $snapshot = [];
        foreach (array_keys($ids) as $pageId) {
            $snapshot[$pageId] = [
                'url' => $this->pagePathFromMap($byId[$pageId], $byId),
                'status' => (string)($byId[$pageId]['page_status'] ?? 'draft'),
            ];
        }
        return $snapshot;
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

    private function buildAdminFilters(string $search, string $status): array
    {
        $where = [];
        $params = [];
        if (in_array($status, self::validStatuses(), true)) {
            $where[] = 'p.page_status = ?';
            $params[] = $status;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(p.page_title LIKE ? OR p.page_slug LIKE ? OR p.page_description LIKE ? OR p.meta_title LIKE ? OR p.meta_description LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    private function pageIdExists(int $id): bool
    {
        return $id > 0 && (int)$this->db->fetchValue('SELECT COUNT(*) FROM pages WHERE id = ?', [$id]) > 0;
    }

    private function parentIsAllowed(int $id, int $parentId): bool
    {
        if ($parentId < 1 || $id === $parentId) {
            return false;
        }
        foreach ($this->getParentOptions($id) as $row) {
            if ((int)$row['id'] === $parentId) {
                return true;
            }
        }
        return false;
    }

    private function uniqueSlug(string $requested, string $fallback, ?int $excludeId = null): string
    {
        $base = $this->slugify($requested !== '' ? $requested : $fallback);
        if ($base === '') {
            $base = 'side';
        }
        $base = rtrim(substr($base, 0, 240), '-');
        $candidate = $base;
        $suffix = 2;
        while ($this->slugExists($candidate, $excludeId)) {
            $suffixText = '-' . $suffix++;
            $candidate = rtrim(substr($base, 0, 250 - strlen($suffixText)), '-') . $suffixText;
        }
        return $candidate;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null && $excludeId > 0) {
            return (int)$this->db->fetchValue('SELECT COUNT(*) FROM pages WHERE page_slug = ? AND id <> ?', [$slug, $excludeId]) > 0;
        }
        return (int)$this->db->fetchValue('SELECT COUNT(*) FROM pages WHERE page_slug = ?', [$slug]) > 0;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        $value = strtr($value, [
            'Æ'=>'Ae','Ø'=>'O','Å'=>'Aa','æ'=>'ae','ø'=>'o','å'=>'aa',
            'Ä'=>'A','Ö'=>'O','Ü'=>'U','ä'=>'a','ö'=>'o','ü'=>'u',
            'É'=>'E','È'=>'E','Ê'=>'E','é'=>'e','è'=>'e','ê'=>'e',
        ]);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function normalizeContentMode(string $mode): string
    {
        return $mode === 'builder' ? 'builder' : 'legacy';
    }

    private function clearCaches(): void
    {
        $this->cache = [];
        $this->publishedCache = null;
    }

    public function get404Content(): string
    {
        return '<section class="section theme-light"><div class="container"><div class="block"><p class="block__kicker">404</p><h1 class="block__title">Siden blev ikke fundet.</h1><div class="block__content rich-text"><p>Den side, du leder efter, findes ikke længere eller adressen er forkert.</p><p><a class="text-link" href="/">Gå til forsiden <span aria-hidden="true">→</span></a></p></div></div></div></section>';
    }
}
