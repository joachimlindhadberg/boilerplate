<?php
/**
 * Controlled page-builder blocks.
 *
 * The builder deliberately stores content/config only. Frontend layout and
 * styling remain defined in code so CMS users cannot create arbitrary markup.
 */
class PageBlocks
{
    protected Db $db;

    private const STATUSES = ['active', 'hidden'];
    private const MAX_TOP_LEVEL_BLOCKS = 60;
    private const MAX_GRID_COLUMNS = 6;
    private const MAX_GRID_CHILDREN_PER_COLUMN = 12;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    public static function definitions(): array
    {
        return [
            'hero' => [
                'label' => 'Hero',
                'description' => 'Stor sideintro med valgfrit baggrundsbillede.',
            ],
            'intro' => [
                'label' => 'Intro',
                'description' => 'Kicker, overskrift og introduktion i to kolonner.',
            ],
            'text' => [
                'label' => 'Tekstsektion',
                'description' => 'Kontrolleret tekstsektion med valgfri overskrift.',
            ],
            'text_image' => [
                'label' => 'Tekst + billede',
                'description' => 'Tekst og billede i et responsivt split-layout.',
            ],
            'cta' => [
                'label' => 'CTA',
                'description' => 'Kort call-to-action med én kontrolleret knap.',
            ],
            'component' => [
                'label' => 'Dynamisk komponent',
                'description' => 'Indsæt en godkendt dynamisk komponent fra databasen.',
            ],
            'grid' => [
                'label' => 'Grid / kolonner',
                'description' => '2–6 responsive kolonner med indhold i hver kolonne.',
            ],
        ];
    }

    /**
     * Block types allowed inside a grid column.
     * Nested grids and heroes are intentionally excluded by design.
     */
    public static function gridChildDefinitions(): array
    {
        return [
            'text' => [
                'label' => 'Tekst',
                'description' => 'Overskrift og brødtekst.',
            ],
            'image' => [
                'label' => 'Billede',
                'description' => 'Et enkelt responsivt billede.',
            ],
            'text_image' => [
                'label' => 'Tekst + billede',
                'description' => 'Billede, overskrift og tekst i samme kolonne.',
            ],
            'cta' => [
                'label' => 'CTA',
                'description' => 'Kort tekst og én knap.',
            ],
            'component' => [
                'label' => 'Dynamisk komponent',
                'description' => 'En godkendt dynamisk komponent.',
            ],
        ];
    }

    public static function componentOptions(): array
    {
        if (!function_exists('cms_module_manager')) {
            return [];
        }

        try {
            return cms_module_manager()->componentOptions();
        } catch (Throwable $e) {
            error_log('Unable to load module Page Builder components: ' . $e->getMessage());
            return [];
        }
    }

    public static function themes(): array
    {
        return ['light' => 'Lys', 'soft' => 'Soft', 'dark' => 'Mørk'];
    }

    public static function containerWidths(): array
    {
        return ['narrow' => 'Smal', 'standard' => 'Standard', 'wide' => 'Bred', 'full' => 'Fuld bredde'];
    }

    public static function normalContainerWidth(string $candidate): string
    {
        return array_key_exists($candidate, self::containerWidths()) ? $candidate : 'standard';
    }

    /** Keep old placements intact while validating the new per-placement settings. */
    private static function galleryDisplaySettings(string $component, array $data): array
    {
        if (!preg_match('/^gallery:gallery-[1-9][0-9]*$/D', $component)) return [];
        if (isset($data['gallery_mode']) && in_array($data['gallery_mode'], ['lightbox', 'slider'], true)) {
            return [
                'gallery_mode' => $data['gallery_mode'],
                'gallery_columns' => max(2, min(6, (int)($data['gallery_columns'] ?? 3))),
                'gallery_fullscreen' => (string)($data['gallery_fullscreen'] ?? '1') === '0' ? '0' : '1',
                'gallery_autoplay' => (string)($data['gallery_autoplay'] ?? '0') === '1' ? '1' : '0',
                'gallery_interval' => max(3, min(10, (int)($data['gallery_interval'] ?? 5))),
            ];
        }
        // Previously saved 4A.6.9i placements: honor their original choices.
        $legacy = [];
        if (isset($data['gallery_layout'])) $legacy['gallery_layout'] = $data['gallery_layout'] === 'slider' ? 'slider' : 'grid';
        if (isset($data['gallery_lightbox'])) $legacy['gallery_lightbox'] = in_array($data['gallery_lightbox'], ['on','off','inherit'], true) ? $data['gallery_lightbox'] : 'inherit';
        return $legacy;
    }

