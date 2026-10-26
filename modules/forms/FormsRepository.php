<?php

declare(strict_types=1);

final class FormsRepository
{
    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    public function ensureSchema(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS module_forms (\n"
            . "id int NOT NULL AUTO_INCREMENT,\n"
            . "name varchar(191) NOT NULL,\n"
            . "status varchar(20) NOT NULL DEFAULT 'active',\n"
            . "submit_label varchar(80) NOT NULL DEFAULT 'Submit',\n"
            . "success_message varchar(1000) NOT NULL DEFAULT 'Thank you. Your message has been sent.',\n"
            . "success_action varchar(20) NOT NULL DEFAULT 'message',\n"
            . "success_redirect_page_id int DEFAULT NULL,\n"
            . "notification_email varchar(255) DEFAULT NULL,\n"
            . "notification_subject varchar(191) NOT NULL DEFAULT '',\n"
            . "reply_to_field_key varchar(100) NOT NULL DEFAULT '',\n"
            . "save_submissions tinyint(1) NOT NULL DEFAULT 1,\n"
            . "send_notification tinyint(1) NOT NULL DEFAULT 1,\n"
            . "send_confirmation tinyint(1) NOT NULL DEFAULT 0,\n"
            . "confirmation_email_field_key varchar(100) NOT NULL DEFAULT '',\n"
            . "confirmation_subject varchar(191) NOT NULL DEFAULT '',\n"
            . "confirmation_message varchar(3000) NOT NULL DEFAULT '',\n"
            . "created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY forms_status (status)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Forward-only, idempotent migrations for installations created by Forms 1.0.x.
        $this->ensureColumn('module_forms', 'success_action', "varchar(20) NOT NULL DEFAULT 'message' AFTER success_message");
        $this->ensureColumn('module_forms', 'success_redirect_page_id', 'int DEFAULT NULL AFTER success_action');
        $this->ensureColumn('module_forms', 'notification_subject', "varchar(191) NOT NULL DEFAULT '' AFTER notification_email");
        $this->ensureColumn('module_forms', 'reply_to_field_key', "varchar(100) NOT NULL DEFAULT '' AFTER notification_subject");
        $this->ensureColumn('module_forms', 'send_confirmation', 'tinyint(1) NOT NULL DEFAULT 0 AFTER send_notification');
        $this->ensureColumn('module_forms', 'confirmation_email_field_key', "varchar(100) NOT NULL DEFAULT '' AFTER send_confirmation");
        $this->ensureColumn('module_forms', 'confirmation_subject', "varchar(191) NOT NULL DEFAULT '' AFTER confirmation_email_field_key");
        $this->ensureColumn('module_forms', 'confirmation_message', "varchar(3000) NOT NULL DEFAULT '' AFTER confirmation_subject");

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS module_form_fields (\n"
            . "id int NOT NULL AUTO_INCREMENT,\n"
            . "form_id int NOT NULL,\n"
            . "field_key varchar(100) NOT NULL,\n"
            . "label varchar(191) NOT NULL,\n"
            . "field_type varchar(30) NOT NULL,\n"
            . "placeholder varchar(255) NOT NULL DEFAULT '',\n"
            . "help_text varchar(500) NOT NULL DEFAULT '',\n"
            . "options_json longtext DEFAULT NULL,\n"
            . "is_required tinyint(1) NOT NULL DEFAULT 0,\n"
            . "row_layout varchar(10) NOT NULL DEFAULT '100',\n"
            . "row_order int NOT NULL DEFAULT 0,\n"
            . "column_index tinyint unsigned NOT NULL DEFAULT 0,\n"
            . "sort_order int NOT NULL DEFAULT 0,\n"
            . "created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY form_field_key (form_id, field_key),\n"
            . "KEY form_fields_sort (form_id, sort_order),\n"
            . "CONSTRAINT module_form_fields_form_fk FOREIGN KEY (form_id) REFERENCES module_forms(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $rowOrderAdded = $this->ensureColumn('module_form_fields', 'row_order', 'int NOT NULL DEFAULT 0 AFTER is_required');
        $this->ensureColumn('module_form_fields', 'row_layout', "varchar(10) NOT NULL DEFAULT '100' AFTER is_required");
        $this->ensureColumn('module_form_fields', 'column_index', 'tinyint unsigned NOT NULL DEFAULT 0 AFTER row_order');
        if ($rowOrderAdded) {
            // Forms <= 1.1 stored a flat field list. Preserve its visual order by
            // converting every legacy field into its own 100% row.
            $this->db->query(
                "UPDATE module_form_fields SET row_layout = '100', row_order = sort_order, column_index = 0"
            );
        }

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS module_form_submissions (\n"
            . "id bigint NOT NULL AUTO_INCREMENT,\n"
            . "form_id int NOT NULL,\n"
            . "data_json longtext NOT NULL,\n"
            . "source_url varchar(2048) NOT NULL DEFAULT '',\n"
            . "status varchar(20) NOT NULL DEFAULT 'new',\n"
            . "submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY form_submissions_form_date (form_id, submitted_at),\n"
            . "KEY form_submissions_status (status),\n"
            . "CONSTRAINT module_form_submissions_form_fk FOREIGN KEY (form_id) REFERENCES module_forms(id) ON DELETE RESTRICT\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function allForms(): array
    {
        return $this->db->fetchArray(
            "SELECT f.*,\n"
            . "(SELECT COUNT(*) FROM module_form_fields ff WHERE ff.form_id = f.id) AS field_count,\n"
            . "(SELECT COUNT(*) FROM module_form_submissions fs WHERE fs.form_id = f.id) AS submission_count,\n"
            . "(SELECT COUNT(*) FROM module_form_submissions fs WHERE fs.form_id = f.id AND fs.status = 'new') AS new_submission_count\n"
            . "FROM module_forms f ORDER BY f.name ASC, f.id ASC"
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function activeForms(): array
    {
        // An unfinished (row-only) form is a saved draft, not a usable frontend component.
        return $this->db->fetchArray("SELECT id, name FROM module_forms f WHERE status = 'active' AND EXISTS (SELECT 1 FROM module_form_fields ff WHERE ff.form_id = f.id) ORDER BY name ASC, id ASC");
    }

    public function findForm(int $id, bool $activeOnly = false): ?array
    {
        if ($id < 1) return null;
        $sql = 'SELECT * FROM module_forms WHERE id = ?';
        if ($activeOnly) $sql .= " AND status = 'active'";
        $sql .= ' LIMIT 1';
        $rows = $this->db->fetchArray($sql, [$id]);
        return $rows[0] ?? null;
    }

    /** @return array<int,array<string,mixed>> */
    public function fieldsForForm(int $formId): array
    {
        if ($formId < 1) return [];
        $rows = $this->db->fetchArray(
            'SELECT * FROM module_form_fields WHERE form_id = ? ORDER BY row_order ASC, column_index ASC, sort_order ASC, id ASC',
            [$formId]
        );
        foreach ($rows as &$row) {
            $options = json_decode((string)($row['options_json'] ?? ''), true);
            $row['options'] = is_array($options) ? array_values(array_filter(array_map('strval', $options), static fn(string $v): bool => $v !== '')) : [];
        }
        unset($row);
        return $rows;
    }

    /**
     * Persisted builder rows are independent of fields, so an empty 50/50 row
     * survives save/reopen. Legacy installations with no row records are still
     * reconstructed from the existing module_form_fields row_* columns.
     *
     * Requires database/migrations/20261015_forms_rows_v2_4_4t.sql.
     * @return array<int,array{row_order:int,layout:string}>
     */
    public function rowsForForm(int $formId): array
    {
        if ($formId < 1) return [];
        $rows = $this->db->fetchArray(
            'SELECT row_order, row_layout FROM module_form_rows WHERE form_id = ? ORDER BY row_order ASC',
            [$formId]
        );
        return array_map(static fn(array $row): array => [
            'row_order' => max(0, (int)$row['row_order']),
            'layout' => (string)$row['row_layout'] === '50-50' ? '50-50' : '100',
        ], $rows);
    }

    /**
     * Deletion must not discard submissions or leave broken Page Builder links.
     * The page check inspects saved component identifiers, not arbitrary text.
     *
     * @return array{submissions:int,pages:int}
     */
    public function formDeleteDependencies(int $id): array
    {
        if ($id < 1) return ['submissions' => 0, 'pages' => 0];

        $submissions = (int)$this->db->fetchValue(
            'SELECT COUNT(*) FROM module_form_submissions WHERE form_id = ?', [$id]
        );
        $pages = [];
        $component = 'form-' . $id;
        $blocks = $this->db->fetchArray(
            'SELECT page_id, block_data FROM page_blocks WHERE block_data LIKE ?',
            ['%' . $component . '%']
        );
        foreach ($blocks as $block) {
            $data = json_decode((string)($block['block_data'] ?? ''), true);
            if (is_array($data) && self::containsFormComponent($data, $component)) {
                $pages[(int)$block['page_id']] = true;
            }
        }
        return ['submissions' => $submissions, 'pages' => count($pages)];
    }

    /** Recursively match real component keys (including nested grid children). */
    private static function containsFormComponent(array $data, string $component): bool
    {
        foreach ($data as $key => $value) {
            if ($key === 'component' && $value === $component) return true;
            if (is_array($value) && self::containsFormComponent($value, $component)) return true;
        }
        return false;
    }

    /**
     * Delete an unused form and its fields; submissions are protected by the
     * existing ON DELETE RESTRICT constraint even in a concurrent request.
     *
     * @throws DomainException when a form has dependencies
     */
    public function deleteForm(int $id): bool
    {
        if ($id < 1) return false;
        $this->db->beginTransaction();
        try {
            // Hold the parent row while checking/deleting to avoid concurrent
            // submission inserts racing a deletion request.
            $rows = $this->db->fetchArray('SELECT id FROM module_forms WHERE id = ? FOR UPDATE', [$id]);
            if (!$rows) {
                $this->db->rollback();
                return false;
            }
            $dependencies = $this->formDeleteDependencies($id);
            if ($dependencies['submissions'] > 0) {
                throw new DomainException('forms:Formularen har gemte indsendelser. Slet dem først, eller deaktivér formularen.');
            }
            if ($dependencies['pages'] > 0) {
                throw new DomainException('forms:Formularen bruges i Page Builder. Fjern den fra siderne først.');
            }
            $this->db->query('DELETE FROM module_forms WHERE id = ?', [$id]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $fields
     * @param array<int,array{row_order:int,layout:string}> $rowLayouts
     */
    public function saveForm(int $id, array $form, array $fields, array $rowLayouts = []): int
    {
        // Backwards compatibility with callers that only know the old flat
        // fields API. Each occupied row is synthesized from the field layout.
        if (!$rowLayouts) {
            foreach ($fields as $field) {
                $index = max(0, (int)($field['row_order'] ?? $field['sort_order'] ?? 0));
                $rowLayouts[$index] = [
                    'row_order' => $index,
                    'layout' => ($field['row_layout'] ?? '100') === '50-50' ? '50-50' : '100',
                ];
            }
        }
        if (count($rowLayouts) > 100) {
            throw new InvalidArgumentException('En formular kan højst indeholde 100 rækker.');
        }
        $this->db->beginTransaction();
        try {
            $params = [
                $form['name'],
                $form['status'],
                $form['submit_label'],
                $form['success_message'],
                $form['success_action'],
                $form['success_redirect_page_id'] > 0 ? $form['success_redirect_page_id'] : null,
                $form['notification_subject'],
                $form['reply_to_field_key'],
                !empty($form['save_submissions']) ? 1 : 0,
                !empty($form['send_notification']) ? 1 : 0,
                !empty($form['send_confirmation']) ? 1 : 0,
                $form['confirmation_email_field_key'],
                $form['confirmation_subject'],
                $form['confirmation_message'],
            ];

            if ($id > 0) {
                if (!$this->findForm($id)) {
                    throw new RuntimeException('Formularen findes ikke længere.');
                }
                $this->db->query(
                    'UPDATE module_forms SET name = ?, status = ?, submit_label = ?, success_message = ?, success_action = ?, success_redirect_page_id = ?, notification_email = NULL, notification_subject = ?, reply_to_field_key = ?, save_submissions = ?, send_notification = ?, send_confirmation = ?, confirmation_email_field_key = ?, confirmation_subject = ?, confirmation_message = ?, updated_at = NOW() WHERE id = ?',
                    [...$params, $id]
                );
            } else {
                $this->db->query(
                    'INSERT INTO module_forms (name, status, submit_label, success_message, success_action, success_redirect_page_id, notification_email, notification_subject, reply_to_field_key, save_submissions, send_notification, send_confirmation, confirmation_email_field_key, confirmation_subject, confirmation_message, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    $params
                );
                $id = $this->db->getInsertId();
            }

            $this->db->query('DELETE FROM module_form_fields WHERE form_id = ?', [$id]);
            $this->db->query('DELETE FROM module_form_rows WHERE form_id = ?', [$id]);
            $seenRowOrders = [];
            foreach ($rowLayouts as $row) {
                $order = max(0, (int)($row['row_order'] ?? 0));
                if (isset($seenRowOrders[$order])) {
                    throw new InvalidArgumentException('Rækkernes placering skal være entydig.');
                }
                $seenRowOrders[$order] = true;
                $this->db->query(
                    'INSERT INTO module_form_rows (form_id, row_order, row_layout) VALUES (?, ?, ?)',
                    [$id, $order, ($row['layout'] ?? '100') === '50-50' ? '50-50' : '100']
                );
            }
            foreach (array_values($fields) as $sort => $field) {
                $this->db->query(
                    'INSERT INTO module_form_fields (form_id, field_key, label, field_type, placeholder, help_text, options_json, is_required, row_layout, row_order, column_index, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $id,
                        $field['field_key'],
                        $field['label'],
                        $field['field_type'],
                        $field['placeholder'],
                        $field['help_text'],
                        $field['options'] ? json_encode($field['options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                        !empty($field['is_required']) ? 1 : 0,
                        in_array((string)($field['row_layout'] ?? '100'), ['100', '50-50'], true) ? (string)$field['row_layout'] : '100',
                        max(0, (int)($field['row_order'] ?? $sort)),
                        max(0, min(1, (int)($field['column_index'] ?? 0))),
                        $sort,
                    ]
                );
            }

            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /** @param array<int,array<string,mixed>> $snapshot */
    public function storeSubmission(int $formId, array $snapshot, string $sourceUrl): int
    {
        $this->db->query(
            'INSERT INTO module_form_submissions (form_id, data_json, source_url, status, submitted_at) VALUES (?, ?, ?, ?, NOW())',
            [
                $formId,
                json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                function_exists('mb_substr') ? mb_substr($sourceUrl, 0, 2048) : substr($sourceUrl, 0, 2048),
                'new',
            ]
        );
        return $this->db->getInsertId();
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function submissionFilter(int $formId, string $status, string $search): array
    {
        $conditions = [];
        $params = [];

        if ($formId > 0) {
            $conditions[] = 's.form_id = ?';
            $params[] = $formId;
        }
        if (in_array($status, ['new', 'read'], true)) {
            $conditions[] = 's.status = ?';
            $params[] = $status;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . (function_exists('mb_substr') ? mb_substr($search, 0, 150) : substr($search, 0, 150)) . '%';
            $conditions[] = '(f.name LIKE ? OR s.source_url LIKE ? OR s.data_json LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }

    public function countSubmissions(int $formId = 0, string $status = '', string $search = ''): int
    {
        [$where, $params] = $this->submissionFilter($formId, $status, $search);
        return (int)$this->db->fetchValue(
            'SELECT COUNT(*) FROM module_form_submissions s JOIN module_forms f ON f.id = s.form_id' . $where,
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function submissions(int $formId = 0, string $status = '', string $search = '', int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(250, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->submissionFilter($formId, $status, $search);
        return $this->db->fetchArray(
            'SELECT s.id, s.form_id, s.source_url, s.status, s.submitted_at, f.name AS form_name '
            . 'FROM module_form_submissions s JOIN module_forms f ON f.id = s.form_id'
            . $where . ' ORDER BY s.submitted_at DESC, s.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    public function findSubmission(int $id): ?array
    {
        if ($id < 1) return null;
        $rows = $this->db->fetchArray(
            'SELECT s.*, f.name AS form_name FROM module_form_submissions s JOIN module_forms f ON f.id = s.form_id WHERE s.id = ? LIMIT 1',
            [$id]
        );
        if (!$rows) return null;
        $row = $rows[0];
        $decoded = json_decode((string)$row['data_json'], true);
        $row['data'] = is_array($decoded) ? $decoded : [];
        return $row;
    }

    public function setSubmissionStatus(int $id, string $status): void
    {
        if ($id < 1 || !in_array($status, ['new', 'read'], true)) return;
        $this->db->query('UPDATE module_form_submissions SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function markSubmissionRead(int $id): void
    {
        $this->setSubmissionStatus($id, 'read');
    }

    public function deleteSubmission(int $id): bool
    {
        if ($id < 1 || !$this->findSubmission($id)) return false;
        $this->db->query('DELETE FROM module_form_submissions WHERE id = ?', [$id]);
        return true;
    }

    private function ensureColumn(string $table, string $column, string $definition): bool
    {
        $allowedTables = ['module_forms', 'module_form_fields'];
        $allowedColumns = [
            'success_action', 'success_redirect_page_id', 'notification_subject', 'reply_to_field_key',
            'send_confirmation', 'confirmation_email_field_key', 'confirmation_subject', 'confirmation_message',
            'row_layout', 'row_order', 'column_index',
        ];
        if (!in_array($table, $allowedTables, true) || !in_array($column, $allowedColumns, true)) {
            throw new InvalidArgumentException('Unsupported Forms schema migration.');
        }

        $exists = (int)$this->db->fetchValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
        if (!$exists) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
            return true;
        }
        return false;
    }
}
