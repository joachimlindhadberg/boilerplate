<?php

declare(strict_types=1);

/** Administration-only translations. Never changes website content or public routes. */
final class Translator
{
    private static string $locale = 'en';
    private static string $default = 'en';
    /** @var array<string, array<string, array<string, string>>> */
    private static array $catalogs = [];
    /** @var array<string, string> */
    private static array $moduleCatalogs = [];

    /** Stable administration locales (not the public website language). */
    public static function locales(): array
    {
        return ['en', 'da', 'de', 'sv', 'nb'];
    }

    public static function supported(string $locale): bool
    {
        return in_array($locale, self::locales(), true);
    }

    public static function boot(string $default = 'en'): void
    {
        self::$default = self::supported($default) ? $default : 'en';
        self::$locale = self::$default;
    }

    public static function setLocale(?string $preference): void
    {
        self::$locale = $preference !== null && self::supported($preference)
            ? $preference : self::$default;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function defaultLocale(): string
    {
        return self::$default;
    }

    public static function registerModule(string $slug, string $dir): void
    {
        // Only Core-discovered, trusted installed modules supply paths. Never use
        // a user-submitted path or locale in filesystem lookups.
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $slug) && is_dir($dir)) {
            $directory = rtrim($dir, '/');
            if ((self::$moduleCatalogs[$slug] ?? null) !== $directory) {
                unset(self::$catalogs[$slug]);
            }
            self::$moduleCatalogs[$slug] = $directory;
        }
    }

    /**
     * Read a single JSON catalog per Core/module per request. No executable
     * language files and no JSON served to the public webroot.
     *
     * @return array<string, array<string, string>>
     */
    private static function catalog(?string $module = null): array
    {
        $name = $module ?? 'core';
        if (!array_key_exists($name, self::$catalogs)) {
            $path = $module === null
                ? dirname(__DIR__) . '/lang/translations.json'
                : (isset(self::$moduleCatalogs[$module]) ? self::$moduleCatalogs[$module] . '/lang/translations.json' : '');
            $result = [];
            if ($path !== '' && is_file($path)) {
                try {
                    $contents = file_get_contents($path);
                    if ($contents === false) {
                        throw new RuntimeException('Catalog could not be read');
                    }
                    $decoded = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
                    if (!is_object($decoded)) {
                        throw new RuntimeException('Catalog root must be a JSON object');
                    }
                    foreach (get_object_vars($decoded) as $translationKey => $translations) {
                        $result[$translationKey] = is_object($translations) ? get_object_vars($translations) : [];
                    }
                } catch (JsonException | RuntimeException $error) {
                    error_log('Invalid administration translation catalog for ' . $name . ': ' . $error->getMessage());
                }
            }
            self::$catalogs[$name] = $result;
        }
        return self::$catalogs[$name];
    }

    public static function get(string $key, array $parameters = []): string
    {
        $module = null;
        if (preg_match('/^([a-z][a-z0-9_-]*):(.+)$/D', $key, $m)) {
            $module = $m[1];
            $key = $m[2];
        }
        $translations = self::catalog($module)[$key] ?? null;
        $text = is_array($translations) ? ($translations[self::$locale] ?? null) : null;
        if (!is_string($text)) {
            $text = is_array($translations) && is_string($translations['en'] ?? null)
                ? $translations['en'] : $key;
        }
        foreach ($parameters as $name => $value) {
            $text = str_replace('{' . $name . '}', (string)$value, $text);
        }
        return $text;
    }
}
