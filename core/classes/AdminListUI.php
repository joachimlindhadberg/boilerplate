<?php

declare(strict_types=1);

/**
 * Shared presentation primitives for admin list views.
 *
 * Filtering, authorization, pagination, and row data stay in the calling
 * controller. Core owns the HTML contract; this class never queries the DB.
 */
final class AdminListUI
{
    /**
     * @param list<array{value:string,label:string,count:int,url:string}> $tabs
     */
    public static function filterTabs(array $tabs, string $active, string $label): string
    {
        if (trim($label) === '') {
            throw new InvalidArgumentException('List filters require an accessible name.');
        }
        $html = '<nav class="admin-filter-tabs" aria-label="' . AdminEscaper::attribute($label) . '">';
        $seen = [];
        foreach ($tabs as $tab) {
            $value = $tab['value'];
            if (isset($seen[$value]) || trim($tab['label']) === '') {
                throw new InvalidArgumentException('Duplicate or unnamed list filter.');
            }
            $seen[$value] = true;
            $url = self::adminUrl($tab['url']);
            $selected = $value === $active;
            $html .= '<a href="' . AdminEscaper::attribute($url) . '"'
                . ($selected ? ' class="is-active" aria-current="page"' : '') . '>'
                . '<span>' . AdminEscaper::text($tab['label']) . '</span>'
                . '<strong>' . max(0, $tab['count']) . '</strong></a>';
        }
        return $html . '</nav>';
    }

    /**
     * Backward-compatible, navigational Core SVG action links.
     * Only safe local GET destinations are accepted.
     *
     * @param list<array{icon:string,label:string,url:string}> $actions
     */
    public static function actionLinks(array $actions, string $label): string
    {
        foreach ($actions as $action) {
            if (isset($action['type']) || !isset($action['url']) || isset($action['attributes'])) {
                throw new InvalidArgumentException('actionLinks accepts navigational links only.');
            }
        }
        return self::actionGroup($actions, $label);
    }

    /**
     * Shared Core action group for navigational links and explicitly non-submitting
     * buttons. A destructive button may OPEN a confirmation modal; it must never
     * execute deletion by GET. The actual server operation remains POST + CSRF.
     *
     * @param list<array<string,mixed>> $actions
     */
    public static function actionGroup(array $actions, string $label): string
    {
        if (trim($label) === '') {
            throw new InvalidArgumentException('List action groups require an accessible name.');
        }
        $html = '<div class="admin-table-actions admin-table-actions--icons" role="group" aria-label="'
            . AdminEscaper::attribute($label) . '">';
        foreach ($actions as $action) {
            $icon = $action['icon'] ?? null;
            $text = $action['label'] ?? null;
            $tone = $action['tone'] ?? 'neutral';
            if (!is_string($icon) || !is_string($text) || !is_string($tone)) {
                throw new InvalidArgumentException('Invalid list action specification.');
            }
            $type = $action['type'] ?? 'link';
            if ($type === 'link') {
                if (!isset($action['url']) || !is_string($action['url']) || isset($action['attributes'])) {
                    throw new InvalidArgumentException('List links require a local admin URL.');
                }
                $html .= AdminIcons::actionLink($icon, $text, self::adminUrl($action['url']), [], $tone);
            } elseif ($type === 'button') {
                if (isset($action['url']) || !is_array($action['attributes'] ?? null)) {
                    throw new InvalidArgumentException('List buttons require attributes, not a URL.');
                }
                $attributes = $action['attributes'];
                if (isset($attributes['type']) || isset($attributes['form'])) {
                    throw new InvalidArgumentException('List action buttons cannot submit forms.');
                }
                $html .= AdminIcons::actionButton($icon, $text, $attributes, $tone);
            } else {
                throw new InvalidArgumentException('Unknown list action type.');
            }
        }
        return $html . '</div>';
    }

    private static function adminUrl(string $url): string
    {
        // List filters/actions must never navigate away from the admin area.
        // Ampersands are passed as raw URL parameters and escaped only at render.
        if (!preg_match('~\A/admin(?:[/?#]|\z)~D', $url)
            || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7F]/', $url)
            || AdminEscaper::safeUrl($url) === null) {
            throw new InvalidArgumentException('List links require a safe local admin URL.');
        }
        return $url;
    }
}
