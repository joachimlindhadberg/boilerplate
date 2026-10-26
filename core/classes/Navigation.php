<?php
/**
 * Core Navigation Manager. Menus are independent of the pages' URL hierarchy.
 * Page links are stored by id and resolved when rendered, never as cached slugs.
 */
class Navigation
{
    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    public function menus(): array
    {
        $fetch = fn(): array => $this->db->fetchArray('SELECT m.*, (SELECT COUNT(*) FROM navigation_items i WHERE i.menu_id = m.id) AS item_count FROM navigation_menus m ORDER BY CASE m.slug WHEN \'primary\' THEN 0 WHEN \'footer\' THEN 1 ELSE 2 END, m.id');
        return Db::transactionActive() ? $fetch() : RequestCache::remember('navigation', 'menus', $fetch);
    }

    public function menu(int $id): ?array
    {
        $fetch = fn(): ?array => $this->db->fetchArray('SELECT * FROM navigation_menus WHERE id = ? LIMIT 1', [$id])[0] ?? null;
        return Db::transactionActive() ? $fetch() : RequestCache::remember('navigation', 'menu:' . $id, $fetch);
    }

    public function bySlug(string $slug): ?array
    {
        $fetch = fn(): ?array => $this->db->fetchArray('SELECT * FROM navigation_menus WHERE slug = ? LIMIT 1', [$slug])[0] ?? null;
        return Db::transactionActive() ? $fetch() : RequestCache::remember('navigation', 'slug:' . $slug, $fetch);
    }

