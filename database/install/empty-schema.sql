-- Boilerplate CMS V2.5.0-RC2 fresh-install baseline. New, EMPTY database ONLY.
-- Derived from audited schema structure; contains no existing users/data.
-- Not an upgrade or recovery script. Installer refuses any nonempty database.
SET NAMES utf8mb4;
CREATE TABLE `activity_log` (
  `id` bigint UNSIGNED NOT NULL,
  `actor_id` int DEFAULT NULL,
  `actor_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint UNSIGNED DEFAULT NULL,
  `subject_label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at_utc` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `media` (
  `id` int NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_kind` enum('image','video') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint UNSIGNED NOT NULL DEFAULT '0',
  `width` int UNSIGNED DEFAULT NULL,
  `height` int UNSIGNED DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `media_reference_index` (
  `source_id` int NOT NULL,
  `media_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `media_reference_state` (
  `id` tinyint UNSIGNED NOT NULL,
  `dirty` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rebuilt_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modules` (
  `id` int NOT NULL,
  `module_slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `installed_version` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `activated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `module_forms` (
  `id` int NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `submit_label` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Submit',
  `success_message` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Thank you. Your message has been sent.',
  `success_action` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'message',
  `success_redirect_page_id` int DEFAULT NULL,
  `notification_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notification_subject` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reply_to_field_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `save_submissions` tinyint(1) NOT NULL DEFAULT '1',
  `send_notification` tinyint(1) NOT NULL DEFAULT '1',
  `send_confirmation` tinyint(1) NOT NULL DEFAULT '0',
  `confirmation_email_field_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `confirmation_subject` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `confirmation_message` varchar(3000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `module_form_fields` (
  `id` int NOT NULL,
  `form_id` int NOT NULL,
  `field_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `placeholder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `help_text` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `options_json` longtext COLLATE utf8mb4_unicode_ci,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `row_layout` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '100',
  `row_order` int NOT NULL DEFAULT '0',
  `column_index` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `module_form_rows` (
  `form_id` int NOT NULL,
  `row_order` int NOT NULL,
  `row_layout` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '100'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `module_form_submissions` (
  `id` bigint NOT NULL,
  `form_id` int NOT NULL,
  `data_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `module_galleries` (
  `id` int NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `lightbox_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `module_gallery_items` (
  `id` int NOT NULL,
  `gallery_id` int NOT NULL,
  `media_id` int NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `navigation_items` (
  `id` int NOT NULL,
  `menu_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `type` enum('page','url') COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` int DEFAULT NULL,
  `url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `target` enum('_self','_blank') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '_self',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `navigation_menus` (
  `id` int NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pages` (
  `id` int NOT NULL,
  `page_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int DEFAULT NULL,
  `page_description` text COLLATE utf8mb4_unicode_ci,
  `page_content` longtext COLLATE utf8mb4_unicode_ci,
  `content_mode` enum('legacy','builder') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'builder',
  `page_status` enum('draft','published','protected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `menu_order` int DEFAULT NULL,
  `show_in_menu` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(320) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `page_blocks` (
  `id` int NOT NULL,
  `page_id` int NOT NULL,
  `block_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_data` json NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` enum('active','hidden') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `page_revisions` (
  `id` bigint UNSIGNED NOT NULL,
  `page_id` int NOT NULL,
  `version_number` int UNSIGNED NOT NULL,
  `action` enum('baseline','create','save','restore','scheduled_publish','scheduled_unpublish') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'save',
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `snapshot_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `page_schedules` (
  `page_id` int NOT NULL,
  `publish_at_utc` datetime DEFAULT NULL,
  `unpublish_at_utc` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `redirects` (
  `id` int NOT NULL,
  `source_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `redirect_type` smallint UNSIGNED NOT NULL DEFAULT '301',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_automatic` tinyint(1) NOT NULL DEFAULT '0',
  `page_id` int DEFAULT NULL,
  `hit_count` bigint UNSIGNED NOT NULL DEFAULT '0',
  `last_hit_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `role_name` varchar(50) COLLATE utf8mb4_danish_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

CREATE TABLE `schema_migrations` (
  `migration_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum_sha256` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `applied_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `security_rate_limits` (
  `key_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hit_count` int UNSIGNED NOT NULL DEFAULT '0',
  `reset_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(191) COLLATE utf8mb4_danish_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_danish_ci,
  `autoload` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

CREATE TABLE `user_dashboard_cards` (
  `user_id` int NOT NULL,
  `cards_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int NOT NULL,
  `role_id` int NOT NULL,
  `firstname` varchar(100) COLLATE utf8mb4_danish_ci NOT NULL,
  `lastname` varchar(100) COLLATE utf8mb4_danish_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_danish_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_danish_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_danish_ci NOT NULL,
  `weekly_hours` decimal(4,1) NOT NULL DEFAULT '0.0',
  `status` enum('active','inactive','banned') COLLATE utf8mb4_danish_ci DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `remember_token` text COLLATE utf8mb4_danish_ci,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `admin_language` varchar(5) COLLATE utf8mb4_danish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_log_created` (`created_at_utc`,`id`),
  ADD KEY `activity_log_actor` (`actor_id`,`id`),
  ADD KEY `activity_log_subject` (`subject_type`,`subject_id`),
  ADD KEY `activity_log_action` (`action`,`id`);

ALTER TABLE `media`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_path` (`file_path`),
  ADD KEY `media_kind_created` (`media_kind`,`created_at`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `media_reference_index`
  ADD PRIMARY KEY (`source_id`,`media_id`),
  ADD KEY `media_reference_media` (`media_id`);

ALTER TABLE `media_reference_state`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_slug` (`module_slug`),
  ADD KEY `modules_active` (`is_active`);

ALTER TABLE `module_forms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `forms_status` (`status`);

ALTER TABLE `module_form_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `form_field_key` (`form_id`,`field_key`),
  ADD KEY `form_fields_sort` (`form_id`,`sort_order`);

ALTER TABLE `module_form_rows`
  ADD PRIMARY KEY (`form_id`,`row_order`);

ALTER TABLE `module_form_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `form_submissions_form_date` (`form_id`,`submitted_at`),
  ADD KEY `form_submissions_status` (`status`);

ALTER TABLE `module_galleries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `galleries_status` (`status`),
  ADD KEY `galleries_name` (`name`);

ALTER TABLE `module_gallery_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gallery_media` (`gallery_id`,`media_id`),
  ADD KEY `gallery_items_sort` (`gallery_id`,`sort_order`),
  ADD KEY `gallery_items_media` (`media_id`);

ALTER TABLE `navigation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nav_items_menu_sort` (`menu_id`,`parent_id`,`sort_order`),
  ADD KEY `nav_items_page` (`page_id`),
  ADD KEY `nav_items_parent` (`parent_id`);

ALTER TABLE `navigation_menus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_slug` (`page_slug`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `page_status` (`page_status`),
  ADD KEY `pages_creator_fk` (`created_by`),
  ADD KEY `pages_editor_fk` (`updated_by`);

ALTER TABLE `page_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `page_id_sort` (`page_id`,`sort_order`);

ALTER TABLE `page_revisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_version_unique` (`page_id`,`version_number`),
  ADD KEY `page_revisions_creator` (`created_by`);

ALTER TABLE `page_schedules`
  ADD PRIMARY KEY (`page_id`),
  ADD KEY `page_schedules_publish_due` (`publish_at_utc`),
  ADD KEY `page_schedules_unpublish_due` (`unpublish_at_utc`);

ALTER TABLE `redirects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `source_path` (`source_path`),
  ADD KEY `redirects_active` (`is_active`),
  ADD KEY `redirects_page` (`page_id`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`migration_id`);

ALTER TABLE `security_rate_limits`
  ADD PRIMARY KEY (`key_hash`),
  ADD KEY `security_rate_limits_expiry` (`reset_at`);

ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

ALTER TABLE `user_dashboard_cards`
  ADD PRIMARY KEY (`user_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

ALTER TABLE `activity_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

ALTER TABLE `media`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

ALTER TABLE `modules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `module_forms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `module_form_fields`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

ALTER TABLE `module_form_submissions`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `module_galleries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `module_gallery_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

ALTER TABLE `navigation_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `navigation_menus`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `pages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

ALTER TABLE `page_blocks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

ALTER TABLE `page_revisions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `redirects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `media`
  ADD CONSTRAINT `media_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `media_reference_index`
  ADD CONSTRAINT `media_reference_media_fk` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT;

ALTER TABLE `module_form_fields`
  ADD CONSTRAINT `module_form_fields_form_fk` FOREIGN KEY (`form_id`) REFERENCES `module_forms` (`id`) ON DELETE CASCADE;

ALTER TABLE `module_form_rows`
  ADD CONSTRAINT `module_form_rows_form_fk` FOREIGN KEY (`form_id`) REFERENCES `module_forms` (`id`) ON DELETE CASCADE;

ALTER TABLE `module_form_submissions`
  ADD CONSTRAINT `module_form_submissions_form_fk` FOREIGN KEY (`form_id`) REFERENCES `module_forms` (`id`) ON DELETE RESTRICT;

ALTER TABLE `module_gallery_items`
  ADD CONSTRAINT `module_gallery_items_gallery_fk` FOREIGN KEY (`gallery_id`) REFERENCES `module_galleries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_gallery_items_media_fk` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT;

ALTER TABLE `navigation_items`
  ADD CONSTRAINT `nav_items_menu_fk` FOREIGN KEY (`menu_id`) REFERENCES `navigation_menus` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nav_items_page_fk` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `nav_items_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `navigation_items` (`id`) ON DELETE SET NULL;

ALTER TABLE `pages`
  ADD CONSTRAINT `pages_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pages_editor_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pages_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL;

ALTER TABLE `page_blocks`
  ADD CONSTRAINT `page_blocks_page_fk` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;

ALTER TABLE `page_revisions`
  ADD CONSTRAINT `page_revisions_page_fk` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `page_revisions_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `page_schedules`
  ADD CONSTRAINT `page_schedules_page_fk` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE;

ALTER TABLE `redirects`
  ADD CONSTRAINT `redirects_page_fk` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL;

ALTER TABLE `user_dashboard_cards`
  ADD CONSTRAINT `user_dashboard_cards_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `users_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

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