    public static function gridGaps(): array
    {
        return [
            'compact' => 'Kompakt',
            'normal' => 'Normal',
            'large' => 'Stor',
        ];
    }

    /**
     * Presets are intentionally finite so editors cannot create arbitrary widths.
     */
    public static function gridPresets(int $columnCount): array
    {
        return match ($columnCount) {
            1 => ['equal' => '100 %'],
            2 => [
                'equal' => '50 / 50',
                '33-67' => '33 / 67',
                '67-33' => '67 / 33',
                '40-60' => '40 / 60',
                '60-40' => '60 / 40',
            ],
            3 => [
                'equal' => '33 / 33 / 33',
                '25-50-25' => '25 / 50 / 25',
                '50-25-25' => '50 / 25 / 25',
                '25-25-50' => '25 / 25 / 50',
            ],
            4 => ['equal' => '25 / 25 / 25 / 25'],
            5 => ['equal' => '20 / 20 / 20 / 20 / 20'],
            6 => ['equal' => '16 / 16 / 16 / 16 / 16 / 16'],
            default => ['equal' => 'Lige brede kolonner'],
        };
    }

    public function getForPage(int $pageId, bool $activeOnly = false): array
    {
        if ($pageId < 1) {
            return [];
        }
        $where = $activeOnly ? " AND status = 'active'" : '';
        $rows = $this->db->fetchArray(
            'SELECT id, page_id, block_type, block_data, sort_order, status, created_at, updated_at '
            . 'FROM page_blocks WHERE page_id = ?' . $where . ' ORDER BY sort_order ASC, id ASC',
            [$pageId]
        );
        foreach ($rows as &$row) {
            $decoded = json_decode((string)($row['block_data'] ?? ''), true);
            $row['data'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);
        return $rows;
    }

    public function countForPage(int $pageId, bool $activeOnly = false): int
    {
        if ($pageId < 1) {
            return 0;
        }
        $where = $activeOnly ? " AND status = 'active'" : '';
        return (int)$this->db->fetchValue('SELECT COUNT(*) FROM page_blocks WHERE page_id = ?' . $where, [$pageId]);
    }

    /**
     * Replace a page's complete block list atomically. Blocks have no external
     * references, so replacement is intentionally simpler and safer than a
     * complex per-block diff.
     */
    public function replaceForPage(int $pageId, array $blocks, bool $manageTransaction = true): void
    {
        if ($pageId < 1) {
            throw new InvalidArgumentException('Invalid page id.');
        }

        if ($manageTransaction) $this->db->beginTransaction();
        try {
            $this->db->query('DELETE FROM page_blocks WHERE page_id = ?', [$pageId]);
            $sort = 0;
            foreach ($blocks as $block) {
                $type = (string)($block['type'] ?? '');
                if (!array_key_exists($type, self::definitions())) {
                    continue;
                }
                $status = in_array(($block['status'] ?? 'active'), self::STATUSES, true)
                    ? (string)$block['status'] : 'active';
                $data = is_array($block['data'] ?? null) ? $block['data'] : [];
                $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $this->db->query(
                    'INSERT INTO page_blocks (page_id, block_type, block_data, sort_order, status, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                    [$pageId, $type, $json, $sort, $status]
                );
                $sort++;
            }
            if ($manageTransaction) $this->db->commit();
        } catch (Throwable $e) {
            if ($manageTransaction) $this->db->rollback();
            throw $e;
        }
    }

    public static function normalizeSubmitted(array $submitted): array
    {
        $definitions = self::definitions();
        $blocks = [];
        $errors = [];
        // Top-level section IDs live inside block_data so the existing database,
        // revision snapshots, imports and media references keep their formats.
        $usedSectionIds = [];

        if (count($submitted) > self::MAX_TOP_LEVEL_BLOCKS) {
            $errors[] = 'En side kan højst indeholde ' . self::MAX_TOP_LEVEL_BLOCKS . ' sektioner.';
            $submitted = array_slice($submitted, 0, self::MAX_TOP_LEVEL_BLOCKS);
        }

        foreach (array_values($submitted) as $position => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $type = trim((string)($raw['type'] ?? ''));
            if (!isset($definitions[$type])) {
                $errors[] = 'Sektion ' . ($position + 1) . ' har en ukendt type.';
                continue;
            }

            $status = (($raw['status'] ?? 'active') === 'hidden') ? 'hidden' : 'active';
            $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
            $clean = self::normalizeTopLevelData($type, $data, $position, $errors);
            // Preserve valid IDs; repair missing/duplicate/invalid IDs on save.
            // Never regenerate IDs during frontend rendering.
            $clean['section_id'] = self::uniqueSectionId($data['section_id'] ?? null, $usedSectionIds);
            // One consistent top-level setting for all sections, including Hero and Grid.
            $clean['theme'] = self::normalTheme((string)($data['theme'] ?? ($type === 'hero' ? 'dark' : 'light')));
            $clean['container_width'] = self::normalContainerWidth((string)($data['container_width'] ?? 'standard'));
            $blocks[] = ['type' => $type, 'status' => $status, 'data' => $clean];
        }

        return ['blocks' => $blocks, 'errors' => $errors];
    }

    /** Allocate a stable unique HTML anchor for each top-level section on a page.
     * A copied section must not reuse an existing section's ID.
     * The ID is supplied only by data normalization on save, not by reads/renders.
     */
    private static function uniqueSectionId(mixed $candidate, array &$used): string
    {
        if (is_string($candidate)
            && preg_match('/^section-[0-9]{6}$/D', $candidate) === 1
            && !isset($used[$candidate])) {
            $used[$candidate] = true;
            return $candidate;
        }

        do {
            $id = 'section-' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (isset($used[$id]));
        $used[$id] = true;
        return $id;
    }

    private static function normalizeTopLevelData(string $type, array $data, int $position, array &$errors): array
    {
        $text = static fn(array $source, string $key, int $max = 5000): string => self::trimmed($source[$key] ?? '', $max);
        $theme = static fn(array $source): string => self::normalTheme((string)($source['theme'] ?? 'light'));

        switch ($type) {
            case 'hero':
                $clean = [
                    'kicker' => $text($data, 'kicker', 160),
                    'title' => $text($data, 'title', 255),
                    'lead' => $text($data, 'lead', 1200),
                    'image_id' => max(0, (int)($data['image_id'] ?? 0)),
                    'image_path' => self::safePath($text($data, 'image_path', 500)),
                    'primary_label' => $text($data, 'primary_label', 80),
                    'primary_url' => self::safeUrl($text($data, 'primary_url', 500)),
                    'secondary_label' => $text($data, 'secondary_label', 80),
                    'secondary_url' => self::safeUrl($text($data, 'secondary_url', 500)),
                ];
                if ($clean['title'] === '') {
                    $errors[] = 'Hero-sektionen på placering ' . ($position + 1) . ' mangler en overskrift.';
                }
                return $clean;

            case 'intro':
                return [
                    'kicker' => $text($data, 'kicker', 160),
                    'title' => $text($data, 'title', 255),
                    'body' => $text($data, 'body', 12000),
                    'theme' => $theme($data),
                ];

            case 'text':
                return [
                    'kicker' => $text($data, 'kicker', 160),
                    'title' => $text($data, 'title', 255),
                    'body' => $text($data, 'body', 16000),
                    'theme' => $theme($data),
                    'layout' => (($data['layout'] ?? 'split') === 'stacked') ? 'stacked' : 'split',
                ];

            case 'text_image':
                return [
                    'kicker' => $text($data, 'kicker', 160),
                    'title' => $text($data, 'title', 255),
                    'body' => $text($data, 'body', 12000),
                    'image_id' => max(0, (int)($data['image_id'] ?? 0)),
                    'image_path' => self::safePath($text($data, 'image_path', 500)),
                    'image_alt' => $text($data, 'image_alt', 255),
                    'image_side' => (($data['image_side'] ?? 'right') === 'left') ? 'left' : 'right',
                    'theme' => $theme($data),
                ];

            case 'cta':
                return [
                    'kicker' => $text($data, 'kicker', 160),
                    'title' => $text($data, 'title', 255),
                    'body' => $text($data, 'body', 3000),
                    'button_label' => $text($data, 'button_label', 80),
                    'button_url' => self::safeUrl($text($data, 'button_url', 500)),
                    'theme' => $theme($data),
                ];

            case 'component':
                $component = self::normalComponent((string)($data['component'] ?? ''));
                if ($component === '') {
                    $errors[] = 'Dynamisk komponent på placering ' . ($position + 1) . ' er ikke gyldig.';
                }
                return [
                    'kicker' => $text($data, 'kicker', 160),
                    'title' => $text($data, 'title', 255),
                    'component' => $component,
                    'theme' => $theme($data),
                    ...self::galleryDisplaySettings($component, $data),
                ];

            case 'grid':
                return self::normalizeGridData($data, $position, $errors);
        }

        return [];
    }

    private static function normalizeGridData(array $data, int $position, array &$errors): array
    {
        $count = (int)($data['column_count'] ?? 2);
        $isV2 = ($data['layout_version'] ?? null) === 'v2';
        if ($isV2 && ($count < 1 || $count > self::MAX_GRID_COLUMNS)) {
            $errors[] = 'Row ' . ($position + 1) . ' skal have mellem 1 og 6 kolonner.';
        }
        $count = max($isV2 ? 1 : 2, min(self::MAX_GRID_COLUMNS, $count));

        $presets = self::gridPresets($count);
        $preset = (string)($data['preset'] ?? 'equal');
        if (!isset($presets[$preset])) {
            $preset = 'equal';
        }

        $gaps = self::gridGaps();
        $gap = (string)($data['gap'] ?? 'normal');
        if (!isset($gaps[$gap])) {
            $gap = 'normal';
        }

        $columnsRaw = $data['columns'] ?? [];
        if (!is_array($columnsRaw)) {
            $columnsRaw = [];
        }
        // Reject invalid/stale V2 submissions instead of silently discarding
        // populated columns that lie outside the requested layout.
        if ($isV2) {
            foreach ($columnsRaw as $idx => $column) {
                if ((int)$idx >= $count && is_array($column) && !empty($column['blocks'])) {
                    $errors[] = 'Row ' . ($position + 1) . ': flyt eller fjern blokke i overskydende kolonner før gemning.';
                    break;
                }
            }
        }

        $columns = [];
        for ($columnIndex = 0; $columnIndex < $count; $columnIndex++) {
            $column = $columnsRaw[$columnIndex] ?? [];
            if (!is_array($column)) {
                $column = [];
            }
            $children = $column['blocks'] ?? [];
            if (!is_array($children)) {
                $children = [];
            }
            if (count($children) > self::MAX_GRID_CHILDREN_PER_COLUMN) {
                $errors[] = 'Grid-sektion ' . ($position + 1) . ', kolonne ' . ($columnIndex + 1)
                    . ' kan højst indeholde ' . self::MAX_GRID_CHILDREN_PER_COLUMN . ' indholdsblokke.';
                $children = array_slice($children, 0, self::MAX_GRID_CHILDREN_PER_COLUMN);
            }

            $cleanChildren = [];
            foreach (array_values($children) as $childIndex => $rawChild) {
                if (!is_array($rawChild)) {
                    continue;
                }
                $child = self::normalizeGridChild($rawChild, $position, $columnIndex, $childIndex, $errors);
                if ($child !== null) {
                    $cleanChildren[] = $child;
                }
            }
            $columns[] = ['blocks' => $cleanChildren];
        }

        $clean = [
            'theme' => self::normalTheme((string)($data['theme'] ?? 'light')),
            'column_count' => $count,
            'preset' => $preset,
            'gap' => $gap,
            'columns' => $columns,
        ];
        // Explicitly marked equal-width 1–6 column rows use V2 markup.
        // Legacy grids (2–6 columns, including asymmetric layouts) remain untouched.
        if ($isV2 && $count >= 1 && $count <= self::MAX_GRID_COLUMNS && $preset === 'equal') {
            $clean['layout_version'] = 'v2';
        }
        return $clean;
    }

    private static function normalizeGridChild(
        array $raw,
        int $sectionPosition,
        int $columnIndex,
        int $childIndex,
        array &$errors
    ): ?array {
        $definitions = self::gridChildDefinitions();
        $type = trim((string)($raw['type'] ?? ''));
        if (!isset($definitions[$type])) {
            $errors[] = 'Grid-sektion ' . ($sectionPosition + 1) . ', kolonne ' . ($columnIndex + 1)
                . ', blok ' . ($childIndex + 1) . ' har en ukendt type.';
            return null;
        }
        $status = (($raw['status'] ?? 'active') === 'hidden') ? 'hidden' : 'active';
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];

        switch ($type) {
            case 'text':
                $clean = [
                    'kicker' => self::trimmed($data['kicker'] ?? '', 160),
                    'title' => self::trimmed($data['title'] ?? '', 255),
                    'body' => self::trimmed($data['body'] ?? '', 12000),
                ];
                break;

            case 'image':
                $clean = [
                    'image_id' => max(0, (int)($data['image_id'] ?? 0)),
                    'image_path' => self::safePath(self::trimmed($data['image_path'] ?? '', 500)),
                    'image_alt' => self::trimmed($data['image_alt'] ?? '', 255),
                    'caption' => self::trimmed($data['caption'] ?? '', 500),
                ];
                break;

            case 'text_image':
                $clean = [
                    'kicker' => self::trimmed($data['kicker'] ?? '', 160),
                    'title' => self::trimmed($data['title'] ?? '', 255),
                    'body' => self::trimmed($data['body'] ?? '', 10000),
                    'image_id' => max(0, (int)($data['image_id'] ?? 0)),
                    'image_path' => self::safePath(self::trimmed($data['image_path'] ?? '', 500)),
                    'image_alt' => self::trimmed($data['image_alt'] ?? '', 255),
                    'image_position' => (($data['image_position'] ?? 'top') === 'bottom') ? 'bottom' : 'top',
                ];
                break;

            case 'cta':
                $clean = [
                    'kicker' => self::trimmed($data['kicker'] ?? '', 160),
                    'title' => self::trimmed($data['title'] ?? '', 255),
                    'body' => self::trimmed($data['body'] ?? '', 3000),
                    'button_label' => self::trimmed($data['button_label'] ?? '', 80),
                    'button_url' => self::safeUrl(self::trimmed($data['button_url'] ?? '', 500)),
                ];
                break;

            case 'component':
                $component = self::normalComponent((string)($data['component'] ?? ''));
                if ($component === '') {
                    $errors[] = 'Grid-sektion ' . ($sectionPosition + 1) . ', kolonne ' . ($columnIndex + 1)
                        . ', blok ' . ($childIndex + 1) . ' har en ugyldig dynamisk komponent.';
                }
                $clean = [
                    'kicker' => self::trimmed($data['kicker'] ?? '', 160),
                    'title' => self::trimmed($data['title'] ?? '', 255),
                    'component' => $component,
                    ...self::galleryDisplaySettings($component, $data),
                ];
                break;

            default:
                return null;
        }

        return ['type' => $type, 'status' => $status, 'data' => $clean];
    }

    private static function trimmed(mixed $value, int $max): string
    {
        $value = trim((string)$value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }
        return substr($value, 0, $max);
    }

    private static function normalTheme(string $value): string
    {
        return isset(self::themes()[$value]) ? $value : 'light';
    }

    private static function normalComponent(string $value): string
    {
        return isset(self::componentOptions()[$value]) ? $value : '';
    }

    private static function safePath(string $value): string
    {
        if ($value === '') return '';
        if (str_starts_with($value, '/uploads/')) return $value;
        if (str_starts_with($value, 'uploads/')) return '/' . $value;
        // Legacy media paths from Boilerplate <= 1.5.x remain readable.
        if (str_starts_with($value, '/assets/uploads/')) return $value;
        if (str_starts_with($value, 'assets/uploads/')) return '/' . $value;
        return '';
    }

    private static function safeUrl(string $value): string
    {
        if ($value === '') return '';
        if (str_starts_with($value, '/') || str_starts_with($value, '#')) return $value;
        if (preg_match('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL)) return $value;
        return '';
    }
}
