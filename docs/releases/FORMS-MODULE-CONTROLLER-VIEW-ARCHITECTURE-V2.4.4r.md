# Boilerplate V2.4.4r – Forms Module Controller & View Architecture

**Base:** V2.4.4q1 (`BOILERPLATE_VERSION=2.4.4.24`), whose local code audit reported 129 PASS / 0 FAIL.  
**Candidate:** V2.4.4r (`BOILERPLATE_VERSION=2.4.4.25`).  
**Scope:** The existing Forms module's *admin controller/view organization only*. No new Forms Builder UI or templates are implemented in this release.

## Architecture

```text
modules/forms/
├── FormsModule.php                            Module entry, routes, frontend/actions, helpers
├── FormsRepository.php                        Unchanged persistence layer
└── admin/
    ├── controllers/
    │   └── FormsAdminController.php            Four admin request handlers (trait)
    ├── workspace.php                          Existing Core workspace adapter
    └── views/
        ├── index.php                          GET /admin/forms
        ├── new.php                            GET/POST /admin/forms?screen=new
        ├── edit.php                           GET/POST /admin/forms?screen=edit&id=...
        ├── submissions.php                    GET/POST /admin/forms/submissions
        ├── submission.php                     GET/POST /admin/forms/submission?id=...
        ├── settings.php                       GET/POST /admin/forms/settings
        └── partials/
            ├── forms-list.php                  Former content/index.php (unchanged)
            ├── form-editor.php                 Former content/form.php (unchanged)
            ├── submissions-list.php            Former content/submissions.php (unchanged)
            ├── submission-detail.php           Former content/submission.php (unchanged)
            └── settings-fields.php             Former content/settings.php (unchanged)
```

Because module routes call methods by name on the registered module entrypoint, `FormsAdminController.php` is a **PHP trait**, composed into `FormsModule` through `use FormsAdminController;`. This splits the four admin HTTP handlers from frontend rendering/submission logic **without changing route names or their public API**. The handlers' business logic is identical to the base; the only change in handler code is the new/edit view selection. Core still controls shared admin presentation, and the frontend Theme still owns frontend styling.

`admin/workspace.php` still owns Core workspace metadata and view selection. It now selects named partials for the six active views. It additionally accepts the legacy screen name `form` for compatibility with old cached callers during ZIP upgrades; **the old `admin/views/form.php` adapter is not distributed**. The five former `admin/views/content/*.php` files are not distributed; their content moves byte-for-byte into `admin/views/partials/*.php`.

## Behaviors unchanged

- Form index, filter/search and 10-item pagination.
- Create and edit form, field types, ordering, rows, validation, success actions and Page Builder integration.
- Form submission listing, filters, read/unread, deletion confirmations, CSRF and redirects.
- Forms settings, rate limiting, notification and confirmation settings.
- Frontend rendering, anti-spam validation, email notifications, frontend POST submission, tokens and security.
- CMS permissions, module registry/manifest, admin layout, routes, form field names, translations and CSS/SCSS.
- No changes to SQL or MySQL schema; **no SQL import required**. No PHP migrations added.

## Automated checks

- 360 offline PHP rendered HTML comparisons (6 views x 12 data variants x 5 language fixtures): **byte-identical** to V2.4.4q1, including both form-editor modes, fields and row layouts, notices, submissions, empty states, errors and pagination. Deterministic helper stubs; not a live browser/MySQL test.
- All 157 PHP files pass `php -l`; all 3 standalone JavaScript files pass `node --check`.
- Code Audit: **147 PASS / 0 FAIL** in release mode; module controller, handlers, view mappings, six screens, five partials and legacy adapter exclusion are explicitly covered.
- Negative tests: removed controller, missing form-editor, broken view routing, missing new view and broken workspace mapping all detected.
- Core QA: 37 PASS, 2 CLI environment warnings, 0 FAIL; translations: 1,245 keys across five languages.
- Moved content partials remain byte-for-byte identical to the original files. Unchanged frontend methods/helpers match V2.4.4q1 byte-for-byte; four controller methods match except for the view name.
- Validated ZIP root `boilerplate/`, 0644 file permissions, 0755 directories and no `.env`, database dump or symlink. Release audited again after fresh extraction.

## Upgrade procedure (macOS Homebrew Apache/PHP)

1. Back up the **approved site** and database.
2. Unzip the complete archive; merge the contents of the included `boilerplate/` folder with the existing site, without overwriting your live `.env` or uploads.
3. No SQL import required. Clear PHP OPcache/restart local Apache if your environment caches old controller files.
4. Open `/admin/forms`. Check list and search, create a test form, edit an existing form, reorder 100%/50-50 rows, save/reload, edit notification settings, list/read/unread/delete **test submissions**, and submit a form from frontend.
5. Run `php core/cli/code-audit.php`, `php core/cli/qa.php` and `php core/cli/translations.php`.
6. A ZIP merge **does not delete old files**. After successful local testing and a backup, you may remove the obsolete `modules/forms/admin/views/form.php` and `modules/forms/admin/views/content/` directory. **Do not delete `FormsModule.php`, `admin/workspace.php` or `FormsRepository.php`.** The installation audit tolerates these stale files (including their identical content) until cleanup; strict `--release` excludes them.

**Not included:** New Forms Builder design, modal field selection, Form Row redesign or Forms Templates; these are separate approved concept briefs for future implementation.

**Local acceptance pending.** This release does not become the new stable baseline until the user tests and approves it.
