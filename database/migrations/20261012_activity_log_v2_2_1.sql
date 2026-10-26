-- Boilerplate V2.2.1: Create-only audit event storage, existing records unchanged.
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` int DEFAULT NULL,
  `actor_name` varchar(191) NOT NULL,
  `actor_type` varchar(16) NOT NULL DEFAULT 'user',
  `action` varchar(64) NOT NULL,
  `subject_type` varchar(64) NOT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `subject_label` varchar(255) NOT NULL DEFAULT '',
  `created_at_utc` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_log_created` (`created_at_utc`, `id`),
  KEY `activity_log_actor` (`actor_id`, `id`),
  KEY `activity_log_subject` (`subject_type`, `subject_id`),
  KEY `activity_log_action` (`action`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
