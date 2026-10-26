-- Boilerplate V1.9.0. Back up first and import ONCE to the existing database.
-- Requires page_revisions migration from V1.8.0.
-- SQL 8 / MariaDB supported enum extension. Existing revision rows remain unchanged.
ALTER TABLE `page_revisions`
  MODIFY COLUMN `action` enum('baseline','create','save','restore','scheduled_publish','scheduled_unpublish')
  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'save';


-- V1.9.0: UTC schedule metadata; one row per page, cascades on page removal.
CREATE TABLE IF NOT EXISTS `page_schedules` (
  `page_id` int NOT NULL,
  `publish_at_utc` datetime DEFAULT NULL,
  `unpublish_at_utc` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`page_id`),
  KEY `page_schedules_publish_due` (`publish_at_utc`),
  KEY `page_schedules_unpublish_due` (`unpublish_at_utc`),
  CONSTRAINT `page_schedules_page_fk` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
