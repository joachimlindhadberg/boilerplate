<?php

declare(strict_types=1);

/**
 * Core-owned presentation of a permanent-delete confirmation dialog.
 *
 * Calling controllers retain permissions, endpoint routing, CSRF verification,
 * confirmation requirements and database integrity checks. This renderer emits
 * a native dialog with a POST form; it deliberately has no delete behavior.
 */
final class AdminDeleteDialog
{
    /**
     * @param array<string, mixed> $config
     * Expected keys: id, title, warning, action, csrfToken, prefix, subject,
     * suffix, confirmLabel. Optional: titleId, dialogAttributes, formAttributes,
     * subjectAttributes, fields, closeLabel, cancelLabel.
     */
    public static function render(array $config): string
    {
        foreach (['id', 'title', 'warning', 'action', 'csrfToken', 'prefix', 'subject', 'suffix', 'confirmLabel'] as $field) {
            if (!array_key_exists($field, $config) || !is_string($config[$field])) {
                throw new InvalidArgumentException('Delete dialog requires text field: ' . $field);
            }
        }
        $id = $config['id'];
        $titleId = $config['titleId'] ?? $id . '-title';
        if (!is_string($titleId) || !self::validName($id) || !self::validName($titleId)) {
            throw new InvalidArgumentException('Delete dialog IDs must be valid.');
        }
        foreach (['title', 'confirmLabel', 'csrfToken'] as $required) {
            if (trim($config[$required]) === '') {
                throw new InvalidArgumentException('Delete dialog field cannot be empty: ' . $required);
            }
        }
        $url = $config['action'];
        if (!preg_match('~\A/admin(?:[/?#]|\z)~D', $url)
            || str_contains($url, '\\')
            || preg_match('/[\x00-\x20\x7F]/', $url)
            || AdminEscaper::safeUrl($url) === null) {
            throw new InvalidArgumentException('Delete dialog action must be a safe local admin URL.');
        }
        $dialogAttrs = self::dataAttributes($config['dialogAttributes'] ?? []);
        $formAttrs = self::dataAttributes($config['formAttributes'] ?? []);
        $subjectAttrs = self::dataAttributes($config['subjectAttributes'] ?? []);
        $closeLabel = $config['closeLabel'] ?? 'Luk';
        $cancelLabel = $config['cancelLabel'] ?? 'Annuller';
        if (!is_string($closeLabel) || trim($closeLabel) === '' || !is_string($cancelLabel) || trim($cancelLabel) === '') {
            throw new InvalidArgumentException('Dialog buttons require accessible labels.');
        }
        $fields = $config['fields'] ?? [];
        if (!is_array($fields)) {
            throw new InvalidArgumentException('Delete dialog fields must be an array.');
        }
        $hiddenHtml = '<input type="hidden" name="csrf_token" value="' . self::e($config['csrfToken']) . '">';
        foreach ($fields as $name => $entry) {
            if (!is_string($name) || !preg_match('/\A[a-z][a-z0-9_]*\z/D', $name) || $name === 'csrf_token') {
                throw new InvalidArgumentException('Invalid or reserved hidden field name.');
            }
            $attrs = [];
            if (is_array($entry)) {
                if (!array_key_exists('value', $entry) || !is_scalar($entry['value'])) {
                    throw new InvalidArgumentException('Hidden field requires a scalar value.');
                }
                $value = (string)$entry['value'];
                $attrs = $entry['attributes'] ?? [];
            } elseif (is_scalar($entry)) {
                $value = (string)$entry;
            } else {
                throw new InvalidArgumentException('Invalid hidden field value.');
            }
            $hiddenHtml .= '<input type="hidden" name="' . self::e($name) . '" value="' . self::e($value) . '"'
                . self::dataAttributes($attrs) . '>';
        }
        // Dynamic targets retain their inert <strong> even when initially empty.
        // A text-only confirmation (with no dynamic subject) needs no empty element.
        $hasSubject = $config['subject'] !== '' || $subjectAttrs !== '';
        $messageHtml = self::e($config['prefix']);
        if ($hasSubject) {
            $messageHtml .= ' <strong' . $subjectAttrs . '>' . self::e($config['subject']) . '</strong>';
        }
        if ($config['suffix'] !== '') {
            $messageHtml .= ' ' . self::e($config['suffix']);
        }
        return '<dialog class="admin-modal admin-modal--sm" data-admin-modal id="' . self::e($id) . '" aria-labelledby="' . self::e($titleId) . '"' . $dialogAttrs . '>'
            . '<form class="admin-modal__surface" method="post" action="' . self::e($url) . '"' . $formAttrs . '>'
            . $hiddenHtml
            . '<div class="admin-modal__header"><div><h2 id="' . self::e($titleId) . '">' . self::e($config['title']) . '</h2><p>' . self::e($config['warning']) . '</p></div>'
            . '<button class="admin-modal__close" type="button" data-modal-close aria-label="' . self::e($closeLabel) . '">×</button></div>'
            . '<div class="admin-modal__body"><p>' . $messageHtml . '</p></div>'
            . '<div class="admin-modal__footer"><button class="admin-button admin-button--secondary" type="button" data-modal-close>' . self::e($cancelLabel) . '</button>'
            . '<button class="admin-button admin-button--danger" type="submit">' . self::e($config['confirmLabel']) . '</button></div>'
            . '</form></dialog>';
    }

    /** @param mixed $attributes */
    private static function dataAttributes(mixed $attributes): string
    {
        if (!is_array($attributes)) {
            throw new InvalidArgumentException('Dialog data attributes must be an array.');
        }
        $html = '';
        foreach ($attributes as $key => $value) {
            if (!is_string($key) || !preg_match('/\Adata-[a-z][a-z0-9-]*\z/D', $key)) {
                throw new InvalidArgumentException('Delete dialog only accepts data-* attributes.');
            }
            if ($value === false || $value === null) continue;
            if ($value === true) {
                $html .= ' ' . $key;
            } elseif (is_scalar($value)) {
                $html .= ' ' . $key . '="' . self::e((string)$value) . '"';
            } else {
                throw new InvalidArgumentException('Dialog data attributes must be scalar.');
            }
        }
        return $html;
    }

    private static function validName(string $name): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9_-]*\z/D', $name) === 1;
    }

    private static function e(string $value): string
    {
        return AdminEscaper::attribute($value);
    }
}
