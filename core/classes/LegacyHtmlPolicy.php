<?php

declare(strict_types=1);

/**
 * Trust boundary for executable legacy page HTML.
 * Never filter or mutate existing HTML silently. Editors use the escaped
 * Page Builder; only administrators may author or restore raw HTML.
 */
final class LegacyHtmlPolicy
{
    public static function canUseRawHtml(string $role): bool
    {
        return CapabilityRegistry::allows($role, 'content.html.unfiltered');
    }

    public static function canEditPage(array $page, string $role): bool
    {
        if (self::canUseRawHtml($role)) {
            return true;
        }
        return CapabilityRegistry::allows($role, 'content.manage')
            && ($page['content_mode'] ?? 'legacy') === 'builder';
    }

    /** @return array<string,mixed> */
    public static function prepareSave(array $submitted, ?array $existing, string $role): array
    {
        if (!CapabilityRegistry::allows($role, 'content.manage')) {
            throw new InvalidArgumentException('Ingen tilladelse til at redigere sider.');
        }
        if (self::canUseRawHtml($role)) {
            return $submitted;
        }
        // Includes a forged content_mode or an attempt to convert existing
        // legacy content: an editor must never create an executable HTML path.
        if (($submitted['content_mode'] ?? null) !== 'builder'
            || ($existing !== null && ($existing['content_mode'] ?? 'legacy') !== 'builder')) {
            throw new InvalidArgumentException('Legacy HTML kan kun redigeres af administratorer.');
        }
        // Even when builder mode is selected, disregard forged hidden HTML.
        // Existing legacy text is retained byte-identically for safe rollback.
        $submitted['page_content'] = $existing === null ? '' : (string)($existing['page_content'] ?? '');
        return $submitted;
    }

    public static function canRestore(array $current, array $target, string $role): bool
    {
        if (self::canUseRawHtml($role)) {
            return true;
        }
        return CapabilityRegistry::allows($role, 'content.manage')
            && ($current['content_mode'] ?? 'legacy') === 'builder'
            && ($target['content_mode'] ?? 'legacy') === 'builder'
            // A revision must not be able to change hidden raw HTML either.
            && (string)($current['page_content'] ?? '') === (string)($target['page_content'] ?? '');
    }
}
