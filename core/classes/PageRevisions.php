<?php
/**
 * Immutable snapshots of CMS pages, including inactive Page Builder blocks.
 * Schema is installed by database/migrations/20261012_page_revisions_v1_8_0.sql.
 * Caller owns a shared Db transaction for any create/save/restore operation.
 */
class PageRevisions
{
    private Db $db;
    private PageBlocks $blocks;

    public const FIELDS = [
        'page_title', 'page_slug', 'parent_id', 'page_description',
        'page_content', 'content_mode', 'page_status', 'menu_order',
        'show_in_menu', 'meta_title', 'meta_description',
    ];

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
        $this->blocks = new PageBlocks($this->db);
    }

    /** Capture the persisted page and its blocks, never untrusted POST values. */
    public function snapshot(int $pageId): array
    {
        $rows = $this->db->fetchArray('SELECT * FROM pages WHERE id = ? LIMIT 1', [$pageId]);
        if (!$rows) {
            throw new RuntimeException('Siden findes ikke længere.');
        }
        $page = [];
        foreach (self::FIELDS as $field) {
            $page[$field] = $rows[0][$field] ?? null;
        }
        // Keep null and integer semantics stable for round-trip restoration.
        foreach (['parent_id', 'menu_order'] as $field) {
            $page[$field] = $page[$field] === null ? null : (int)$page[$field];
        }
        $page['show_in_menu'] = (int)$page['show_in_menu'];
        $blocks = [];
        foreach ($this->blocks->getForPage($pageId) as $row) {
            $blocks[] = [
                'type' => (string)$row['block_type'],
                'status' => (string)$row['status'],
                'data' => $row['data'],
            ];
        }
        return ['page' => $page, 'blocks' => $blocks];
    }

    /** Returns new revision ID; identical consecutive snapshots are not duplicated. */
    public function record(int $pageId, ?int $userId, string $action = 'save', ?array $snapshot = null): int
    {
        if (!in_array($action, ['baseline', 'create', 'save', 'restore', 'scheduled_publish', 'scheduled_unpublish'], true)) {
            throw new InvalidArgumentException('Invalid revision action.');
        }
        $snapshot ??= $this->snapshot($pageId);
        self::validateSnapshot($snapshot);
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $json);
        $latest = $this->db->fetchArray(
            'SELECT id, version_number, snapshot_hash FROM page_revisions WHERE page_id = ? ORDER BY version_number DESC LIMIT 1',
            [$pageId]
        );
        if ($latest && hash_equals((string)$latest[0]['snapshot_hash'], $hash)) {
            return (int)$latest[0]['id'];
        }
        $next = $latest ? ((int)$latest[0]['version_number'] + 1) : 1;
        $this->db->query(
            'INSERT INTO page_revisions (page_id, version_number, action, snapshot, snapshot_hash, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$pageId, $next, $action, $json, $hash, $userId && $userId > 0 ? $userId : null]
        );
        return $this->db->getInsertId();
    }

    public function count(int $pageId): int
    {
        return (int)$this->db->fetchValue('SELECT COUNT(*) FROM page_revisions WHERE page_id = ?', [$pageId]);
    }

    public function listForPage(int $pageId, int $limit = 10, int $offset = 0): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        return $this->db->fetchArray(
            'SELECT r.id, r.page_id, r.version_number, r.action, r.created_at, r.created_by, '
            . 'u.firstname, u.lastname FROM page_revisions r '
            . 'LEFT JOIN users u ON u.id = r.created_by WHERE r.page_id = ? '
            . 'ORDER BY r.version_number DESC LIMIT ? OFFSET ?',
            [$pageId, $limit, $offset]
        );
    }

    public function getForPage(int $pageId, int $revisionId): ?array
    {
        if ($pageId < 1 || $revisionId < 1) return null;
        $rows = $this->db->fetchArray(
            'SELECT r.id, r.page_id, r.version_number, r.action, r.snapshot, r.created_at, r.created_by, '
            . 'u.firstname, u.lastname FROM page_revisions r '
            . 'LEFT JOIN users u ON u.id = r.created_by WHERE r.page_id = ? AND r.id = ? LIMIT 1',
            [$pageId, $revisionId]
        );
        if (!$rows) return null;
        $revision = $rows[0];
        $snapshot = json_decode((string)$revision['snapshot'], true, 512, JSON_THROW_ON_ERROR);
        self::validateSnapshot($snapshot);
        $revision['data'] = $snapshot;
        unset($revision['snapshot']);
        return $revision;
    }

    /**
     * Restore a revision as a new version. Never delete or overwrite old revisions.
     * Shared Db + disabled inner transactions guarantee all-or-nothing updates.
     */
    public function restore(int $pageId, int $revisionId, ?int $userId): int
    {
        $this->db->beginTransaction();
        try {
            // Serialize saves/restores per page so version numbers remain unique.
            $locked = $this->db->fetchArray('SELECT id FROM pages WHERE id = ? FOR UPDATE', [$pageId]);
            if (!$locked) throw new RuntimeException('Siden findes ikke længere.');
            $revision = $this->getForPage($pageId, $revisionId);
            if (!$revision) throw new RuntimeException('Den valgte version findes ikke for denne side.');
            $desired = $revision['data'];
            $previous = $this->snapshot($pageId);
            if ($this->count($pageId) === 0) {
                $this->record($pageId, $userId, 'baseline', $previous);
            } else {
                // Capture any changes made through older API paths before overwriting.
                $this->record($pageId, $userId, 'save', $previous);
            }
            $pages = new Pages($this->db);
            if (!$pages->updateAdmin($pageId, $desired['page'], false, $userId)) {
                throw new RuntimeException('Siden findes ikke længere.');
            }
            // An occupied old slug must not silently become "slug-2" on restore.
            $stored = $this->db->fetchArray('SELECT page_slug, parent_id FROM pages WHERE id = ?', [$pageId]);
            if (!$stored || (string)$stored[0]['page_slug'] !== (string)$desired['page']['page_slug']
                || (int)($stored[0]['parent_id'] ?? 0) !== (int)($desired['page']['parent_id'] ?? 0)) {
                throw new RuntimeException('Den gamle URL eller overordnede side kan ikke gendannes. Kontrollér sidehierarkiet og eventuelle slug-konflikter.');
            }
            $this->blocks->replaceForPage($pageId, $desired['blocks'], false);
            // Restoring content is an explicit editorial decision: cancel pending jobs.
            (new PageScheduler($this->db))->saveForPage($pageId, null, null);
            $id = $this->record($pageId, $userId, 'restore');
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public static function validateSnapshot(mixed $snapshot): void
    {
        if (!is_array($snapshot) || !is_array($snapshot['page'] ?? null) || !is_array($snapshot['blocks'] ?? null)) {
            throw new RuntimeException('Versionsdata er ufuldstændige.');
        }
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $snapshot['page'])) {
                throw new RuntimeException('Versionsdata mangler et påkrævet sidefelt.');
            }
        }
        foreach ($snapshot['blocks'] as $block) {
            if (!is_array($block) || !is_string($block['type'] ?? null)
                || !array_key_exists((string)$block['type'], PageBlocks::definitions())
                || !in_array($block['status'] ?? null, ['active', 'hidden'], true)
                || !is_array($block['data'] ?? null)) {
                throw new RuntimeException('Versionen indeholder en sektion, som ikke understøttes af den nuværende Page Builder.');
            }
        }
    }
}
