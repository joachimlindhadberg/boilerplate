<?php

declare(strict_types=1);

/** Read-only, Core-owned Page Builder presets. Never stores editor or page state. */
final class PageTemplateCatalog
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $path = dirname(__DIR__) . '/templates/pages/standard.json';
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Page template catalog is missing.');
        }
        $catalog = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($catalog) || !array_is_list($catalog) || count($catalog) < 1 || count($catalog) > 20) {
            throw new UnexpectedValueException('Invalid Page template catalog.');
        }
        $result = [];
        $ids = [];
        foreach ($catalog as $entry) {
            if (!is_array($entry)) throw new UnexpectedValueException('Invalid Page template entry.');
            $id = $entry['id'] ?? null;
            $titleKey = $entry['title_key'] ?? null;
            $descriptionKey = $entry['description_key'] ?? null;
            $blocks = $entry['blocks'] ?? null;
            if (!is_string($id) || preg_match('/^[a-z][a-z0-9_-]{0,39}$/D', $id) !== 1
                || isset($ids[$id]) || !self::isTranslationKey($titleKey) || !self::isTranslationKey($descriptionKey)
                || !is_array($blocks) || !array_is_list($blocks) || !$blocks || count($blocks) > 60) {
                throw new UnexpectedValueException('Invalid Page template metadata.');
            }
            $ids[$id] = true;
            $localized = [];
            $preview = [];
            foreach ($blocks as $block) {
                $type = is_array($block) ? ($block['type'] ?? null) : null;
                $data = is_array($block) ? ($block['data'] ?? null) : null;
                if (!is_string($type) || !isset(PageBlocks::definitions()[$type]) || !is_array($data)
                    || array_key_exists('section_id', $data) || array_key_exists('status', $block)) {
                    throw new UnexpectedValueException('Invalid Page template block.');
                }
                // Avoid shipping media or foreign record references from templates.
                $rejectReferences = static function (array $values) use (&$rejectReferences): void {
                    foreach ($values as $key => $value) {
                        if (in_array((string)$key, ['media_id', 'image_id', 'form_id', 'gallery_id', 'component_id'], true)
                            || ((string)$key === 'image_path' && $value !== '')) {
                            throw new UnexpectedValueException('Page template cannot include media/foreign references.');
                        }
                        if (is_array($value)) $rejectReferences($value);
                    }
                };
                $rejectReferences($data);
                if ($type === 'component') {
                    // A template must never contain references to a missing/foreign form or gallery.
                    throw new UnexpectedValueException('Page templates must not reference dynamic components.');
                }
                if ($type === 'grid') {
                    $count = $data['column_count'] ?? null;
                    $columns = $data['columns'] ?? null;
                    if (!in_array($count, [1, 2, 3, 4, 5, 6], true) || ($data['preset'] ?? null) !== 'equal'
                        || ($data['layout_version'] ?? null) !== 'v2' || !is_array($columns)
                        || !array_is_list($columns) || count($columns) !== $count) {
                        throw new UnexpectedValueException('Invalid Page template row.');
                    }
                    foreach ($columns as $column) {
                        $children = is_array($column) ? ($column['blocks'] ?? null) : null;
                        if (!is_array($children) || !array_is_list($children) || count($children) > 1) {
                            throw new UnexpectedValueException('Invalid Page template column.');
                        }
                        foreach ($children as $child) {
                            if (!is_array($child) || !in_array(($child['type'] ?? null), ['text', 'text_image', 'cta'], true)
                                || !is_array($child['data'] ?? null)) {
                                throw new UnexpectedValueException('Invalid Page template child block.');
                            }
                        }
                    }
                    $preview[] = $count;
                } else {
                    $preview[] = 1;
                }
                $localized[] = ['type' => $type, 'status' => 'active', 'data' => self::localize($data)];
            }
            $validated = PageBlocks::normalizeSubmitted($localized);
            if (!empty($validated['errors']) || count($validated['blocks']) !== count($localized)) {
                throw new UnexpectedValueException('Invalid Page template content.');
            }
            // Section IDs are assigned to each new editor instance in JavaScript and
            // verified/regenerated by PageBlocks on save. No shared template IDs.
            foreach ($validated['blocks'] as &$block) unset($block['data']['section_id']);
            unset($block);
            $result[] = [
                'id' => $id,
                'title' => t($titleKey),
                'description' => t($descriptionKey),
                'blocks' => $validated['blocks'],
                'preview' => $preview,
                'preview_blocks' => array_map(static function (array $block): array {
                    $data = $block['data'] ?? [];
                    if (($block['type'] ?? '') === 'grid') {
                        return [
                            'type' => 'grid',
                            'label' => t('page_templates.preview_grid'),
                            'columns' => array_map(static function (array $column): string {
                                $children = $column['blocks'] ?? [];
                                $child = $children[0] ?? null;
                                if (!is_array($child)) return t('page_templates.empty_slot');
                                return (string)($child['data']['title'] ?? t((string)(PageBlocks::gridChildDefinitions()[$child['type']]['label'] ?? 'Blok')));
                            }, $data['columns'] ?? []),
                        ];
                    }
                    return [
                        'type' => $block['type'],
                        'label' => t((string)(PageBlocks::definitions()[$block['type']]['label'] ?? 'Blok')),
                        'title' => (string)($data['title'] ?? ''),
                    ];
                }, $validated['blocks']),
            ];
        }
        return $result;
    }

    private static function isTranslationKey(mixed $key): bool
    {
        return is_string($key) && preg_match('/^page_templates\.[a-z_0-9]+\.[a-z_0-9]+$/D', $key) === 1;
    }

    private static function localize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::localize($value);
            } elseif (is_string($value) && str_starts_with($value, '@')) {
                $keyName = substr($value, 1);
                if (!self::isTranslationKey($keyName)) {
                    throw new UnexpectedValueException('Invalid Page template translation key.');
                }
                $data[$key] = t($keyName);
            }
        }
        return $data;
    }
}
