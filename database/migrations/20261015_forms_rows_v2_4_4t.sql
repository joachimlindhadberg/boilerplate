-- Boilerplate CMS V2.4.4t – Forms Row Data Foundation
-- APPLY IN PHPMYADMIN BEFORE DEPLOYING THE NEW PHP FILES.
-- Back up the database first. This does not change or delete existing fields,
-- forms, saved submissions, or pre-existing row/column assignments.
-- Legacy rows are automatically read from module_form_fields until a form is saved.

CREATE TABLE IF NOT EXISTS `module_form_rows` (
  `form_id` int NOT NULL,
  `row_order` int NOT NULL,
  `row_layout` varchar(10) NOT NULL DEFAULT '100',
  PRIMARY KEY (`form_id`, `row_order`),
  CONSTRAINT `module_form_rows_form_fk`
    FOREIGN KEY (`form_id`) REFERENCES `module_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
