<?php

declare(strict_types=1);

/** Built-in, module-owned templates rendered as ordinary Forms Builder rows. */
final class FormsTemplateCatalog
{
    /** @param list<string> $supportedTypes
     *  @return list<array<string,mixed>>
     */
    public static function all(array $supportedTypes): array
    {
        $path = __DIR__ . '/templates/standard.json';
        $raw = file_get_contents($path);
        if ($raw === false) throw new RuntimeException('Forms template catalog is missing.');
        $records = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($records) || !array_is_list($records) || count($records) > 20) {
            throw new UnexpectedValueException('Invalid Forms template catalog.');
        }
        $result = [];
        $identifiers = [];
        foreach ($records as $record) {
            if (!is_array($record)) throw new UnexpectedValueException('Invalid Forms template.');
            $id = $record['id'] ?? null;
            $title = $record['title'] ?? null;
            $description = $record['description'] ?? null;
            $rows = $record['rows'] ?? null;
            if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_-]{0,39}$/D', $id)
                || isset($identifiers[$id]) || !self::validText($title, 100)
                || !self::validText($description, 300) || !is_array($rows)
                || !array_is_list($rows) || count($rows) < 1 || count($rows) > 100) {
                throw new UnexpectedValueException('Invalid Forms template metadata.');
            }
            $identifiers[$id] = true;
            $localizedRows = [];
            $count = 0;
            foreach ($rows as $row) {
                $layout = is_array($row) ? ($row['layout'] ?? null) : null;
                $items = is_array($row) ? ($row['fields'] ?? null) : null;
                if (!in_array($layout, ['100', '50-50'], true) || !is_array($items)
                    || !array_is_list($items) || count($items) < 1
                    || count($items) > ($layout === '50-50' ? 2 : 1)) {
                    throw new UnexpectedValueException('Invalid Forms template row.');
                }
                $localizedFields = [];
                foreach ($items as $field) {
                    $type = is_array($field) ? ($field['field_type'] ?? null) : null;
                    $label = is_array($field) ? ($field['label'] ?? null) : null;
                    $options = is_array($field) ? ($field['options'] ?? []) : [];
                    if (!is_string($type) || !in_array($type, $supportedTypes, true)
                        || !self::validText($label, 191)
                        || !is_bool($field['is_required'] ?? null)
                        || !is_array($options) || !array_is_list($options)
                        || count($options) > 50 || (in_array($type, ['select','radio'], true) && !$options)
                        || (!in_array($type, ['select','radio'], true) && $options)) {
                        throw new UnexpectedValueException('Invalid Forms template field.');
                    }
                    $localizedOptions = [];
                    foreach ($options as $option) {
                        if (!self::validText($option, 191)) throw new UnexpectedValueException('Invalid Forms template option.');
                        $localizedOptions[] = t('forms:' . $option);
                    }
                    $localizedFields[] = [
                        'field_type' => $type,
                        'label' => t('forms:' . $label),
                        'is_required' => $field['is_required'],
                        'placeholder' => '',
                        'help_text' => '',
                        'options' => $localizedOptions,
                    ];
                    $count++;
                }
                $localizedRows[] = ['layout' => $layout, 'fields' => $localizedFields];
            }
            if ($count > 50) throw new UnexpectedValueException('Too many fields in Forms template.');
            $result[] = [
                'id' => $id,
                'title' => t('forms:' . $title),
                'description' => t('forms:' . $description),
                'rows' => $localizedRows,
            ];
        }
        return $result;
    }

    private static function validText(mixed $value, int $limit): bool
    {
        return is_string($value) && trim($value) !== '' && strlen($value) <= $limit * 4;
    }
}
