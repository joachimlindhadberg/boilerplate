<?php

declare(strict_types=1);

/**
 * Transactionally maintained, indexed Page Builder media references.
 *
 * Database triggers mark the snapshot stale on writes, including direct SQL and
 * bulk imports. The next guarded media deletion refreshes it under the same
 * state-row lock before any file/row is removed. Hidden blocks also count.
 * Legacy HTML and module declarations are checked separately by MediaLibrary.
 */
final class MediaReferenceIndex
{
    public function __construct(private Db $db)
    {
    }

    /**
     * Must run inside the caller's transaction. Holds a state-row lock until
     * commit, serialized with source-table triggers; never trust stale data.
     *
     * @return int[] IDs of pages referencing the requested media ID.
     */
    public function pageIdsForMedia(int $mediaId): array
    {
        if (!Db::transactionActive()) {
            throw new LogicException('Media usage checks must run in a database transaction.');
        }
        $state = $this->lockState();
        if ((int)$state['dirty'] === 1) {
            $this->buildSnapshot();
        }
        $rows = $this->db->fetchArray(
            'SELECT source_id FROM media_reference_index WHERE media_id = ? ORDER BY source_id',
            [$mediaId]
        );
        return array_map(static fn(array $row): int => (int)$row['source_id'], $rows);
    }

    /** Explicit CLI/admin maintenance operation; never a startup migration. */
    public function rebuild(): array
    {
        $this->db->beginTransaction();
        try {
            $this->lockState();
            $statistics = $this->buildSnapshot();
            $this->db->commit();
            return $statistics;
        } catch (Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }
    }

    /** Read-only health check. Does not rebuild or mutate the snapshot. */
    public function status(): array
    {
        $this->assertTriggersInstalled();
        $rows = $this->db->fetchArray('SELECT dirty, updated_at, rebuilt_at FROM media_reference_state WHERE id = 1');
        if (!$rows) {
            throw new RuntimeException('Media index state is missing. Run the database migration.');
        }
        return [
            'dirty' => (int)$rows[0]['dirty'] === 1,
            'updated_at' => $rows[0]['updated_at'],
            'rebuilt_at' => $rows[0]['rebuilt_at'],
            'references' => (int)$this->db->fetchValue('SELECT COUNT(*) FROM media_reference_index'),
        ];
    }

