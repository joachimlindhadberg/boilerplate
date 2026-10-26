# Boilerplate V2.4.4o — Settings Controller & View Architecture

**Base:** Locally approved V2.4.4n (`BOILERPLATE_VERSION=2.4.4.20`).  
**Candidate:** V2.4.4o (`BOILERPLATE_VERSION=2.4.4.21`).  
**Scope:** Only Settings admin presentation architecture. This release does not change functionality, UI, redirects, permissions, database schema or frontend theme.

## Changes

- `public_html/admin/settings.php`: the sole view reference changes from `admin_render('settings', ...)` to `admin_render('settings/index', ...)`. **All GET/POST handling remains unchanged**, including capability check `settings.manage`, CSRF verification, validation, persistence via `Settings::set`, activity logging, notices, redirects and exception handling.
- `public_html/admin/views/settings/index.php`: the main settings form/layout and its existing runtime URL information, notices and action button.
- `public_html/admin/views/settings/partials/{language,website,seo,notifications,maintenance,session}.php`: six presentation-only panel partials; all original form field names, values, labels, validation messages, checkbox behavior and translations preserved.
- Legacy `public_html/admin/views/settings.php` removed from the **release archive**.
- `core/cli/code-audit.php` now checks Settings controller mapping, index view, six partials + references, and absence of the legacy top-level view.
- `public_html/incl/init.php`: internal version updated from `2.4.4.20` to `2.4.4.21`.

**No SQL import required.** No CSS, SCSS, JavaScript, module, route, translation, `.htaccess`, or database modifications.

## Regression and QA

- **95 byte-identical complete HTML render scenarios**: 19 cases × 5 locales (`en`, `da`, `de`, `sv`, `nb`). Covers default/empty data, successful/error notices, general and field-specific validation errors, indexed/noindex and maintenance checkboxes, runtime URL present/absent, special HTML characters, multiline text, language and session values. Deterministic locale stubs are used for rendering comparisons; real translation catalogs are tested separately.
- Read-only Code Audit: 75 PASS, 0 FAIL.
- Core QA: 37 PASS, 2 environment WARN, 0 FAIL (environment warnings: CLI `mysqli` and `mbstring`).
- Translation QA: 1245 keys in Core and 3 modules, 5 locales, all valid.
- All PHP and JavaScript files syntax-checked; clean unpack-and-audit in final ZIP.
- Negative audit tests detect missing Settings partial, stale legacy Settings view and broken Settings controller mapping.
- Packaging preserves `boilerplate/` root, 0644 file permissions and 0755 directories. No `.env` secrets or DB dumps distributed.

These checks cannot replace real end-to-end GET/POST form saves and permission tests using the user's local Apache/MySQL installation.

## Updating an existing installation

1. Back up the approved V2.4.4n installation and database.
2. Extract the entire `boilerplate/` folder and merge into the existing project. **Keep local `.env`, uploads and database.**
3. Test the Settings page, edit and save a harmless value, verify notices and persistence, test admin-language switching and refresh, and verify failed validation retains form values.
4. Test maintenance mode and session settings cautiously, as these change public behavior or login expiry.
5. Run `php core/cli/code-audit.php` and `php core/cli/qa.php` from the project root.
6. Only after successful local testing, remove the obsolete `public_html/admin/views/settings.php` if it remains from previous installations. **Do not delete** the active controller `public_html/admin/settings.php`.

**Approval:** pending user local test. V2.4.4n remains the stable base until approved.
