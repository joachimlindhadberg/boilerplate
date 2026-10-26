-- V2.2.0: individual admin language; default inherited from the global setting (English).
-- Import ONCE on an existing installation. No existing data is changed.
ALTER TABLE `users` ADD COLUMN `admin_language` VARCHAR(5) NULL DEFAULT NULL;

-- Existing website locale remains unchanged. Introduce a separate administration default.
INSERT INTO `settings` (`setting_key`, `setting_value`, `autoload`)
SELECT 'admin_language', 'en', 1
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `setting_key` = 'admin_language');
