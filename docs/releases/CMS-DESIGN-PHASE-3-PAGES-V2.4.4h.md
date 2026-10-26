# V2.4.4h — CMS Design System Phase 3.2 / Pages

Base: user-approved V2.4.4g (internal 2.4.4.13). Internal release: 2.4.4.14.

## Scope

- Page list headings now match the status filter and/or text search instead of always saying “All pages”.
- Active status tab exposes `aria-current="page"` for assistive technology. Existing live search, status counts, list size (10), filters and pagination are retained.
- An empty search/filter result offers a clear search/filters link. An actually empty page collection offers a new-page link.
- Both upcoming publication and unpublication dates show when a page has both scheduled; previously the second could be hidden.
- Page list system-page/hierarchy/schedule labels and editor status/options/help text now translate consistently in all five admin languages, including creation form actions.
- No editor, scheduling, Page Builder, data, or persistence logic changed; no JavaScript, database, frontend, core UI styling or module modifications.

## Local acceptance checklist

1. Test all, Published, Draft, Protected filters; confirm heading and counts update with existing live search.
2. Search for a known title and a nonsense query; verify the empty result clears correctly and active status filters reset. Test no-JS GET submission.
3. Make sure pages scheduled both to publish and unpublish show both dates in the status column.
4. Create and edit a draft, change status and verify saved values remain unchanged; test system-page locked slug/parent.
5. Change admin language and inspect page status labels, metadata, and form help.
6. Mobile + desktop; verify existing Page Builder, revisions, and navigation still work.

Keep `.env`, database and uploads. No SQL import required. Package file/directory permissions 0644/0755.
