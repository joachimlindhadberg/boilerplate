<?php

declare(strict_types=1);

/** Explicit, restart-safe page password metadata; no plaintext passwords. */
return static function (Db $db): void {
    $db->query(
        'CREATE TABLE IF NOT EXISTS page_access ('
        . 'page_id int NOT NULL PRIMARY KEY,'
        . 'password_hash varchar(255) NOT NULL,'
        . 'updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . 'CONSTRAINT page_access_page_fk FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
