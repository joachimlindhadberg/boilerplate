# V2.4.4w – Page Builder V2 Data Foundation

**Approved base:** V2.4.4v (`BOILERPLATE_VERSION=2.4.4.31`).  
**Test candidate:** V2.4.4w (`BOILERPLATE_VERSION=2.4.4.32`).

## Scope

- Store a stable six-digit `section-######` anchor in `page_blocks.block_data.section_id` for each top-level Page Builder section when saved. No schema migration is required.
- Keep an existing valid ID during normal edits, sorting, and round-trip saves. Allocate a fresh unique ID for a new/copied section, missing ID, malformed ID, or duplicate ID.
- Include the ID in the existing admin editor's hidden fields to round-trip through the current controller/view pipeline.
- Preserve all pre-existing block types, content, status, column structures, frontend markup, CSS, media references, revision snapshots, publication schedules, SEO, and Legacy HTML. Older page data stays unchanged until an editor explicitly saves it.
- Page Builder V2 layout/UI/templates and its new `block-section > container > site-grid > site-grid--item` frontend contract are **not enabled** in this data-only release. They belong to subsequent isolated releases. There is no automatic rewrite of existing pages.

## Compatibility and integrity

- PageBlocks::normalizeSubmitted assigns IDs only at the save boundary. No ID generation on public page views. Revisions/imports already store entire block data arrays, so once a page is saved, its ID remains in revisions and exports.
- Never change the existing `page_blocks` schema, `getForPage`, `replaceForPage`, grid children, or frontend rendering path.
- Case-sensitive pattern `^section-[0-9]{6}$`; uniqueness enforced within the submitted page, including inactive sections.

## Local checks

1. Back up V2.4.4v and database. No SQL import necessary.
2. Edit an existing Page Builder page, save, reopen, and save once more. Confirm content, order, images, SEO, status and URL remain unchanged.
3. Inspect `page_blocks.block_data` in phpMyAdmin: each saved top-level block should now have a `section_id` like `section-123456`, unchanged on a subsequent save.
4. Reorder, add, and remove blocks in an unpublished test page. Confirm existing IDs remain stable and new IDs are unique.
5. Check the public page looks *identical* to V2.4.4v, and revision restore, Legacy HTML, scheduled publishing and module components still work.
6. Approve V2.4.4w locally before any UI/renderer conversion.

**Deployment:** complete `boilerplate/` ZIP, correct timestamps for OPcache, 0644/0755 permissions, no `.env` or private data.
