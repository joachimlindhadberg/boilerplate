# Boilerplate V2.4.4p — Tools Controller & View Architecture

**Base:** User-approved V2.4.4o (`BOILERPLATE_VERSION=2.4.4.21`).  
**Candidate:** V2.4.4p (`BOILERPLATE_VERSION=2.4.4.22`).  
**Scope:** Tools admin view architecture only. All functional operations, routes, UI, permissions, form names, error handling and database structures are unchanged.

## Architecture

```text
public_html/admin/
├── tools.php                                # Existing single controller
└── views/tools/
    ├── index.php                            # Tools dashboard
    ├── redirects.php                        # Redirects management
    ├── activity.php                         # Read-only activity log
    └── partials/
        ├── health.php                       # Site health summary
        ├── redirects-card.php               # Link to redirect management
        ├── activity-card.php                # Link to activity log
        ├── import.php                       # JSON import form
        └── export.php                       # Full site backup, JSON export, health report
```

The three former top-level admin views are omitted from the complete release ZIP:

- `public_html/admin/views/tools.php`
- `public_html/admin/views/redirects.php`
- `public_html/admin/views/activity.php`

**Do not delete** the active controller `public_html/admin/tools.php`, whose literal view render calls are updated to `tools/index`, `tools/redirects` and `tools/activity`.

The existing GET route `?mode=activity` (and rewrite `/admin/vaerktoejer/activity`), GET/POST redirect mode (`/admin/vaerktoejer/redirects`), and default tools mode (`/admin/vaerktoejer`) are unchanged. No new routes or views for POST/download actions were introduced.

### Logic left untouched

- Permission gate `tools.manage` via existing bootstrap, including CSRF checks.
- Read-only Activity Log search/filter/pagination and the 405 restriction for non-GET requests.
- Redirects creation, edit, deletion, notices and confirmation behavior.
- Site health display and plaintext health report download.
- Full site backup TAR.GZ streaming, cleanup and sensitive-data warnings.
- JSON export and confirmed JSON import, including error handling and redirects.
- Existing backend admin layout, frontend Theme, localized labels and form attributes.

The sole `public_html/admin/tools.php` change is the three literal `admin_render` view names. CSS, SCSS, JavaScript, data classes, translations, database, `.htaccess` and modules are not changed. `code-audit.php` now checks the three view mappings/files, all five included partials and the removal of three legacy top-level views. The package version is incremented to `2.4.4.22`.

## Verification performed

- **350 byte-for-byte identical complete HTML body renders** between V2.4.4o and V2.4.4p: 70 scenarios (Tools index 20, Redirects 25, Activity Log 25) in each of the five locales (`en`, `da`, `de`, `sv`, `nb`). Simulated cases include populated and empty lists, filtered records, form edits, special characters, status and health messages, errors and pagination. Rendering uses deterministic helper stubs, and is not a real database test.
- All **155 PHP** files pass `php -l`; all **3 JS** files pass `node --check`.
- Code audit **92 PASS, 0 FAIL**.
- Core QA **37 PASS, 2 environment WARN, 0 FAIL** (CLI `mysqli` and `mbstring` warnings).
- Translation QA: **1,245 keys across 4 catalogs / 5 locales**, passes.
- Negative audit scenarios: missing partial, stale legacy Tools view, wrong Tools controller mapping, and broken partial include all correctly detected.
- Final ZIP validated by extracting into a fresh folder and rerunning audit, QA, syntax and structure checks.
- Every ZIP file has Unix permission **0644**, directories **0755**, and there is one `boilerplate/` root directory. No `.env`, database dump, untracked development files or symbolic links are packaged.

These tests cannot replace end-to-end upload, backup, redirect or permissions checks against the user's local Homebrew Apache/MySQL installation.

## Installation and local acceptance

1. Make a backup of the locally approved V2.4.4o installation and its database.
2. Unpack and merge the **entire** `boilerplate/` folder into the existing project, preserving the live `.env`, uploads and database. **No SQL import** is needed.
3. Verify Tools main page and all five sections at `/admin/vaerktoejer`.
4. Test Site Health refresh; download the site-health report and check file extension/content.
5. Download the JSON content export and (optionally) perform a controlled import using a safe local test fixture or backed-up instance; **import may modify records**.
6. Test complete-site backup download, archive integrity and sensitive-data handling. Backups contain sensitive configuration and content; keep them secure.
7. Test Redirects listing, create, edit, filter and delete a **test redirect**; test Activity Log filter/search/pagination. Check existing permission protection.
8. Run `php core/cli/code-audit.php`, `php core/cli/qa.php` and `php core/cli/translations.php` in the CMS root.
9. Only after successful local testing, remove any still-present old top-level **views** (`public_html/admin/views/{tools,redirects,activity}.php`) from the live installation; merging a ZIP will not remove them automatically. The audit may flag these stale files until removed. Do **not** remove the Tools controller.

**Approval pending local user testing.** V2.4.4o remains the latest approved stable base until the user approves this release.
