<?php

declare(strict_types=1);

/** Read-only validation of admin translation JSON. No database or executable catalogs. */
final class TranslationCatalogValidator
{
    /**
     * @return array{keys:int,errors:list<string>}
     */
    public static function inspect(string $file): array
    {
        if (!is_file($file)) {
            return ['keys' => 0, 'errors' => ['Missing catalog: ' . $file]];
        }
        $json = file_get_contents($file);
        if (!is_string($json)) {
            return ['keys' => 0, 'errors' => ['Unreadable catalog: ' . $file]];
        }
        try {
            $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            if (!is_object($object)) {
                return ['keys' => 0, 'errors' => ['Catalog root must be a JSON object']];
            }
        } catch (JsonException $error) {
            return ['keys' => 0, 'errors' => ['Invalid JSON: ' . $error->getMessage()]];
        }

        $errors = [];
        $locales = Translator::locales();
        foreach (get_object_vars($object) as $key => $translations) {
            if (!is_string($key) || trim($key) === '') {
                $errors[] = 'Empty/invalid translation key';
                continue;
            }
            if (!is_object($translations)) {
                $errors[] = $key . ': expected an object with locale translations';
                continue;
            }
            $values = get_object_vars($translations);
            $unknown = array_diff(array_keys($values), $locales);
            if ($unknown) {
                $errors[] = $key . ': unsupported locale(s) ' . implode(', ', $unknown);
            }
            $basePlaceholders = null;
            foreach ($locales as $locale) {
                $value = $values[$locale] ?? null;
                if (!is_string($value)) {
                    $errors[] = $key . ': missing or non-string ' . $locale . ' translation';
                    continue;
                }
                preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $value, $matches);
                $placeholders = array_values(array_unique($matches[0]));
                sort($placeholders, SORT_STRING);
                if ($locale === 'en') {
                    $basePlaceholders = $placeholders;
                } elseif ($basePlaceholders !== null && $placeholders !== $basePlaceholders) {
                    $errors[] = $key . ': {' . $locale . '} placeholders differ from English';
                }
            }
        }
        return ['keys' => count(get_object_vars($object)), 'errors' => $errors];
    }
}
