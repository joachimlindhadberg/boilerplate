<?php

declare(strict_types=1);

/** Explicit, restart-safe RC5 migration. CLI only, does not alter existing users. */
return static function (Db $db): void {
    $db->query(
        'CREATE TABLE IF NOT EXISTS user_dashboard_cards ('
        . 'user_id int NOT NULL PRIMARY KEY,'
        . 'cards_json longtext NOT NULL,'
        . 'updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . 'CONSTRAINT user_dashboard_cards_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