    /**
     * Read-only data-integrity audit. Intentionally independent of the stored
     * index, so missing/invalid references cannot be hidden by a stale index.
     */
    public function audit(): array
    {
        $ids = [];
        $paths = [];
        $missingFiles = [];
        $root = dirname(__DIR__, 2) . '/public_html';
        foreach ($this->db->fetchArray('SELECT id, file_path FROM media ORDER BY id') as $media) {
            $id = (int)$media['id'];
            $path = (string)$media['file_path'];
            $ids[$id] = true;
            foreach (self::pathAliases($path) as $alias) $paths[$alias] = $id;
            if (str_starts_with($path, '/uploads/') || str_starts_with($path, '/assets/uploads/')) {
                $found = false;
                foreach (self::pathAliases($path) as $alias) {
                    $candidate = $root . $alias;
                    if (!str_contains($alias, '..') && is_file($candidate) && !is_link($candidate)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) $missingFiles[$id] = $path;
            }
        }
        $missingIds = [];
        $missingPaths = [];
        $invalidBlocks = [];
        $blockCount = 0;
        $lastId = 0;
        do {
            $batch = $this->db->fetchArray(
                'SELECT id, block_data FROM page_blocks WHERE id > ? ORDER BY id LIMIT 400',
                [$lastId]
            );
            foreach ($batch as $row) {
                $lastId = (int)$row['id'];
                ++$blockCount;
                $data = json_decode((string)$row['block_data'], true);
                if (!is_array($data)) {
                    $invalidBlocks[] = $lastId;
                    continue;
                }
                $found = [];
                self::collect($data, $ids, $paths, $found, $missingIds, $missingPaths);
            }
        } while (count($batch) === 400);
        return [
            'blocks_scanned' => $blockCount,
            'invalid_block_ids' => $invalidBlocks,
            'missing_media_ids' => array_values(array_map('intval', array_keys($missingIds))),
            'unresolved_image_paths' => array_values(array_keys($missingPaths)),
            'missing_media_files' => $missingFiles,
        ];
    }

    private function lockState(): array
    {
        $this->assertTriggersInstalled();
        $rows = $this->db->fetchArray('SELECT dirty FROM media_reference_state WHERE id = 1 FOR UPDATE');
        if (!$rows) {
            throw new RuntimeException('Media reference index not initialized. Run migrations and rebuild.');
        }
        return $rows[0];
    }

    private function assertTriggersInstalled(): void
    {
        // Backup/restore tools sometimes omit triggers. Missing triggers must
        // never result in a falsely clean index or an unsafe deletion.
        $expected = [
            'media_ref_blocks_insert', 'media_ref_blocks_update', 'media_ref_blocks_delete',
            'media_ref_pages_insert', 'media_ref_pages_update', 'media_ref_pages_delete',
            'media_ref_media_insert', 'media_ref_media_path_update', 'media_ref_media_delete',
        ];
        $names = $this->db->fetchArray(
            'SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS '
            . 'WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME IN ('
            . implode(', ', array_fill(0, count($expected), '?')) . ')',
            $expected
        );
        if (count($names) !== count($expected)) {
            throw new RuntimeException('Media reference triggers are missing. Restore them before deleting media.');
        }
    }

    /** Called ONLY with the state-row lock held in an active transaction. */
    private function buildSnapshot(): array
    {
        // Map paths and their legacy aliases to media IDs. Path-only content
        // must still protect the correct asset after storage-directory moves.
        $ids = [];
        $paths = [];
        foreach ($this->db->fetchArray('SELECT id, file_path FROM media ORDER BY id') as $media) {
            $id = (int)$media['id'];
            $ids[$id] = true;
            foreach (self::pathAliases((string)$media['file_path']) as $path) {
                $paths[$path] = $id;
            }
        }

        $this->db->query('DELETE FROM media_reference_index');
        $lastId = 0;
        $blocks = 0;
        $links = 0;
        $missingIds = [];
        $missingPaths = [];
        do {
            $batch = $this->db->fetchArray(
                'SELECT id, page_id, block_data FROM page_blocks WHERE id > ? ORDER BY id LIMIT 400',
                [$lastId]
            );
            foreach ($batch as $row) {
                $lastId = (int)$row['id'];
                $blocks++;
                $decoded = json_decode((string)$row['block_data'], true);
                if (!is_array($decoded)) {
                    // The safe default is to refuse deletion, not to overlook
                    // unknown media in corrupt stored Page Builder JSON.
                    throw new RuntimeException('Invalid Page Builder JSON in block #' . $lastId . '.');
                }
                $found = [];
                self::collect($decoded, $ids, $paths, $found, $missingIds, $missingPaths);
                foreach (array_keys($found) as $id) {
                    $this->db->query(
                        'INSERT INTO media_reference_index (source_id, media_id) VALUES (?, ?) '
                        . 'ON DUPLICATE KEY UPDATE media_id = media_id',
                        [(int)$row['page_id'], (int)$id]
                    );
                    ++$links;
                }
            }
        } while (count($batch) === 400);
        $this->db->query('UPDATE media_reference_state SET dirty = 0, rebuilt_at = UTC_TIMESTAMP() WHERE id = 1');

        return [
            'blocks' => $blocks,
            'links_processed' => $links,
            'unique_references' => (int)$this->db->fetchValue('SELECT COUNT(*) FROM media_reference_index'),
            'missing_media_ids' => array_values(array_map('intval', array_keys($missingIds))),
            'missing_media_paths' => array_values(array_keys($missingPaths)),
        ];
    }

    /** @return list<string> */
    public static function pathAliases(string $path): array
    {
        $path = trim($path);
        if ($path === '') return [];
        $paths = [$path];
        if (str_starts_with($path, '/assets/uploads/')) {
            $paths[] = '/uploads/' . substr($path, strlen('/assets/uploads/'));
        } elseif (str_starts_with($path, '/uploads/')) {
            $paths[] = '/assets/uploads/' . substr($path, strlen('/uploads/'));
        }
        return array_values(array_unique($paths));
    }

    /**
     * Collect all image_id/image_path references recursively, including nested
     * grids and inactive blocks. Non-media free text is not interpreted as IDs.
     */
    private static function collect(
        array $data,
        array $ids,
        array $paths,
        array &$found,
        array &$missingIds,
        array &$missingPaths
    ): void {
        foreach ($data as $key => $value) {
            if ($key === 'image_id' && (is_scalar($value) || $value === null)) {
                $id = (int)$value;
                if ($id > 0) {
                    if (isset($ids[$id])) $found[$id] = true;
                    else $missingIds[$id] = true;
                }
            } elseif ($key === 'image_path' && is_string($value) && trim($value) !== '') {
                $matched = false;
                foreach (self::pathAliases($value) as $candidate) {
                    if (isset($paths[$candidate])) {
                        $found[$paths[$candidate]] = true;
                        $matched = true;
                    }
                }
                if (!$matched) $missingPaths[$value] = true;
            } elseif (is_array($value)) {
                self::collect($value, $ids, $paths, $found, $missingIds, $missingPaths);
            }
        }
    }
}
