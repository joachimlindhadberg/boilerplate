<?php

declare(strict_types=1);

/**
 * V2.4.4z3: Record the creator and latest editor of each Page.
 * Existing pages receive NULL: never invent historical attribution.
 * The explicit CLI migration can resume after partial DDL.
 */
return static function (Db $db): void {
    foreach (['created_by', 'updated_by'] as $column) {
        $exists = (int)$db->fetchValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = ?",
            [$column]
        );
        if ($exists === 0) {
            $db->query('ALTER TABLE pages ADD COLUMN `' . $column . '` int DEFAULT NULL');
        }
    }
    foreach (['created_by' => 'pages_creator_fk', 'updated_by' => 'pages_editor_fk'] as $column => $constraint) {
        $exists = (int)$db->fetchValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            ['pages', $constraint, 'FOREIGN KEY']
        );
        if ($exists === 0) {
            $db->query('ALTER TABLE pages ADD CONSTRAINT `' . $constraint . '` '
                . 'FOREIGN KEY (`' . $column . '`) REFERENCES users(id) ON DELETE SET NULL');
        }
    }
};
