<?php
/**
 * Resolve the public path of a CMS page from its slug and parent hierarchy.
 *
 * This class owns only path construction: Pages owns database lookups and
 * Redirects owns redirect policy. The existing slug and cycle behaviour is
 * deliberately preserved for backwards compatibility.
 */
final class PagePath
{
    /**
     * Resolve a path using a preloaded page lookup map, indexed by integer ID.
     *
     * @param array<string, mixed> $page
     * @param array<int, array<string, mixed>> $byId
     */
    public static function fromMap(array $page, array $byId): string
    {
        return self::fromLookup(
            $page,
            static fn(int $id): ?array => $byId[$id] ?? null
        );
    }

    /**
     * Resolve a path when parents are retrieved on demand.
     *
     * @param array<string, mixed> $page
     * @param callable(int): ?array $findParent
     */
    public static function fromLookup(array $page, callable $findParent): string
    {
        $slug = trim((string)($page['page_slug'] ?? ''));
        if ($slug === '' || $slug === 'forside') {
            return '/';
        }

        $segments = [$slug];
        $parentId = (int)($page['parent_id'] ?? 0);
        $visited = [];
        while ($parentId > 0 && !isset($visited[$parentId])) {
            $visited[$parentId] = true;
            $parent = $findParent($parentId);
            if (!$parent) {
                break;
            }
            $parentSlug = trim((string)($parent['page_slug'] ?? ''));
            if ($parentSlug !== '' && $parentSlug !== 'forside') {
                array_unshift($segments, $parentSlug);
            }
            $parentId = (int)($parent['parent_id'] ?? 0);
        }

        return '/' . implode('/', $segments);
    }
}
