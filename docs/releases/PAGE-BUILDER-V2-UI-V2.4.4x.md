# Boilerplate CMS V2.4.4x — Page Builder V2 UI

**Approved base:** V2.4.4w (`BOILERPLATE_VERSION=2.4.4.32`).  
**This release:** V2.4.4x (`BOILERPLATE_VERSION=2.4.4.33`) — **test candidate** until local approval.

## Included

- Page Builder header and description from the approved brief; a **Skabeloner** button opens a harmless informational modal (templates themselves are a later release).
- Compact Core-admin Row cards for existing single sections (100 %) and two-column equal grids (50/50). Existing asymmetric or 3–6 column grids remain editable in their current format, without conversion.
- A clickable bottom-of-builder add box opens a layout modal: choose **100 %** then choose an existing top-level section type; choose **50/50** to create a two-column Row immediately, including two empty columns.
- A separate block selection modal for empty grid columns. Newly created V2 grids have one content block per column; original legacy grid contents are not truncated or automatically converted.
- Existing content editor fields and media picker remain available behind compact **Rediger** actions. V2 Row settings use an **Indstillinger** toggle. Drag-and-drop and keyboard-accessible move up/down buttons remain.
- `page_blocks` still stores regular top-level blocks. The two-column Row is an existing `grid` block with `column_count=2`, `preset=equal` and a lightweight `layout_version=v2` marker. Section IDs remain persisted through `section_id` from V2.4.4w.
- New localized UI text in the Core JSON catalogue: Danish, English, German, Swedish, and Norwegian Bokmål.
- Core SCSS and compiled CSS both updated; frontend theme files and renderer not changed.

## Deliberate boundaries

- **No template insertion yet** — Skabeloner modal explains it will arrive in a separate release.
- **No new frontend HTML structure yet** — the approved `block-section > container > site-grid > site-grid--item` contract is deferred to the frontend-specific release. Public output remains backward-compatible.
- **No new top-level section types** — the choices reflect `PageBlocks::definitions()` as it exists (Hero, Intro, Tekstsektion, Tekst + billede, CTA, Dynamisk komponent). Grid child choices reflect the existing `gridChildDefinitions()`.
- A new 100 % Row is created only after choosing its content type; cancelling the type modal does not create a blank row. Existing single-section content is always editable.
- Legacy grids with more than two columns and asymmetric two-column presets are not restructured.
- No database migration or new PHP migrations.

## Regression expectations

- Existing page titles, slugs, SEO, revisions, scheduled publishing, Legacy HTML, forms, gallery, media IDs, and module components remain unchanged.
- An empty 50/50 Row and two independently filled columns survive save/reopen; moving an entire Row preserves content and section ID.
- Cancel, close, or Escape on a selection modal does not create unrequested rows or blocks. Removing a row/child still uses Core confirmation.
- Desktop 50/50 adapts to stacked columns when the editor becomes narrow; no frontend-specific style is added.

## Local acceptance checks

1. Back up V2.4.4w and the database. No SQL import is needed.
2. Open an existing Page Builder page with Hero/Text, a media image, and, if available, a legacy Grid; check all content can still be edited, saved and reopened without changes.
3. Add a **100 %** Row, choose **Tekstsektion**, enter content; save and reopen.
4. Add a **50/50** Row, save while empty and reopen; then add **Tekst** to the left and **Billede** to the right; save and reopen.
5. Move Rows with the arrows and drag handle. Check existing `section-######` values remain unchanged in `page_blocks.block_data`, and new IDs appear only on save.
6. Check **Annuller/Escape**, removal confirmation, and responsive stacking at a narrow browser width.
7. Check the public page still looks the same as the existing renderer, and test Legacy HTML and a revision rollback if applicable.
8. Approve V2.4.4x locally before implementing either the new frontend HTML contract or Page Templates.

**Distribution:** complete `boilerplate/` ZIP with updated 2026 timestamps, 0644 files / 0755 directories; excludes `.env`, credentials, database exports, uploaded files, runtime storage, and macOS metadata.
