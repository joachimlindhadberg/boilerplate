<?php

declare(strict_types=1);

/**
 * Declarative media usage registration. Reads module manifests, never runs
 * module PHP. Inactive modules are included so deactivation cannot permit
 * deletion of media still referenced by their stored records.
 */
final class ModuleMediaReferences
{
    /** @return array<int,array{table:string,column:string,label:string}> */
    public static function all(?string $modulesDir = null): array
    {
        $dir = $modulesDir ?? dirname(__DIR__, 2) . '/modules';
        static $cache = [];
        $cacheKey = rtrim($dir, '/');
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        $references = [];
        foreach (glob(rtrim($dir, '/') . '/*/module.json') ?: [] as $manifest) {
            if (is_link($manifest)) {
                throw new RuntimeException('Symlinked module manifest cannot be trusted for media deletion.');
            }
            $value = json_decode((string)file_get_contents($manifest), true);
            if (!is_array($value)) {
                throw new RuntimeException('Invalid module manifest: ' . $manifest);
            }
            foreach (($value['media_references'] ?? []) as $entry) {
                if (!is_array($entry)) {
                    throw new RuntimeException('Invalid module media reference declaration.');
                }
                $table = (string)($entry['table'] ?? '');
                $column = (string)($entry['column'] ?? '');
                $label = trim((string)($entry['label'] ?? ''));
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D', $table)
                    || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D', $column)
                    || $label === '' || strlen($label) > 120) {
                    throw new RuntimeException('Invalid module media reference declaration: ' . $manifest);
                }
                $referenceKey = $table . ':' . $column;
                $references[$referenceKey] = ['table' => $table, 'column' => $column, 'label' => $label];
            }
        }
        return $cache[$cacheKey] = array_values($references);
    }
}
