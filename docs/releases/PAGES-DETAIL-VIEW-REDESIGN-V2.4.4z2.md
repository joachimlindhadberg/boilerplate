# Boilerplate CMS V2.4.4z2 – Pages Detail View Redesign

**Code base:** V2.4.4z1 Pages Workflow Improvements (intern `2.4.4.48`), which itself extends the user-approved V2.4.4z Page Templates (intern `2.4.4.47`).
**New version:** `2.4.4.49`.
**Status:** Test candidate; not considered user-approved until local verification.

## Scope

A small, read-only Pages detail-view redesign only. No database migration; no Page Builder data, frontend-theme, route, Page save/delete handler, navigation or permission changes.

- A compact top header displays the page's real title, saved status, actual public URL and system-page marker (when applicable).
- **Se side** opens the saved public URL in a new tab; **Rediger** opens the existing editor. Draft access remains subject to existing frontend publication rules, not a draft preview.
- The main column summarizes Page Builder sections with persisted ordering, section type, concise contents, the correct V2 Row count (1–4), and visible **Skjult** indicators. Legacy HTML pages instead offer a collapsed, escaped source-code disclosure.
- SEO presents only the saved SEO title/description and real public URL in a search-result-style preview. Empty metadata are explicitly indicated; no invented fallback title or description.
- A compact right column displays the existing editor type, parent, child count, last updated time, publish/unpublish schedule and navigation link. A separate panel links to the existing real revision history and displays the persisted revision count.
- The destructive **Slet** panel and confirmation modal have been removed from `pages/view.php`. They remain on `pages/partials/form-fields.php` for already created pages, with the existing POST/CSRF, system-page and child-page checks; index/detail navigation paths remain.
- All new admin styling belongs to Core's `public_html/admin/assets/scss/admin.scss` and is mirrored byte-for-byte into compiled `public_html/admin/assets/css/admin.css`. No module-owned or inline CSS, and no frontend style modifications.
- 16 new translatable labels use all five supported admin locales (`da`, `en`, `de`, `sv`, `nb`). The previous Pages tests were adjusted to accept patch versions >= their introduction rather than an exact stale patch.

## QA

- `php tests/pages-detail-redesign-v2_4_4z2.php` — 48 assertions using rendered PHP output for published, hidden, V2 single/4-column, blank SEO, scheduled, system and Legacy HTML cases, including XSS escaping.
- All 22 PHP regression-test scripts PASS.
- 35 Chromium browser layout/content checks at 375/390/768/1280/1440 px PASS, including zero horizontal overflow; screenshots inspected.
- `php core/cli/code-audit.php --release` — 165 PASS / 0 FAIL.
- `php core/cli/qa.php` — 37 PASS / 2 known CLI environment warnings / 0 FAIL.
- All 191 PHP files pass syntax lint; Core admin SCSS and CSS are byte-identical.
- ZIP validated for complete `boilerplate/` root, regular 0644 files, 0755 directories, empty uploads directory retained, no local/private config files; ZIP extracted and re-audited.

## Local manual testing

1. Inspect a published Page Builder page with visible/hidden sections; verify title, status, real URL, order, Row counts and section summaries.
2. Inspect a draft, a protected page and a system page; verify labels. *Se side* is the existing public URL, not an authorization-bypassing preview.
3. Inspect a Legacy HTML page; confirm the source is initially collapsed and toggles normally without executing markup.
4. Check pages with no SEO metadata, and pages with a publish or unpublish schedule; no false details should appear.
5. Open *Versionshistorik*, *Rediger* and *Se side*; confirm existing links, revision/history flow and new-tab behavior.
6. Confirm **Slet side** is absent from detail but remains on edit, still using safe confirmation and POST/CSRF protections; if V2.4.4z1 is not yet locally approved, test create/save/edit redirects and deletion too.
7. Check layout in a narrow/mobile browser and on a desktop.
