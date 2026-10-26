<?php
/**
 * Core admin switch control. A real native checkbox exposes its state as a switch
 * to keyboard and assistive technologies; module code owns the saved value.
 */
final class AdminToggle
{
    /**
     * @param array<string, scalar|null> $attributes Safe input attributes, e.g. name, data-*, aria-*.
     */
    public static function render(string $id, string $label, bool $checked = false, string $description = '', array $attributes = [], array $stateLabels = []): string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $id) || trim($label) === '') {
            throw new InvalidArgumentException('A toggle needs a valid id and a non-empty label.');
        }
        $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $extra = '';
        foreach ($attributes as $key => $value) {
            if (!is_string($key) || !preg_match('/^(?:name|value|disabled|required|data-[a-z][a-z0-9_-]*|aria-[a-z][a-z0-9_-]*)$/D', $key)) {
                throw new InvalidArgumentException('Unsupported toggle attribute.');
            }
            if ($key === 'aria-checked' || $key === 'aria-label' || $key === 'aria-labelledby' || $key === 'aria-describedby') {
                // Accessible name is provided by the wrapping label and help text.
                throw new InvalidArgumentException('Toggle accessible labelling cannot be overridden.');
            }
            if (is_array($value) || is_object($value)) {
                throw new InvalidArgumentException('Toggle attributes must be scalar.');
            }
            if ($key === 'disabled' || $key === 'required') {
                if ($value) $extra .= ' ' . $key;
                continue;
            }
            if ($value !== null) $extra .= ' ' . $key . '="' . $escape((string)$value) . '"';
        }
        // Optional live state text lives immediately before the switch, not below its card.
        // State labels are translated by the caller; both states are safely escaped.
        if ($stateLabels !== [] && (array_keys($stateLabels) !== ['on', 'off']
            || !is_string($stateLabels['on']) || !is_string($stateLabels['off'])
            || trim($stateLabels['on']) === '' || trim($stateLabels['off']) === '')) {
            throw new InvalidArgumentException('Toggle state labels must be non-empty on/off strings.');
        }
        $state = $stateLabels === [] ? '' : '<span class="admin-toggle__state" data-admin-toggle-state'
            . ' data-state-on="' . $escape($stateLabels['on']) . '"'
            . ' data-state-off="' . $escape($stateLabels['off']) . '" aria-live="polite">'
            . $escape($checked ? $stateLabels['on'] : $stateLabels['off']) . '</span>';
        $help = $description !== '' ? '<span class="admin-toggle__description">' . $escape($description) . '</span>' : '';
        return '<label class="admin-toggle" for="' . $escape($id) . '">'
            . '<span class="admin-toggle__copy"><span class="admin-toggle__label">' . $escape($label) . '</span>' . $help . '</span>'
            . '<span class="admin-toggle__right">' . $state . '<span class="admin-toggle__control">'
            . '<input class="admin-toggle__input" id="' . $escape($id) . '" type="checkbox" role="switch"'
            . ($checked ? ' checked' : '') . $extra . '>'
            . '<span class="admin-toggle__track" aria-hidden="true"><span class="admin-toggle__thumb"></span></span>'
            . '</span></span></label>';
    }
}
