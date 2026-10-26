<?php

declare(strict_types=1);

/**
 * Core-owned SVG icons and accessible icon-only admin actions.
 * Icons are static, hand-authored shapes; module code must never supply SVG markup.
 * Existing admin_icon() callers continue to work through this single registry.
 */
final class AdminIcons
{
    private const SHAPES = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/>',
        'file' => '<path d="M6 2.5h8l4 4V21H6z"/><path d="M14 2.5v4h4"/>',
        'folder' => '<path d="M3 6.5h7l2 2h9v11H3z"/><path d="M3 6.5V5h7l2 2"/>',
        'warning' => '<path d="M12 3 2 21h20L12 3z"/><path d="M12 9v5M12 18h.01"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="0"/><path d="M7 2.5V7M17 2.5V7M3 9h18"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'cube' => '<path d="m12 2.5 8 4.5v10L12 21.5 4 17V7z"/><path d="m4 7 8 4.5L20 7M12 11.5v10"/>',
        'image' => '<rect x="3" y="3" width="18" height="18"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m4 18 5-5 3.5 3.5 2.5-2.5 5 5"/>',
        'wrench' => '<path d="M14.5 6.5a5 5 0 0 0-6.6 6.6L3 18l3 3 4.9-4.9a5 5 0 0 0 6.6-6.6l-3 3-3-3z"/>',
        'rack' => '<rect x="4" y="4" width="16" height="6"/><circle cx="7" cy="7" r="0.9"/><path d="M14 7h3"/><rect x="4" y="14" width="16" height="6"/><circle cx="7" cy="17" r="0.9"/><path d="M14 17h3"/>',
        'users' => '<path d="M8.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM15.5 10a3 3 0 1 0 0-6"/><path d="M2.5 20c.3-4 2.3-6 6-6s5.7 2 6 6M15 14c3.8.1 5.8 2.1 6 6"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14"/><path d="m4 6 8 7 8-7"/>',
        'inbox' => '<path d="M4 4h16l2 12v4H2v-4z"/><path d="M2 15h6l2 3h4l2-3h6"/>',
        'user' => '<circle cx="12" cy="7" r="4"/><path d="M4.5 21c.4-5 2.9-7.5 7.5-7.5s7.1 2.5 7.5 7.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v7H4V6h7"/>',
        'chevron' => '<path d="m9 7 5 5-5 5"/>',
        'grip' => '<circle cx="9" cy="5" r="1.45" fill="currentColor" stroke="none"/><circle cx="15" cy="5" r="1.45" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1.45" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1.45" fill="currentColor" stroke="none"/><circle cx="9" cy="19" r="1.45" fill="currentColor" stroke="none"/><circle cx="15" cy="19" r="1.45" fill="currentColor" stroke="none"/>',
        'dots' => '<circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1" fill="currentColor" stroke="none"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'upload' => '<path d="M12 16V4"/><path d="m7.5 8.5 4.5-4.5 4.5 4.5"/><path d="M5 13.5v6h14v-6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'arrow-up' => '<path d="M12 20V4M5 11l7-7 7 7"/>',
        'arrow-down' => '<path d="M12 4v16M5 13l7 7 7-7"/>',
        'arrow-left' => '<path d="M20 12H4M11 5l-7 7 7 7"/>',
        'close' => '<path d="M5 5l14 14M19 5 5 19"/>',
        'play' => '<path d="m8 5 11 7-11 7z"/>',
        'pause' => '<path d="M8 5v14M16 5v14"/>',
        'arrow-right' => '<path d="M4 12h16M13 5l7 7-7 7"/>',
        'check' => '<path d="m5 12 4.5 4.5L19 7"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'eye' => '<path d="M2.5 12s3.6-6 9.5-6 9.5 6 9.5 6-3.6 6-9.5 6S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.75"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.2 4.8a2.1 2.1 0 0 1 3 3L8 19l-4 1 1-4z"/>',
        'delete' => '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 10v7M14 10v7"/>',
    ];

    public static function render(string $name, string $class = ''): string
    {
        // Preserve historical admin_icon() fallback for unknown sidebar/module icon names.
        $body = self::SHAPES[$name] ?? self::SHAPES['file'];
        $classAttr = $class !== '' ? ' class="' . AdminEscaper::attribute($class) . '"' : '';
        return '<svg' . $classAttr . ' viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
    }

    /**
     * Non-submitting by default. Modules may explicitly set type=submit in a
     * POST form with their own existing CSRF/capability checks.
     * @param array<string, scalar|null> $attributes
     */
    public static function actionButton(string $name, string $label, array $attributes = [], string $tone = 'neutral'): string
    {
        self::requireLabelAndIcon($name, $label);
        if (isset($attributes['type']) && !in_array($attributes['type'], ['button', 'submit', 'reset'], true)) {
            throw new InvalidArgumentException('Invalid action button type.');
        }
        $type = $attributes['type'] ?? 'button';
        unset($attributes['type']);
        $attrs = self::actionAttributes($attributes, ['class', 'id', 'title', 'form', 'name', 'value', 'disabled', 'tabindex']);
        return '<button type="' . $type . '" class="' . self::actionClass($tone) . self::extraClass($attributes) . '"'
            . ' aria-label="' . AdminEscaper::attribute($label) . '"'
            . ' title="' . AdminEscaper::attribute($attributes['title'] ?? $label) . '"'
            . $attrs . '>' . self::render($name, 'admin-icon-action__icon') . '</button>';
    }

    /**
     * Links only navigate; destructive actions should be POST buttons instead.
     * @param array<string, scalar|null> $attributes
     */
    public static function actionLink(string $name, string $label, string $href, array $attributes = [], string $tone = 'neutral'): string
    {
        self::requireLabelAndIcon($name, $label);
        $safe = AdminEscaper::safeUrl($href);
        if ($safe === null) {
            throw new InvalidArgumentException('Invalid admin action link URL.');
        }
        $attrs = self::actionAttributes($attributes, ['class', 'id', 'title', 'tabindex']);
        return '<a href="' . AdminEscaper::attribute($safe) . '" class="' . self::actionClass($tone) . self::extraClass($attributes) . '"'
            . ' aria-label="' . AdminEscaper::attribute($label) . '"'
            . ' title="' . AdminEscaper::attribute($attributes['title'] ?? $label) . '"'
            . $attrs . '>' . self::render($name, 'admin-icon-action__icon') . '</a>';
    }

    private static function requireLabelAndIcon(string $name, string $label): void
    {
        if (!isset(self::SHAPES[$name]) || trim($label) === '') {
            throw new InvalidArgumentException('Icon actions require a supported icon and a non-empty accessible label.');
        }
    }

    private static function actionClass(string $tone): string
    {
        if (!in_array($tone, ['neutral', 'danger'], true)) {
            throw new InvalidArgumentException('Invalid icon action tone.');
        }
        return 'admin-icon-action' . ($tone === 'danger' ? ' admin-icon-action--danger' : '');
    }

    /**
     * Only inert HTML attributes, ARIA metadata and module data hooks are allowed.
     * Never accept event handlers, inline CSS, an arbitrary href or an overriding label.
     * @param array<string, scalar|null> $attrs
     * @param list<string> $allowed
     */
    private static function actionAttributes(array $attrs, array $allowed): string
    {
        $out = '';
        foreach ($attrs as $key => $value) {
            if ($key === 'class' || $key === 'title') {
                if ($value !== null && !is_scalar($value)) {
                    throw new InvalidArgumentException('Invalid icon action attribute value.');
                }
                continue;
            }
            if ($key === 'aria-label' || $key === 'aria-labelledby') {
                throw new InvalidArgumentException('Icon action labels cannot be overridden.');
            }
            if (!is_string($key) || (!in_array($key, $allowed, true)
                && !preg_match('/\A(?:data|aria)-[a-z][a-z0-9-]*\z/D', $key))) {
                throw new InvalidArgumentException('Unsupported icon action attribute.');
            }
            if ($key === 'disabled') {
                if ($value) {
                    $out .= ' disabled';
                }
                continue;
            }
            if ($value === null) {
                continue;
            }
            if (!is_scalar($value)) {
                throw new InvalidArgumentException('Invalid icon action attribute value.');
            }
            $out .= ' ' . $key . '="' . AdminEscaper::attribute($value) . '"';
        }
        return $out;
    }

    /** @param array<string, scalar|null> $attributes */
    private static function extraClass(array $attributes): string
    {
        if (!isset($attributes['class']) || $attributes['class'] === '') {
            return '';
        }
        if (!is_string($attributes['class']) || !preg_match('/\A[a-zA-Z0-9_-]+(?: [a-zA-Z0-9_-]+)*\z/D', $attributes['class'])) {
            throw new InvalidArgumentException('Invalid icon action class.');
        }
        return ' ' . AdminEscaper::attribute($attributes['class']);
    }
}
