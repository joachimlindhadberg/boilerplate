<?php

declare(strict_types=1);

/**
 * Install a guarded Page Builder media reference index. Does NOT populate it.
 * Pending/dirty state deliberately prevents deletion until a rebuild succeeds.
 * Idempotent DDL (MySQL commits DDL outside user transactions).
 */
return static function (Db $db): void {
    $db->query(
        'CREATE TABLE IF NOT EXISTS media_reference_state ('
        . 'id tinyint unsigned NOT NULL PRIMARY KEY,'
        . 'dirty tinyint(1) NOT NULL DEFAULT 1,'
        . 'updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . 'rebuilt_at datetime DEFAULT NULL'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $db->query('INSERT IGNORE INTO media_reference_state (id, dirty) VALUES (1, 1)');
    $db->query(
        'CREATE TABLE IF NOT EXISTS media_reference_index ('
        . 'source_id int NOT NULL,'
        . 'media_id int NOT NULL,'
        . 'PRIMARY KEY (source_id, media_id),'
        . 'KEY media_reference_media (media_id),'
        . 'CONSTRAINT media_reference_media_fk FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE RESTRICT'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // BEFORE triggers acquire the state-row lock before the source modification
    // can commit, serializing edits with an in-progress guarded media deletion.
    $triggers = [
        'media_ref_blocks_insert' => [
            'page_blocks', 'BEFORE INSERT',
            'UPDATE media_reference_state SET dirty = 1, updated_at = UTC_TIMESTAMP() WHERE id = 1',
        ],
        'media_ref_blocks_update' => [
            'page_blocks', 'BEFORE UPDATE',
            'UPDATE media_reference_state SET dirty = 1, updated_at = UTC_TIMESTAMP() WHERE id = 1',
        ],
        'media_ref_blocks_delete' => [
            'page_blocks', 'BEFORE DELETE',
            'UPDATE media_reference_state SET dirty = 1, updated_at = UTC_TIMESTAMP() WHERE id = 1',
        ],
        'media_ref_pages_insert' => [
            'pages', 'BEFORE INSERT',
            'UPDATE media_reference_state SET dirty = 1, updated_at = UTC_TIMESTAMP() WHERE id = 1',
        ],
        'media_ref_pages_update' => [
            'pages', 'BEFORE UPDATE',
            'IF NOT (OLD.page_content <=> NEW.page_content) THEN '
            . 'UPDATE media_reference_state SET dirty = 1, updated_at = UTC_TIMESTAMP() WHERE id = 1; END IF',
        ],
        'media_ref_pages_delete' => [
            'pages', 'BEFORE DELETE',
            'UPDATE media_reference_state SET dirty = 1, updated_at = UTC_TIMESTAMP() WHERE id = 1',
        ],
        'media_ref_media_insert' => [
            'media', 'BEFORE INSERT',
            'UPDATE media_reference_state SET dirty = 1, updated_at = UTC_TIMESTAMP() WHERE id = 1',
        ],
        'media_ref_media_path_update' => [
            'media', 'BEFORE UPDATE',
            'IF NOT (OLD.file_path <=> NEW.file_path) THEN '
            . 'UPDATE media_reference_state SET dirty = 1, updated_at = UTC_TIMESTAMP() WHERE id = 1; END IF',
        ],
        'media_ref_media_delete' => [
            'media', 'BEFORE DELETE',
            'BEGIN DECLARE index_dirty tinyint DEFAULT 1; '
            . 'SELECT dirty INTO index_dirty FROM media_reference_state WHERE id = 1; '
            . 'IF index_dirty <> 0 THEN '
            . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Media reference index is stale; rebuild before deleting media'; "
            . 'END IF; END',
        ],
    ];

    foreach ($triggers as $name => [$table, $timing, $statement]) {
        $exists = (int)$db->fetchValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS '
            . 'WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?',
            [$name]
        );
        if ($exists === 0) {
            $db->createMigrationTrigger('CREATE TRIGGER `' . $name . '` ' . $timing . ' ON `' . $table . '` '
                . 'FOR EACH ROW ' . (str_starts_with($statement, 'BEGIN') ? $statement : 'BEGIN ' . $statement . '; END'));
        }
    }
};
