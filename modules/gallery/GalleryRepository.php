<?php

declare(strict_types=1);

final class GalleryRepository
{
    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    public function ensureSchema(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS module_galleries (\n"
            . "id int NOT NULL AUTO_INCREMENT,\n"
            . "name varchar(191) NOT NULL,\n"
            . "description varchar(2000) NOT NULL DEFAULT '',\n"
            . "status varchar(20) NOT NULL DEFAULT 'active',\n"
            . "lightbox_enabled tinyint(1) NOT NULL DEFAULT 0,\n"
            . "created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY galleries_status (status),\n"
            . "KEY galleries_name (name)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS module_gallery_items (\n"
            . "id int NOT NULL AUTO_INCREMENT,\n"
            . "gallery_id int NOT NULL,\n"
            . "media_id int NOT NULL,\n"
            . "sort_order int NOT NULL DEFAULT 0,\n"
            . "created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY gallery_media (gallery_id, media_id),\n"
            . "KEY gallery_items_sort (gallery_id, sort_order),\n"
            . "KEY gallery_items_media (media_id),\n"
            . "CONSTRAINT module_gallery_items_gallery_fk FOREIGN KEY (gallery_id) REFERENCES module_galleries(id) ON DELETE CASCADE,\n"
            . "CONSTRAINT module_gallery_items_media_fk FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE RESTRICT\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function allGalleries(): array
    {
        return $this->db->fetchArray(
            "SELECT g.*, (SELECT COUNT(*) FROM module_gallery_items gi WHERE gi.gallery_id = g.id) AS image_count\n"
            . "FROM module_galleries g ORDER BY g.name ASC, g.id ASC"
        );
    }

    public function countFilteredGalleries(string $search = ''): int
    {
        $search = trim($search);
        $params = $search !== '' ? ['%' . $search . '%', '%' . $search . '%'] : [];
        return (int)$this->db->fetchValue(
            'SELECT COUNT(*) FROM module_galleries g' . ($search !== '' ? ' WHERE g.name LIKE ? OR g.description LIKE ?' : ''),
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function filteredGalleries(string $search, int $limit, int $offset): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $search = trim($search);
        $params = $search !== '' ? ['%' . $search . '%', '%' . $search . '%'] : [];
        return $this->db->fetchArray(
            'SELECT g.*, (SELECT COUNT(*) FROM module_gallery_items gi WHERE gi.gallery_id = g.id) AS image_count '
            . 'FROM module_galleries g'
            . ($search !== '' ? ' WHERE g.name LIKE ? OR g.description LIKE ?' : '')
            . ' ORDER BY g.name ASC, g.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function activeGalleries(): array
    {
        return $this->db->fetchArray(
            "SELECT id, name FROM module_galleries WHERE status = 'active' ORDER BY name ASC, id ASC"
        );
    }

    public function findGallery(int $id, bool $activeOnly = false): ?array
    {
        if ($id < 1) return null;
        $sql = 'SELECT * FROM module_galleries WHERE id = ?';
        if ($activeOnly) $sql .= " AND status = 'active'";
        $sql .= ' LIMIT 1';
        $rows = $this->db->fetchArray($sql, [$id]);
        return $rows[0] ?? null;
    }

    /** @return array<int,array<string,mixed>> */
    public function itemsForGallery(int $galleryId): array
    {
        if ($galleryId < 1) return [];
        return $this->db->fetchArray(
            "SELECT gi.id AS gallery_item_id, gi.gallery_id, gi.media_id, gi.sort_order,\n"
            . "m.file_path, m.original_filename, m.title, m.alt_text, m.description, m.width, m.height, m.file_size\n"
            . "FROM module_gallery_items gi\n"
            . "JOIN media m ON m.id = gi.media_id AND m.media_kind = 'image'\n"
            . "WHERE gi.gallery_id = ? ORDER BY gi.sort_order ASC, gi.id ASC",
            [$galleryId]
        );
    }

    /** @param array<int,int> $mediaIds */
    public function saveGallery(int $id, array $gallery, array $mediaIds): int
    {
        $this->db->beginTransaction();
        try {
            if ($id > 0) {
                if (!$this->findGallery($id)) {
                    throw new RuntimeException('Galleriet findes ikke længere.');
                }
                $this->db->query(
                    'UPDATE module_galleries SET name = ?, description = ?, status = ?, lightbox_enabled = ?, updated_at = NOW() WHERE id = ?',
                    [$gallery['name'], $gallery['description'], $gallery['status'], (int)($gallery['lightbox_enabled'] ?? 0), $id]
                );
            } else {
                $this->db->query(
                    'INSERT INTO module_galleries (name, description, status, lightbox_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())',
                    [$gallery['name'], $gallery['description'], $gallery['status'], (int)($gallery['lightbox_enabled'] ?? 0)]
                );
                $id = $this->db->getInsertId();
            }

            $this->db->query('DELETE FROM module_gallery_items WHERE gallery_id = ?', [$id]);
            foreach (array_values($mediaIds) as $sort => $mediaId) {
                $this->db->query(
                    'INSERT INTO module_gallery_items (gallery_id, media_id, sort_order, created_at) VALUES (?, ?, ?, NOW())',
                    [$id, $mediaId, $sort]
                );
            }

            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /** @return array{ok:bool,message:string} */
    public function deleteGallery(int $id): array
    {
        $gallery = $this->findGallery($id);
        if (!$gallery) {
            return ['ok' => false, 'message' => 'Galleriet findes ikke længere.'];
        }

        $usage = $this->pageBuilderUsage($id);
        if ($usage['count'] > 0) {
            return [
                'ok' => false,
                'message' => 'Galleriet bruges stadig på ' . $usage['count'] . ' side' . ($usage['count'] === 1 ? '' : 'r') . ' og kan derfor ikke slettes.',
                'usage_count' => (int)$usage['count'],
            ];
        }

        $this->db->query('DELETE FROM module_galleries WHERE id = ?', [$id]);
        return ['ok' => true, 'message' => 'Galleriet er slettet.'];
    }

    /** @return array{count:int,page_ids:array<int,int>} */
    public function pageBuilderUsage(int $galleryId): array
    {
        if ($galleryId < 1) return ['count' => 0, 'page_ids' => []];
        $needle = 'gallery:gallery-' . $galleryId;
        $pageIds = [];
        foreach ($this->db->fetchArray('SELECT page_id, block_data FROM page_blocks') as $row) {
            $data = json_decode((string)($row['block_data'] ?? ''), true);
            if (is_array($data) && $this->containsComponent($data, $needle)) {
                $pageIds[(int)$row['page_id']] = true;
            }
        }
        return ['count' => count($pageIds), 'page_ids' => array_keys($pageIds)];
    }

    private function containsComponent(array $data, string $needle): bool
    {
        foreach ($data as $key => $value) {
            if ($key === 'component' && (string)$value === $needle) return true;
            if (is_array($value) && $this->containsComponent($value, $needle)) return true;
        }
        return false;
    }
}
