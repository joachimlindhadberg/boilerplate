-- Boilerplate V2.3.7: shared login and Forms rate limiting.
-- Run this migration ONCE on existing installations, before deploying PHP files.
CREATE TABLE IF NOT EXISTS `security_rate_limits` (
  `key_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hit_count` int unsigned NOT NULL DEFAULT 0,
  `reset_at` datetime NOT NULL,
  PRIMARY KEY (`key_hash`),
  KEY `security_rate_limits_expiry` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
