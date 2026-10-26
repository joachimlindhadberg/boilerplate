-- V2.5.0 QA1 / 4A.6.8a: explicit phpMyAdmin migration for EXISTING sites.
-- Back up the isolated database first; import BEFORE deploying the PHP candidate.
-- Run once on a database which does not contain auth_sessions.
-- Existing sessions and legacy Remember Me cookies will require fresh login.
CREATE TABLE `auth_sessions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `remember_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `remember_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auth_sessions_session_hash` (`session_hash`),
  UNIQUE KEY `auth_sessions_remember_hash` (`remember_hash`),
  KEY `auth_sessions_user` (`user_id`),
  KEY `auth_sessions_remember_expiry` (`remember_expires_at`),
  CONSTRAINT `auth_sessions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
