<?php

declare(strict_types=1);

/**
 * Read-only Page Builder component references, shared by CMS modules.
 *
 * A matching component field anywhere inside the stored JSON block counts as
 * usage, regardless of publication state. Free text containing an identifier
 * is not a reference. Only distinct page IDs are counted.
 *
 * The full-scan option preserves Gallery's original lookup semantics. Other
 * callers use Forms' original parameterized SQL LIKE prefilter for efficiency.
 * Neither path writes to the database or alters a page.
 */
final class PageComponentUsage
{
    /** @return array{count:int,page_ids:array<int,int>} */
    public static function find(Db $db, string $component, bool $fullScan = false): array
    {
        if ($component === '') {
            return ['count' => 0, 'page_ids' => []];
        }

        $rows = $fullScan
            ? $db->fetchArray('SELECT page_id, block_data FROM page_blocks')
            : $db->fetchArray(
                'SELECT page_id, block_data FROM page_blocks WHERE block_data LIKE ?',
                ['%' . $component . '%']
            );

        $pageIds = [];
        foreach ($rows as $row) {
            $data = json_decode((string)($row['block_data'] ?? ''), true);
            if (is_array($data) && self::containsComponent($data, $component)) {
                $pageIds[(int)$row['page_id']] = true;
            }
        }
        return ['count' => count($pageIds), 'page_ids' => array_keys($pageIds)];
    }

    /**
     * Find several component IDs in one read-only scan, preserving the same
     * exact component matching and distinct page counting as find().
     *
     * @param list<string> $components
     * @return array<string,array{count:int,page_ids:array<int,int>}>
     */
    public static function findMany(Db $db, array $components): array
    {
        $pagesByComponent = [];
        foreach ($components as $component) {
            if (is_string($component) && $component !== '') {
                $pagesByComponent[$component] = [];
            }
        }
        if ($pagesByComponent === []) return [];

        $targets = array_fill_keys(array_keys($pagesByComponent), true);
        foreach ($db->fetchArray('SELECT page_id, block_data FROM page_blocks') as $row) {
            $data = json_decode((string)($row['block_data'] ?? ''), true);
            if (!is_array($data)) continue;
            $matches = [];
            self::collectMatches($data, $targets, $matches);
            foreach ($matches as $component => $_) {
                $pagesByComponent[$component][(int)$row['page_id']] = true;
            }
        }

        $result = [];
        foreach ($pagesByComponent as $component => $pageIds) {
            $result[$component] = ['count' => count($pageIds), 'page_ids' => array_keys($pageIds)];
        }
        return $result;
    }

    /** @param array<string,true> $targets @param array<string,true> $matches */
    private static function collectMatches(array $data, array $targets, array &$matches): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'component' && is_string($value) && isset($targets[$value])) {
                $matches[$value] = true;
            }
            if (is_array($value)) self::collectMatches($value, $targets, $matches);
        }
    }

    /** Recursively inspect the real component field, including grid children. */
    private static function containsComponent(array $data, string $component): bool
    {
        foreach ($data as $key => $value) {
            if ($key === 'component' && $value === $component) {
                return true;
            }
            if (is_array($value) && self::containsComponent($value, $component)) {
                return true;
            }
        }
        return false;
    }
}
