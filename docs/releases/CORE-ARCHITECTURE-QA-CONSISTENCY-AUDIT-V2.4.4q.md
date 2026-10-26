# Boilerplate V2.4.4q — Core Architecture QA & Consistency Audit

**Base:** Locally approved V2.4.4p (`BOILERPLATE_VERSION=2.4.4.22`).  
**Candidate:** V2.4.4q (`BOILERPLATE_VERSION=2.4.4.23`).  
**Scope:** Read-only static Core architecture verification, code-audit correctness and release hygiene. No controller/view UI refactor and no database changes.

## Findings

1. **Confirmed local-installation false positive (fixed):** The V2.4.4p audit reported FAIL when `.env` existed in the project root, although a deployed CMS must retain its private `.env`. Its default installation audit now **ignores and does not read/hash `.env`**. Strict `--release` mode explicitly fails if `.env` is packaged. Tested using a harmless synthetic `.env`.
2. **Duplicate uploads false positive (fixed):** Duplicate detection previously applied to all runtime files. In installation mode it now excludes `public_html/uploads/`, `public_html/assets/uploads/`, `storage/` and `cache/` from *duplicate-content comparison*; all ordinary project files continue to be checked. Release mode checks all shipped files. Tested with two identical synthetic uploads.
3. **Cross-section consistency (extended):** The audit now checks the shared security bootstrap for all seven migrated controllers; shared admin layout inclusion for all 20 primary views; resolution of all 54 literal `__DIR__`-relative view includes; Navigation's match-based dynamic dispatch; six canonical Core URL rewrites; the nested Tools redirects rewrite; and the HTTP restriction on the admin views/includes directory.
4. **No proven dead Core view was found:** The seven migrated areas have their expected files and references in the V2.4.4p base. No existing view/controller/partial was deleted or rearranged. The legacy top-level login/dashboard/error/scheduled-content views serve other entry points and must not be removed based on their filenames.
5. **Architecture exception retained:** Navigation's views read submitted `$_POST` values for form repopulation, which is older behavior. It was not moved into its controller, because this release preserves exact existing UI and workflow. Forms and Gallery modules remain outside this Core-only audit/refactor scope and are scheduled for separate incremental development.

## Core controller/view inventory

| Area | Controller | View files | Partial files |
|---|---|---:|---:|
| Pages | `public_html/admin/pages.php` | 6 | 3 |
| Media | `public_html/admin/media.php` | 1 | 2 |
| Navigation | `public_html/admin/navigation.php` | 4 | 2 |
| Users | `public_html/admin/users.php` | 4 | 2 |
| Modules | `public_html/admin/modules.php` | 1 | 3 |
| Settings | `public_html/admin/settings.php` | 1 | 6 |
| Tools | `public_html/admin/tools.php` | 3 | 5 |
| **Total** | **7** | **20** | **23** |

Shared Core views (login, dashboard, 404/403, delete-confirm, password-change and scheduled-content) are kept as their existing standalone views. The 23 section partials and 2 shared global admin partials are not new files.

## Changed files

- `core/cli/code-audit.php`: adds `--release` mode; fixes installed `.env` and runtime duplicate-file handling; expands read-only architecture checks; updates report version to V2.4.4q. Existing command and `--json` remain compatible.
- `public_html/incl/init.php`: increments `BOILERPLATE_VERSION` from `2.4.4.22` to `2.4.4.23`.
- `docs/releases/CORE-ARCHITECTURE-QA-CONSISTENCY-AUDIT-V2.4.4q.md`: this report.

**Not changed:** public application controllers/views, frontend/backend CSS or SCSS, JavaScript, translations, `modules/*`, SQL schemas, database operations, `.htaccess`, URL behavior and admin form workflows. No existing files removed. No migration or SQL import required.

## Commands

```bash
# In the installed CMS: .env and user uploads are allowed and never modified.
php core/cli/code-audit.php
php core/cli/code-audit.php --json

# In a pristine unzipped release folder: rejects .env and duplicate shipped files.
php core/cli/code-audit.php --release
php core/cli/code-audit.php --release --json

# Existing read-only QA and translation tools:
php core/cli/qa.php
php core/cli/translations.php
```

`--release` is intended for a clean, unmodified distribution tree. Running it on a live installation containing `.env` will intentionally show a FAIL; that is not a production error. Neither mode reads environment-file contents, connects to MySQL or alters files.

## Tests on release source

- Core audit: **129 PASS, 0 FAIL** in both installation and `--release` modes.
- Negative tests: 9 controlled conditions correctly distinguished: local `.env` accepted / release `.env` rejected, duplicate user uploads accepted / release duplicate rejected, missing view partial detected, missing controller security bootstrap detected, broken shared layout detected, wrong rewrite detected, wrong Navigation dispatcher detected.
- PHP syntax: **155 PHP files** pass `php -l`.
- JavaScript syntax: **3 JS files** pass `node --check`.
- Core QA: **37 PASS, 2 environment WARN, 0 FAIL** (CLI lacks `mysqli` and `mbstring`; PHP CLI extensions can differ from Apache PHP).
- Translation QA: **1,245 keys** across four JSON catalogs and five locales; all pass.
- No render-output comparison necessary in this version: every PHP controller/view and all front-end/admin assets match the approved V2.4.4p bytes; only metadata and the standalone audit CLI are modified.
- Final distribution ZIP: one `boilerplate/` root; no `.env`; no private database dumps; Unix permissions `0644` (files) and `0755` (folders); structure and read-only audits verified after clean extraction.

## Limits and local approval checklist

Static inspection cannot prove the runtime behavior of dynamically constructed `include` paths, database operations, all plugin hooks or UI interactions. It does not replace MySQL and browser integration tests, and does not claim those tests were performed.

1. Back up V2.4.4p and the database. Unpack the full V2.4.4q ZIP, merging the `boilerplate/` contents while retaining `.env`, uploads and database.
2. Run the default (installation) `code-audit.php` and confirm it no longer fails because `.env` exists. Do **not** use `--release` on the live site.
3. Test login and opening Pages, Media, Navigation, Users, Modules, Settings and Tools. Test one harmless read-only view in each; only run destructive functions with an isolated fixture and backup.
4. Confirm Forms and Gallery still open; they were not changed.
5. Review `php core/cli/qa.php` environment warnings using the local Homebrew PHP binary. No SQL import is needed.

**Stable base until local user approval:** V2.4.4p.
