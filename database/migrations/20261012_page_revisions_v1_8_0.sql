-- Boilerplate V1.8.0: Page revisions. Run once on existing V1.7.4 databases.
-- Back up database first. Do NOT import boilerplate.sql into an existing database.
CREATE TABLE IF NOT EXISTS `page_revisions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` int NOT NULL,
  `version_number` int UNSIGNED NOT NULL,
  `action` enum('baseline','create','save','restore') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'save',
  `snapshot` longtext COLLATE utf8mb4_bin NOT NULL,
  `snapshot_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_version_unique` (`page_id`,`version_number`),
  KEY `page_revisions_creator` (`created_by`),
  CONSTRAINT `page_revisions_page_fk` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_revisions_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `page_revisions_snapshot_json` CHECK (json_valid(`snapshot`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
