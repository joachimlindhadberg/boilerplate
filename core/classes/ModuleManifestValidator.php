<?php

declare(strict_types=1);

/**
 * Read-only validation of static module metadata. Never executes module PHP.
 * Used equally by the runtime registry and the offline developer CLI.
 */
final class ModuleManifestValidator
{
    private static function isList(array $items): bool
    {
        $index = 0;
        foreach ($items as $key => $_) {
            if ($key !== $index++) return false;
        }
        return true;
    }

    public static function load(string $directory): array
    {
        if (is_link($directory) || !is_dir($directory)) {
            throw new RuntimeException('Module directory is missing or is a symbolic link.');
        }
        $file = $directory . '/module.json';
        if (!is_file($file) || is_link($file)) {
            throw new RuntimeException('module.json is missing or is a symbolic link.');
        }
        $raw = file_get_contents($file);
        if ($raw === false || strlen($raw) > 65536) {
            throw new RuntimeException('module.json cannot be read or exceeds 64 KiB.');
        }
        $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || self::isList($manifest)) {
            throw new RuntimeException('module.json must contain a JSON object.');
        }
        return self::validate(basename($directory), $directory, $manifest);
    }

    public static function validate(string $folder, string $directory, array $manifest): array
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/D', $folder) || is_link($directory)) {
            throw new RuntimeException('Module folder must use a lowercase safe slug and cannot be a symbolic link.');
        }
        foreach (['slug', 'name', 'version', 'entry', 'class'] as $field) {
            if (!isset($manifest[$field]) || !is_string($manifest[$field]) || trim($manifest[$field]) === '') {
                throw new RuntimeException('Manifest is missing a non-empty string field: ' . $field);
            }
        }
        foreach (['description', 'author', 'requires', 'requires_php'] as $field) {
            if (isset($manifest[$field]) && !is_string($manifest[$field])) {
                throw new RuntimeException('Manifest field must be a string: ' . $field);
            }
        }
        $slug = trim($manifest['slug']);
        $name = trim($manifest['name']);
        $version = trim($manifest['version']);
        $entry = trim($manifest['entry']);
        $class = trim($manifest['class']);
        $requires = trim((string)($manifest['requires'] ?? ''));
        $requiresPhp = trim((string)($manifest['requires_php'] ?? ''));

        if ($slug !== $folder) {
            throw new RuntimeException('Manifest slug must match the module folder exactly.');
        }
        if (strlen($name) > 191) {
            throw new RuntimeException('Module name exceeds 191 bytes.');
        }
        foreach (['version' => $version, 'requires' => $requires, 'requires_php' => $requiresPhp] as $key => $value) {
            if ($value !== '' && !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/D', $value)) {
                throw new RuntimeException('Invalid semantic version in manifest: ' . $key);
            }
        }
        if (basename($entry) !== $entry || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*\.php$/D', $entry)) {
            throw new RuntimeException('Entry must be a PHP filename in the module root.');
        }
        if (!preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/D', $class)) {
            throw new RuntimeException('Invalid module class name.');
        }
        $entryPath = $directory . '/' . $entry;
        $realRoot = realpath($directory);
        $realEntry = realpath($entryPath);
        if ($realRoot === false || $realEntry === false || !is_file($realEntry)
            || is_link($entryPath) || dirname($realEntry) !== $realRoot) {
            throw new RuntimeException('Entry file is missing or escapes the module root.');
        }
        $references = $manifest['media_references'] ?? [];
        if (!is_array($references) || !self::isList($references)) {
            throw new RuntimeException('media_references must be a JSON array.');
        }
        foreach ($references as $reference) {
            if (!is_array($reference)) {
                throw new RuntimeException('Invalid media reference definition.');
            }
            foreach (['table', 'column', 'label'] as $field) {
                if (!is_string($reference[$field] ?? null) || trim($reference[$field]) === '') {
                    throw new RuntimeException('Invalid media reference field: ' . $field);
                }
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $reference['table'])
                || !preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $reference['column'])) {
                throw new RuntimeException('Invalid media reference SQL identifier.');
            }
        }
        // Capabilities are explicit, module-owned and role-bound. A plugin may
        // never redefine a Core capability or grant permissions to another module.
        $capabilities = $manifest['capabilities'] ?? [];
        if (!is_array($capabilities) || ($capabilities !== [] && self::isList($capabilities))) {
            throw new RuntimeException('capabilities must be a JSON object mapping names to role arrays.');
        }
        $normalizedCapabilities = [];
        foreach ($capabilities as $capability => $roles) {
            if (!is_string($capability)
                || !preg_match('/^' . preg_quote($slug, '/') . '\.[a-z][a-z0-9_.-]{0,99}$/D', $capability)
                || !is_array($roles) || !self::isList($roles) || !$roles) {
                throw new RuntimeException('Invalid module capability definition.');
            }
            $uniqueRoles = [];
            foreach ($roles as $role) {
                if (!is_string($role) || !in_array($role, ['admin', 'editor'], true)
                    || in_array($role, $uniqueRoles, true)) {
                    throw new RuntimeException('Invalid or duplicate capability role.');
                }
                $uniqueRoles[] = $role;
            }
            $normalizedCapabilities[$capability] = $uniqueRoles;
        }
        return [
            'slug' => $slug,
            'name' => $name,
            'version' => $version,
            'description' => trim((string)($manifest['description'] ?? '')),
            'author' => trim((string)($manifest['author'] ?? '')),
            'requires' => $requires,
            'requires_php' => $requiresPhp,
            'entry' => $entry,
            'class' => $class,
            'capabilities' => $normalizedCapabilities,
            'base_path' => $directory,
            'entry_path' => $entryPath,
            'valid' => true,
            'error' => null,
        ];
    }
}