    public function createMenu(string $name): int
    {
        $name = trim($name);
        if ($name === '' || $this->length($name) > 120) {
            throw new InvalidArgumentException('Menunavnet skal være mellem 1 og 120 tegn.');
        }
        $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : false;
        $base = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $ascii ?: $name)), '-');
        $base = substr($base ?: 'menu', 0, 90);
        $slug = $base;
        $number = 2;
        while ($this->bySlug($slug)) {
            $slug = $base . '-' . $number++;
        }
        $this->db->query('INSERT INTO navigation_menus (name, slug) VALUES (?, ?)', [$name, $slug]);
        return $this->db->getInsertId();
    }

    public function renameMenu(int $id, string $name): void
    {
        $name = trim($name);
        if (!$this->menu($id) || $name === '' || $this->length($name) > 120) {
            throw new InvalidArgumentException('Ugyldigt menunavn.');
        }
        $this->db->query('UPDATE navigation_menus SET name = ? WHERE id = ?', [$name, $id]);
    }

    public function deleteMenu(int $id): void
    {
        $menu = $this->menu($id);
        if (!$menu) {
            throw new InvalidArgumentException('Menuen findes ikke.');
        }
        $assigned = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM settings WHERE setting_key IN ('navigation_header_menu', 'navigation_footer_menu') AND setting_value = ?",
            [(string)$menu['slug']]
        );
        if ($assigned > 0) {
            throw new InvalidArgumentException('Menuen bruges i header eller footer. Vælg en anden menu til placeringen, før du sletter den.');
        }
        $this->db->query('DELETE FROM navigation_menus WHERE id = ?', [$id]);
    }

    public function items(int $menuId): array
    {
        $fetch = fn(): array => $this->db->fetchArray(
            'SELECT i.*, p.page_title, p.page_status, p.page_slug, p.parent_id AS page_parent_id
             FROM navigation_items i LEFT JOIN pages p ON p.id = i.page_id
             WHERE i.menu_id = ? ORDER BY i.sort_order ASC, i.id ASC', [$menuId]
        );
        return Db::transactionActive() ? $fetch() : RequestCache::remember('navigation', 'items:' . $menuId, $fetch);
    }

    public function item(int $itemId, int $menuId): ?array
    {
        return $this->db->fetchArray('SELECT * FROM navigation_items WHERE id = ? AND menu_id = ? LIMIT 1', [$itemId, $menuId])[0] ?? null;
    }

    /** Create/update a menu item and its level in one transaction. */
    public function saveItem(int $menuId, int $itemId, array $input): int
    {
        if (!$this->menu($menuId)) {
            throw new InvalidArgumentException('Menuen findes ikke.');
        }
        $type = (string)($input['type'] ?? 'page');
        if (!in_array($type, ['page', 'url'], true)) {
            throw new InvalidArgumentException('Ukendt linktype.');
        }
        $pageId = $type === 'page' ? (int)($input['page_id'] ?? 0) : null;
        $url = $type === 'url' ? trim((string)($input['url'] ?? '')) : null;
        if ($type === 'page' && !$this->db->fetchValue('SELECT id FROM pages WHERE id = ?', [$pageId])) {
            throw new InvalidArgumentException('Vælg en eksisterende side.');
        }
        if ($type === 'url' && !self::validUrl((string)$url)) {
            throw new InvalidArgumentException('Angiv en sikker intern sti eller en http(s)-adresse.');
        }
        $label = trim((string)($input['label'] ?? ''));
        if ($this->length($label) > 120) {
            throw new InvalidArgumentException('Menuteksten må højst være 120 tegn.');
        }
        if ($type === 'url' && $label === '') {
            throw new InvalidArgumentException('Egne links skal have en menutekst.');
        }
        $existing = $itemId > 0 ? $this->item($itemId, $menuId) : null;
        if ($itemId > 0 && !$existing) {
            throw new InvalidArgumentException('Menupunktet findes ikke i denne menu.');
        }
        $parentRaw = $input['parent_id'] ?? ($existing['parent_id'] ?? 0);
        $parentId = (int)$parentRaw;
        if ($parentId < 0) throw new InvalidArgumentException('Ugyldig placering.');
        $newParent = $parentId === 0 ? null : $parentId;
        $oldParent = $existing ? ($existing['parent_id'] === null ? null : (int)$existing['parent_id']) : null;
        if ($newParent !== null) {
            $parent = $this->item($newParent, $menuId);
            if (!$parent || $parent['parent_id'] !== null || $newParent === $itemId) {
                throw new InvalidArgumentException('Vælg et gyldigt hovedpunkt til undermenuen.');
            }
        }
        if ($existing && $newParent !== $oldParent && $newParent !== null &&
            $this->db->fetchValue('SELECT id FROM navigation_items WHERE menu_id=? AND parent_id=? LIMIT 1', [$menuId, $itemId])) {
            throw new InvalidArgumentException('Et punkt med undermenuer kan ikke selv blive en undermenu.');
        }
        $visible = !empty($input['is_visible']) ? 1 : 0;
        $target = !empty($input['new_tab']) ? '_blank' : '_self';
        $this->db->beginTransaction();
        try {
            $positionChanged = $existing && $oldParent !== $newParent;
            $order = $existing && !$positionChanged ? (int)$existing['sort_order'] : (int)$this->db->fetchValue(
                $newParent === null
                    ? 'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM navigation_items WHERE menu_id = ? AND parent_id IS NULL'
                    : 'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM navigation_items WHERE menu_id = ? AND parent_id = ?',
                $newParent === null ? [$menuId] : [$menuId, $newParent]
            );
            if ($existing) {
                $this->db->query('UPDATE navigation_items SET type=?, page_id=?, url=?, label=?, target=?, is_visible=?, parent_id=?, sort_order=? WHERE id=? AND menu_id=?',
                    [$type, $pageId, $url, $label, $target, $visible, $newParent, $order, $itemId, $menuId]);
                if ($positionChanged) $this->normalize($menuId, $oldParent);
                $result = $itemId;
            } else {
                $this->db->query('INSERT INTO navigation_items (menu_id, parent_id, type, page_id, url, label, target, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$menuId, $newParent, $type, $pageId, $url, $label, $target, $visible, $order]);
                $result = $this->db->getInsertId();
            }
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /** Save the complete menu editor in one database transaction. Stable item IDs are preserved. */
    public function saveEditor(int $menuId, string $name, string $placement, array $submitted): void
    {
        $menu = $this->menu($menuId);
        $name = trim($name);
        if (!$menu || $name === '' || $this->length($name) > 120) {
            throw new InvalidArgumentException('Angiv et menunavn på højst 120 tegn.');
        }
        if (!in_array($placement, ['none', 'header', 'footer', 'both'], true)) {
            throw new InvalidArgumentException('Ugyldig menuplacering.');
        }
        $stored = $this->items($menuId);
        $existing = [];
        foreach ($stored as $row) $existing[(string)$row['id']] = $row;
        if (count($submitted) > 500) throw new InvalidArgumentException('En menu kan højst have 500 menupunkter.');
        $normalized = [];
        foreach ($submitted as $key => $row) {
            if (!is_array($row) || !preg_match('/^(?:[1-9][0-9]*|new_[a-zA-Z0-9_]+)$/D', (string)$key)) {
                throw new InvalidArgumentException('Ugyldigt menupunkt.');
            }
            $key = (string)$key;
            if (!str_starts_with($key, 'new_') && !isset($existing[$key])) {
                throw new InvalidArgumentException('Menupunktet tilhører ikke denne menu.');
            }
            $deleted = !empty($row['deleted']);
            $parent = trim((string)($row['parent_id'] ?? '0'));
            if ($parent === '') $parent = '0';
            $type = (string)($row['type'] ?? 'page');
            $pageId = (int)($row['page_id'] ?? 0);
            $url = trim((string)($row['url'] ?? ''));
            $label = trim((string)($row['label'] ?? ''));
            if (!$deleted) {
                if (!in_array($type, ['page', 'url'], true)) throw new InvalidArgumentException('Ugyldig linktype.');
                if ($type === 'page' && !$this->db->fetchValue('SELECT id FROM pages WHERE id=?', [$pageId])) {
                    throw new InvalidArgumentException('Vælg en eksisterende side for menupunktet.');
                }
                if ($type === 'url' && !self::validUrl($url)) {
                    throw new InvalidArgumentException('Angiv en gyldig intern sti eller http(s)-adresse.');
                }
                if ($this->length($label) > 120 || ($type === 'url' && $label === '')) {
                    throw new InvalidArgumentException('Menuteksten skal udfyldes for egne links og højst være 120 tegn.');
                }
            }
            $normalized[$key] = ['deleted' => $deleted, 'parent' => $parent, 'type' => $type,
                'page_id' => $pageId, 'url' => $url, 'label' => $label,
                'visible' => !empty($row['is_visible']) ? 1 : 0,
                'target' => !empty($row['new_tab']) ? '_blank' : '_self'];
        }
        // Require the complete original set, so an incomplete POST cannot silently erase data.
        foreach ($existing as $id => $_) {
            if (!array_key_exists($id, $normalized)) throw new InvalidArgumentException('Menuen er ændret siden siden blev indlæst. Genindlæs og prøv igen.');
        }
        foreach ($normalized as $key => $row) {
            if ($row['deleted']) continue;
            $parent = $row['parent'];
            if ($parent === '0') continue;
            if ($parent === $key || !isset($normalized[$parent]) || $normalized[$parent]['deleted'] ||
                $normalized[$parent]['parent'] !== '0') {
                throw new InvalidArgumentException('Undermenuer skal høre til et aktivt menupunkt på hovedniveau.');
            }
        }
        $this->db->beginTransaction();
        try {
            $this->db->query('UPDATE navigation_menus SET name=? WHERE id=?', [$name, $menuId]);
            // Detach all parents before deletion to avoid referencing a deleted parent.
            $this->db->query('UPDATE navigation_items SET parent_id=NULL WHERE menu_id=?', [$menuId]);
            foreach ($normalized as $key => $row) {
                if ($row['deleted'] && isset($existing[$key])) {
                    $this->db->query('DELETE FROM navigation_items WHERE id=? AND menu_id=?', [(int)$key, $menuId]);
                }
            }
            $ids = [];
            foreach ($normalized as $key => $row) {
                if ($row['deleted']) continue;
                $pageId = $row['type'] === 'page' ? $row['page_id'] : null;
                $url = $row['type'] === 'url' ? $row['url'] : null;
                if (isset($existing[$key])) {
                    $this->db->query('UPDATE navigation_items SET type=?,page_id=?,url=?,label=?,target=?,is_visible=? WHERE id=? AND menu_id=?',
                        [$row['type'], $pageId, $url, $row['label'], $row['target'], $row['visible'], (int)$key, $menuId]);
                    $ids[$key] = (int)$key;
                } else {
                    $this->db->query('INSERT INTO navigation_items (menu_id,parent_id,type,page_id,url,label,target,is_visible,sort_order) VALUES (?,NULL,?,?,?,?,?,?,0)',
                        [$menuId, $row['type'], $pageId, $url, $row['label'], $row['target'], $row['visible']]);
                    $ids[$key] = $this->db->getInsertId();
                }
            }
            $order = [];
            foreach ($normalized as $key => $row) {
                if ($row['deleted']) continue;
                $parent = $row['parent'];
                $order[$parent] = ($order[$parent] ?? 0) + 1;
                $this->db->query('UPDATE navigation_items SET parent_id=?,sort_order=? WHERE id=? AND menu_id=?',
                    [$parent === '0' ? null : $ids[$parent], $order[$parent] - 1, $ids[$key], $menuId]);
            }
            // One menu can occupy both locations, or neither. Other assignments are preserved.
            foreach (['header' => 'navigation_header_menu', 'footer' => 'navigation_footer_menu'] as $location => $settingKey) {
                $assigned = $this->db->fetchValue('SELECT setting_value FROM settings WHERE setting_key=?', [$settingKey]);
                $wants = $placement === 'both' || $placement === $location;
                if ($wants) $newSlug = (string)$menu['slug'];
                elseif ($assigned !== (string)$menu['slug']) continue;
                else $newSlug = ''; // Explicitly leave this placement unused.
                if ($assigned === null) {
                    $this->db->query('INSERT INTO settings (setting_key,setting_value,autoload) VALUES (?,?,1)', [$settingKey, $newSlug]);
                } else {
                    $this->db->query('UPDATE settings SET setting_value=? WHERE setting_key=?', [$newSlug, $settingKey]);
                }
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public static function validUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x1F\x7F\\\\]/', $url)) {
            return false;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        if (str_starts_with($url, '#') && strlen($url) > 1) {
            return true;
        }
        $parts = parse_url($url);
        return $parts !== false && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
            && !preg_match('/\s/', $url);
    }

    public function removeItem(int $menuId, int $id): void
    {
        $item = $this->item($id, $menuId);
        if (!$item) throw new InvalidArgumentException('Menupunktet findes ikke.');
        $this->db->beginTransaction();
        try {
            // Preserve descendants instead of deleting them when a parent is removed.
            $this->db->query('UPDATE navigation_items SET parent_id = ? WHERE menu_id = ? AND parent_id = ?',
                [$item['parent_id'], $menuId, $id]);
            $this->db->query('DELETE FROM navigation_items WHERE id = ? AND menu_id = ?', [$id, $menuId]);
            $this->normalize($menuId, $item['parent_id'] === null ? null : (int)$item['parent_id']);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * before/after: drop relative to target on target's level.
     * inside: make source a child of a root-level target (two levels max).
     * root: outdent a child to the root level.
     */
    public function moveItem(int $menuId, int $id, string $direction, int $targetId = 0): void
    {
        $source = $this->item($id, $menuId);
        if (!$source) throw new InvalidArgumentException('Menupunktet findes ikke.');
        $oldParent = $source['parent_id'] === null ? null : (int)$source['parent_id'];
        if ($direction === 'root') {
            $newParent = null;
            $position = PHP_INT_MAX;
        } else {
            $target = $this->item($targetId, $menuId);
            if (!$target || $targetId === $id) throw new InvalidArgumentException('Ugyldigt mål for flytning.');
            if ($direction === 'inside') {
                if ($target['parent_id'] !== null) throw new InvalidArgumentException('Der understøttes højst to menuniveauer.');
                $newParent = $targetId;
                $position = PHP_INT_MAX;
            } elseif (in_array($direction, ['before', 'after'], true)) {
                $newParent = $target['parent_id'] === null ? null : (int)$target['parent_id'];
                $position = $targetId;
            } else {
                throw new InvalidArgumentException('Ugyldig flytning.');
            }
        }
        if ($newParent === $id) throw new InvalidArgumentException('Et punkt kan ikke være sin egen forælder.');
        if ($newParent !== null && $this->db->fetchValue('SELECT id FROM navigation_items WHERE menu_id=? AND parent_id=? LIMIT 1', [$menuId, $id])) {
            throw new InvalidArgumentException('Flyt først undermenuerne, før dette punkt kan rykkes ind.');
        }
        // Never move a root item relative to one of its own children.
        if ($newParent !== null && (int)$newParent === $id) throw new InvalidArgumentException('Ugyldigt hierarki.');
        $this->db->beginTransaction();
        try {
            $this->db->query('UPDATE navigation_items SET parent_id = ? WHERE id = ? AND menu_id = ?', [$newParent, $id, $menuId]);
            $all = $this->items($menuId);
            $siblings = array_values(array_filter($all, static fn(array $row): bool =>
                ($row['parent_id'] === null ? null : (int)$row['parent_id']) === $newParent && (int)$row['id'] !== $id));
            $ids = array_map(static fn(array $row): int => (int)$row['id'], $siblings);
            $insertAt = count($ids);
            if ($position !== PHP_INT_MAX) {
                $index = array_search($position, $ids, true);
                if ($index === false) throw new InvalidArgumentException('Flyttemålet findes ikke på det valgte niveau.');
                $insertAt = $index + ($direction === 'after' ? 1 : 0);
            }
            array_splice($ids, $insertAt, 0, [$id]);
            foreach ($ids as $sort => $siblingId) {
                $this->db->query('UPDATE navigation_items SET sort_order = ? WHERE id = ? AND menu_id = ?', [$sort, $siblingId, $menuId]);
            }
            if ($oldParent !== $newParent) $this->normalize($menuId, $oldParent);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function shiftItem(int $menuId, int $id, int $step): void
    {
        if (!in_array($step, [-1, 1], true)) throw new InvalidArgumentException('Ugyldig retning.');
        $item = $this->item($id, $menuId);
        if (!$item) throw new InvalidArgumentException('Menupunktet findes ikke.');
        $siblings = array_values(array_filter($this->items($menuId), static fn(array $row): bool =>
            ($row['parent_id'] === null ? null : (int)$row['parent_id']) === ($item['parent_id'] === null ? null : (int)$item['parent_id'])));
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $siblings);
        $index = array_search($id, $ids, true);
        $next = $index + $step;
        if ($next < 0 || $next >= count($ids)) return;
        $this->moveItem($menuId, $id, $step < 0 ? 'before' : 'after', $ids[$next]);
    }

    private function normalize(int $menuId, ?int $parentId): void
    {
        $sql = 'SELECT id FROM navigation_items WHERE menu_id = ? AND parent_id IS ';
        $rows = $parentId === null
            ? $this->db->fetchArray($sql . 'NULL ORDER BY sort_order,id', [$menuId])
            : $this->db->fetchArray('SELECT id FROM navigation_items WHERE menu_id = ? AND parent_id = ? ORDER BY sort_order,id', [$menuId, $parentId]);
        foreach ($rows as $i => $row) $this->db->query('UPDATE navigation_items SET sort_order=? WHERE id=?', [$i, (int)$row['id']]);
    }

    /** Public tree, excludes hidden/draft/missing links and their descendants. */
    public function publicTree(string $slug, Pages $pages): ?array
    {
        $menu = $this->bySlug($slug);
        if (!$menu) return []; // Missing menu is empty; only missing tables trigger legacy fallback.
        $items = $this->items((int)$menu['id']);
        $byParent = [];
        foreach ($items as $item) {
            if (!(int)$item['is_visible']) continue;
            if ($item['type'] === 'page') {
                if (empty($item['page_id']) || ($item['page_status'] ?? '') !== 'published') continue;
                $url = $pages->buildPublicUrl([
                    'page_slug' => $item['page_slug'], 'parent_id' => $item['page_parent_id'],
                ]);
                $item['label'] = trim((string)$item['label']) ?: (string)$item['page_title'];
            } else {
                $url = (string)$item['url'];
                if (!self::validUrl($url)) continue;
            }
            $item['href'] = $url;
            $byParent[(int)($item['parent_id'] ?? 0)][] = $item;
        }
        $walk = static function (int $parent, int $depth) use (&$walk, $byParent): array {
            if ($depth > 2) return [];
            $result = [];
            foreach ($byParent[$parent] ?? [] as $item) {
                $item['children'] = $walk((int)$item['id'], $depth + 1);
                $result[] = $item;
            }
            return $result;
        };
        return $walk(0, 1);
    }

    private function length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}
