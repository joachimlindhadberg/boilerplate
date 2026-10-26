<?php

declare(strict_types=1);

/** Explicit restart-safe CLI migration; never run during page requests. */
return static function (Db $db): void {
    $present = (int)$db->fetchValue(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS '
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'module_galleries' AND COLUMN_NAME = ?",
        ['lightbox_enabled']
    );
    if ($present === 0) {
        $db->query('ALTER TABLE module_galleries ADD COLUMN lightbox_enabled tinyint(1) NOT NULL DEFAULT 0 AFTER status');
    }
};
