# Boilerplate V2.4.4i — Pages Controller & View Architecture

**Base:** User-restored V2.4.4h. **Scope:** Structural changes to Pages only.
No change to URLs, forms, Page Builder, delete workflows, database, frontend or CSS/SCSS.

## Pages

- `public_html/admin/pages.php`: the existing Pages controller; retains GET/POST processing and permissions
- `public_html/admin/views/pages/index.php`: list; GET `/admin/sider`
- `public_html/admin/views/pages/view.php`: detail; GET `?mode=view&id=...`
- `public_html/admin/views/pages/new.php`: creation form; GET `?mode=new`
- `public_html/admin/views/pages/edit.php`: edit form; GET `?mode=edit&id=...`
- `public_html/admin/views/pages/history.php`: version history
- `public_html/admin/views/pages/revision.php`: selected revision
- `public_html/admin/views/pages/partials/form-fields.php`: shared new/edit form markup
- `public_html/admin/views/pages/partials/delete-panel.php`: current delete panel on VIEW (unchanged)
- `public_html/admin/views/pages/partials/delete-modal.php`: current delete confirmation modal (unchanged)

`new` names the GET view. Existing POST to `?mode=new` performs creation in `pages.php`; no POST action is renamed in this structural release. Future controller conventions may use `action=create`, but that is **not** introduced here to avoid changing production behavior.

## View loader

`admin_render()` now accepts **validated, relative identifiers** such as `pages/new`. Path traversal and invalid paths are rejected. Existing top-level views such as `login` and `media` still resolve exactly as before. Shared layout remains in `views/partials/layout.php`.

## Installation

1. Take a complete backup of the working installation.
2. Extract the ZIP. Its root is always `boilerplate/`.
3. Copy the **contents** of this folder into your existing project root (containing `core/` and `public_html/`), allowing replacement. Do not extract into `public_html/`.
4. Retain your original `.env`, database and uploads. No SQL import.
5. Verify `public_html/admin/views/pages/new.php` and `public_html/admin/pages.php` exist in the **same deployment**.
6. Once working, remove these obsolete old files from `public_html/admin/views/`: `pages.php`, `page-detail.php`, `page-form.php`, `page-history.php`, `page-revision.php`. They are not used by the new controller; some unzip/copy tools do not remove old files automatically.
7. Test `/admin/sider`, `?mode=view`, `?mode=new`, `?mode=edit`, Page Builder, history and revisions before considering the release approved.

**Known unchanged behavior:** Delete remains on the detail view, and the existing `?mode=delete` route still exists. Do not confuse structural refactoring with a deletion-workflow fix.

## QA

Review PHP syntax, JS syntax, main route → view mapping, admin view loader path safety, HTML regression vs original V2.4.4h, JSON translation completeness, archive file permissions (0644/0755) and ZIP path integrity. Local Homebrew Apache and MySQL end-to-end tests await user validation.
