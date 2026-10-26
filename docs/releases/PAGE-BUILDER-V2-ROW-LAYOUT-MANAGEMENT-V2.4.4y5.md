# Boilerplate CMS V2.4.4y5 — Page Builder V2 Row Layout Management

**Base:** V2.4.4y4b1 (internal `2.4.4.41`); **release:** V2.4.4y5 (internal `2.4.4.42`).

## Scope

- Add Content → Layout creates an **empty** 100%, 50/50, 33/33/33, or 25/25/25/25 Row, immediately. Standalone Hero/Intro/Text/CTA remain available separately.
- Each new or partially filled V2 Row retains its columns when saved/reopened. Empty columns display the clickable **Tilføj blok** control, which opens the existing block-type modal.
- Each occupied V2 column has the same Core SVG **Edit**/**Delete** buttons used elsewhere; status is in the block's expanded editor rather than a permanent dropdown.
- The Row's Settings modal adjusts its number of columns, section container width, theme, visibility and gap.
- Adding columns is non-destructive. **Reducing column count is refused if any affected column contains a block**, without applying any of the modal's other edits. Move/remove the blocks explicitly first. A populated Row can be reconfigured safely when the removed columns are empty.
- Existing V2 rows (2/3 columns) persist; legacy 2–6 column grids and asymmetric presets remain legacy and do not migrate automatically. Changes to legacy grids retain their existing editor behavior.
- Theme owns public `site-grid--4` and mobile/tablet stacking, as well as all existing `site-grid--1/2/3` presentation. No inline styles or module frontend CSS.
- Accessible Alt+Left / Alt+Right keyboard movement for blocks between adjacent empty columns, when the block's drag grip is focused.

## Files and migrations

No SQL change. No changes to Forms, Gallery, Blog, Core icon SVGs or public PHP routing. Core admin styling: `public_html/admin/assets/scss/admin.scss` and `css/admin.css` updated in sync; Theme `layout/_layout.site-grid.scss` and `assets/css/style.css` updated in sync. Five-language JSON translations in Core.

## Manual acceptance testing (local Apache / PHP-FPM / MySQL)

1. Create a test page; create one empty Row for each of the four layouts, save and reopen; verify the empty column count remains unchanged.
2. Choose **Tilføj blok** in individual columns, create/edit/delete an image and a text block; save and reopen; verify field values and their individual status.
3. Change a 4-column Row to 2 columns while the fourth contains content; verify the modal refuses the change, preserves all content and does not partially commit theme/width.
4. Move/delete the block in the fourth column explicitly, then reduce to 3 or 2 columns; save and reopen and confirm content/IDs remain.
5. Use keyboard Alt+Left/Right on the block's drag handle to transfer it to an adjacent empty column. Check mouse drag & drop as well.
6. Inspect frontend HTML for `section.block-section > container > site-grid--1..4 > site-grid--item` and responsive 4-column Theme layout.
7. Open an existing page with asymmetric or 5/6-column legacy grid; verify its old frontend and editor settings are unchanged.
8. Recheck Forms Builder and the previously approved icon hover/border and admin overflow corrections.

**Release state:** candidate until local approval. Update all project files as a single complete ZIP; preserve your local `.env`, database and uploads. Archive root: `boilerplate/`; file mode 0644, folder mode 0755; current file timestamps to prevent old OPcache bytecode reuse.
