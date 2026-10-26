<?php

/**
 * Core media library for reusable images and videos.
 */
class MediaLibrary
{
    protected Db $db;

    private const MAX_UPLOAD_BYTES = 104857600; // 100 MB per file.

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => ['extension' => 'jpg', 'kind' => 'image'],
        'image/png' => ['extension' => 'png', 'kind' => 'image'],
        'image/webp' => ['extension' => 'webp', 'kind' => 'image'],
        'image/gif' => ['extension' => 'gif', 'kind' => 'image'],
        'video/mp4' => ['extension' => 'mp4', 'kind' => 'video'],
        'video/webm' => ['extension' => 'webm', 'kind' => 'video'],
        'video/quicktime' => ['extension' => 'mov', 'kind' => 'video'],
    ];

    public function __construct()
    {
        $this->db = new Db();
    }

    public static function maxUploadBytes(): int
    {
        return self::MAX_UPLOAD_BYTES;
    }

    public static function allowedKinds(): array
    {
        return ['image', 'video'];
    }

    public function countFiltered(string $search = '', string $kind = ''): int
    {
        [$where, $params] = $this->buildFilters($search, $kind);
        return (int)$this->db->fetchValue(
            'SELECT COUNT(*) FROM media' . ($where !== '' ? ' WHERE ' . $where : ''),
            $params
        );
    }

    public function getFiltered(string $search = '', string $kind = '', int $limit = 24, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->buildFilters($search, $kind);
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->db->fetchArray(
            'SELECT m.*, CONCAT_WS(" ", u.firstname, u.lastname) AS uploader_name '
            . 'FROM media m LEFT JOIN users u ON u.id = m.created_by'
            . ($where !== '' ? ' WHERE ' . $where : '')
            . ' ORDER BY m.created_at DESC, m.id DESC LIMIT ? OFFSET ?',
            $params
        );
        foreach ($rows as &$row) {
            $row['public_url'] = $this->publicUrl((string)($row['file_path'] ?? ''));
            $row['variants'] = ($row['media_kind'] ?? '') === 'image'
                ? ImageVariants::forPath($row['public_url']) : [];
        }
        unset($row);
        return $rows;
    }

    public function getPickerImages(string $search = '', int $limit = 200): array
    {
        return $this->getFiltered($search, 'image', min(200, max(1, $limit)), 0);
    }

    public function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $rows = $this->db->fetchArray(
            'SELECT m.*, CONCAT_WS(" ", u.firstname, u.lastname) AS uploader_name '
            . 'FROM media m LEFT JOIN users u ON u.id = m.created_by WHERE m.id = ? LIMIT 1',
            [$id]
        );
        $record = $rows[0] ?? null;
        if (is_array($record)) {
            $record['public_url'] = $this->publicUrl((string)($record['file_path'] ?? ''));
            $record['variants'] = ($record['media_kind'] ?? '') === 'image'
                ? ImageVariants::forPath($record['public_url']) : [];
        }
        return $record;
    }

    /**
     * Resolve the public URL for media while keeping legacy /assets/uploads paths
     * compatible with the current /uploads storage location (and vice versa).
     */
    public function publicUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $path = '/' . ltrim($path, '/');
        if (str_contains($path, '..')) {
            return '';
        }

        $candidates = [$path];
        if (str_starts_with($path, '/assets/uploads/')) {
            $candidates[] = '/uploads/' . substr($path, strlen('/assets/uploads/'));
        } elseif (str_starts_with($path, '/uploads/')) {
            $candidates[] = '/assets/uploads/' . substr($path, strlen('/uploads/'));
        }

        foreach ($candidates as $candidate) {
            $absolute = $this->absolutePublicPath($candidate);
            if ($absolute !== null && is_file($absolute)) {
                return $candidate;
            }
        }

        return $path;
    }

    public function updateMetadata(int $id, array $data): bool
    {
        $media = $this->find($id);
        if (!$media) {
            return false;
        }

        $title = $this->trimmed($data['title'] ?? '', 255);
        $altText = $this->trimmed($data['alt_text'] ?? '', 255);
        $description = $this->trimmed($data['description'] ?? '', 4000);

        if (($media['media_kind'] ?? '') !== 'image') {
            $altText = '';
        }

        $this->db->query(
            'UPDATE media SET title = ?, alt_text = ?, description = ?, updated_at = NOW() WHERE id = ?',
            [$title !== '' ? $title : null, $altText !== '' ? $altText : null, $description !== '' ? $description : null, $id]
        );
        return true;
    }

    /**
     * Store one or more PHP upload entries. Returns uploaded media records.
     */
    public function uploadMany(array $files, ?int $userId): array
    {
        $normalized = $this->normalizeFilesArray($files);
        if (!$normalized) {
            throw new RuntimeException('Vælg mindst én fil at uploade.');
        }

        $uploaded = [];
        $this->db->beginTransaction();
        try {
            foreach ($normalized as $file) {
                $uploaded[] = $this->uploadOne($file, $userId);
            }
            $this->db->commit();
            return $uploaded;
        } catch (Throwable $e) {
            $this->db->rollback();
            // Database rollback cannot undo filesystem writes. Remove only
            // files successfully uploaded by this exact batch.
            foreach ($uploaded as $record) {
                ImageVariants::deleteArtifacts((string)($record['file_path'] ?? ''));
            }
            throw $e;
        }
    }

    public function delete(int $id): array
    {
        // Both the indexed snapshot and the media row must be checked/deleted
        // atomically. The state-row lock also serializes Page Builder writes.
        $this->db->beginTransaction();
        try {
            $media = $this->find($id);
            if (!$media) {
                $this->db->rollback();
                return ['ok' => false, 'message' => 'Mediet findes ikke længere.'];
            }

            $usage = $this->usage($media);
            if ($usage['count'] > 0) {
                $this->db->rollback();
                return [
                    'ok' => false,
                    'message' => 'Mediet bruges stadig på ' . $usage['count'] . ' sted' . ($usage['count'] === 1 ? '' : 'er') . ' og kan derfor ikke slettes.',
                    'usage' => $usage,
                ];
            }
            $this->db->query('DELETE FROM media WHERE id = ?', [$id]);
            $this->db->commit();
        } catch (mysqli_sql_exception $error) {
            $this->db->rollback();
            if ((int)$error->getCode() === 1451 || $error->getSqlState() === '45000') {
                return [
                    'ok' => false,
                    'message' => 'Mediet er stadig i brug, eller medieregisteret skal opdateres. Intet er slettet.',
                ];
            }
            throw $error;
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }

        // Filesystem deletion happens only after the DB transaction commits.
        // A failure is logged as a cleanup task, never as a successful wipe.
        $path = (string)($media['file_path'] ?? '');
        // Delete derived files and metadata together with the original; legacy media
        // without derivatives is still supported.
        ImageVariants::deleteArtifacts($this->publicUrl($path));
        return ['ok' => true, 'media' => $media];
    }

    /**
     * Protected media-usage inspection. Deliberately requires the caller's
     * transaction: callers must hold a lock until the deletion decision ends.
     */
    public function usage(array $media): array
    {
        $id = (int)($media['id'] ?? 0);
        $path = (string)($media['file_path'] ?? '');
        if ($id < 1) {
            return ['count' => 0, 'page_ids' => [], 'modules' => []];
        }

        // Rebuild only when triggers have marked source data dirty. Normal
        // media deletions use one indexed read rather than scanning every block.
        $pageIds = [];
        foreach ((new MediaReferenceIndex($this->db))->pageIdsForMedia($id) as $pageId) {
            $pageIds[$pageId] = true;
        }

        // Legacy raw HTML is not structured Page Builder content. Check it
        // directly, including both supported upload locations and escaped
        // LIKE wildcards, until a separate structured conversion is available.
        foreach (MediaReferenceIndex::pathAliases($path) as $candidate) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $candidate);
            $legacyRows = $this->db->fetchArray(
                "SELECT id FROM pages WHERE page_content LIKE ? ESCAPE '!'",
                ['%' . $escaped . '%']
            );
            foreach ($legacyRows as $row) {
                $pageIds[(int)$row['id']] = true;
            }
        }

        // Inactive modules still own their data; never allow disabling a
        // plugin to bypass reference protection. SQL errors fail closed.
        $moduleUsage = [];
        foreach (ModuleMediaReferences::all() as $reference) {
            $table = $reference['table'];
            $column = $reference['column'];
            $label = $reference['label'];
            try {
                $count = (int)$this->db->fetchValue(
                    'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` = ?', [$id]
                );
                if ($count > 0) $moduleUsage[$label] = ($moduleUsage[$label] ?? 0) + $count;
            } catch (mysqli_sql_exception $error) {
                if ((int)$error->getCode() !== 1146) throw $error;
            }
        }

        return [
            'count' => count($pageIds) + array_sum($moduleUsage),
            'page_ids' => array_keys($pageIds),
            'modules' => $moduleUsage,
        ];
    }

    /** Installer-only schema creation, deliberately absent from runtime constructor. */
    public static function installSchema(Db $db): void
    {
        $db->query(
            "CREATE TABLE IF NOT EXISTS media (\n"
            . "id int NOT NULL AUTO_INCREMENT,\n"
            . "filename varchar(255) NOT NULL,\n"
            . "original_filename varchar(255) NOT NULL,\n"
            . "file_path varchar(500) NOT NULL,\n"
            . "mime_type varchar(100) NOT NULL,\n"
            . "media_kind enum('image','video') NOT NULL,\n"
            . "file_size bigint unsigned NOT NULL DEFAULT 0,\n"
            . "width int unsigned DEFAULT NULL,\n"
            . "height int unsigned DEFAULT NULL,\n"
            . "title varchar(255) DEFAULT NULL,\n"
            . "alt_text varchar(255) DEFAULT NULL,\n"
            . "description text DEFAULT NULL,\n"
            . "created_by int DEFAULT NULL,\n"
            . "created_at datetime DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY file_path (file_path),\n"
            . "KEY media_kind_created (media_kind, created_at),\n"
            . "KEY created_by (created_by),\n"
            . "CONSTRAINT media_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function uploadOne(array $file, ?int $userId): array
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($error));
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $originalName = trim((string)($file['name'] ?? 'fil'));

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Uploaden kunne ikke valideres.');
        }
        if ($size < 1) {
            throw new RuntimeException('Filen er tom.');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('Filen ' . $originalName . ' er større end 100 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$finfo->file($tmp));
        $definition = self::ALLOWED_MIME_TYPES[$mime] ?? null;
        if (!$definition) {
            throw new RuntimeException('Filtypen for ' . $originalName . ' understøttes ikke.');
        }

        $year = gmdate('Y');
        $month = gmdate('m');
        $relativeDir = '/uploads/' . $year . '/' . $month;
        $absoluteDir = PUBLICPATH . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Upload-mappen kunne ikke oprettes.');
        }
        if (!is_writable($absoluteDir)) {
            throw new RuntimeException('Upload-mappen er ikke skrivbar.');
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = $this->safeFileBase($base);
        $storedName = $safeBase . '-' . bin2hex(random_bytes(5)) . '.' . $definition['extension'];
        $relativePath = $relativeDir . '/' . $storedName;
        $absolutePath = $absoluteDir . '/' . $storedName;

        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new RuntimeException('Filen kunne ikke gemmes på serveren.');
        }
        @chmod($absolutePath, 0644);

        $width = null;
        $height = null;
        if ($definition['kind'] === 'image') {
            $imageInfo = @getimagesize($absolutePath);
            if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
                @unlink($absolutePath);
                throw new RuntimeException('Billedfilen kunne ikke læses eller er beskadiget.');
            }
            $width = (int)$imageInfo[0];
            $height = (int)$imageInfo[1];
        }

        // An image editor is optional. A failed optimization must never make a
        // valid original image disappear or abort the upload.
        if ($definition['kind'] === 'image') {
            ImageVariants::generate($absolutePath, $relativePath, $mime, (int)$width, (int)$height);
        }

        $title = $this->humanTitle($base);
        try {
            $this->db->query(
                'INSERT INTO media (filename, original_filename, file_path, mime_type, media_kind, file_size, width, height, title, alt_text, description, created_by, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, NOW(), NOW())',
                [
                    $storedName,
                    function_exists('mb_substr') ? mb_substr($originalName, 0, 255) : substr($originalName, 0, 255),
                    $relativePath,
                    $mime,
                    $definition['kind'],
                    $size,
                    $width,
                    $height,
                    $title !== '' ? $title : null,
                    $userId,
                ]
            );
        } catch (Throwable $e) {
            ImageVariants::deleteArtifacts($relativePath);
            throw $e;
        }

        try {
            $record = $this->find($this->db->getInsertId());
            if (!$record) {
                throw new RuntimeException('Mediet blev uploadet, men kunne ikke læses fra databasen.');
            }
            return $record;
        } catch (Throwable $e) {
            ImageVariants::deleteArtifacts($relativePath);
            throw $e;
        }
    }

    private function buildFilters(string $search, string $kind): array
    {
        $where = [];
        $params = [];

        $kind = strtolower(trim($kind));
        if (in_array($kind, self::allowedKinds(), true)) {
            $where[] = 'media_kind = ?';
            $params[] = $kind;
        }

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(filename LIKE ? OR original_filename LIKE ? OR title LIKE ? OR alt_text LIKE ? OR description LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [implode(' AND ', $where), $params];
    }

    private function normalizeFilesArray(array $files): array
    {
        $names = $files['name'] ?? [];
        if (!is_array($names)) {
            return [[
                'name' => $files['name'] ?? '',
                'type' => $files['type'] ?? '',
                'tmp_name' => $files['tmp_name'] ?? '',
                'error' => $files['error'] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'] ?? 0,
            ]];
        }

        $result = [];
        foreach ($names as $index => $name) {
            $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $result[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $error,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
        return $result;
    }

    private function dataUsesMedia(array $data, int $id, string $path): bool
    {
        foreach ($data as $key => $value) {
            if ($key === 'image_id' && (int)$value === $id) {
                return true;
            }
            if ($path !== '' && $key === 'image_path' && (string)$value === $path) {
                return true;
            }
            if (is_array($value) && $this->dataUsesMedia($value, $id, $path)) {
                return true;
            }
        }
        return false;
    }

    private function absolutePublicPath(string $path): ?string
    {
        if (!str_starts_with($path, '/uploads/') && !str_starts_with($path, '/assets/uploads/')) {
            return null;
        }
        $relative = ltrim($path, '/');
        if (str_contains($relative, '..')) {
            return null;
        }
        return PUBLICPATH . '/' . $relative;
    }

    private function safeFileBase(string $value): string
    {
        $value = trim($value);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value !== '' ? substr($value, 0, 80) : 'media';
    }

    private function humanTitle(string $value): string
    {
        $value = preg_replace('/[-_]+/', ' ', trim($value)) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        if ($value === '') {
            return '';
        }
        return function_exists('mb_convert_case')
            ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8')
            : ucwords($value);
    }

    private function trimmed(mixed $value, int $max): string
    {
        $value = trim((string)$value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Filen er større end serverens tilladte uploadstørrelse.',
            UPLOAD_ERR_PARTIAL => 'Uploaden blev afbrudt. Prøv igen.',
            UPLOAD_ERR_NO_FILE => 'Vælg en fil at uploade.',
            UPLOAD_ERR_NO_TMP_DIR => 'Serverens midlertidige uploadmappe mangler.',
            UPLOAD_ERR_CANT_WRITE => 'Serveren kunne ikke skrive filen til disk.',
            UPLOAD_ERR_EXTENSION => 'En serverudvidelse stoppede uploaden.',
            default => 'Uploaden mislykkedes.',
        };
    }
}
