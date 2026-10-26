# Boilerplate V2.4.4n — Modules Controller & View Architecture

**Base:** Locally approved V2.4.4m (`BOILERPLATE_VERSION=2.4.4.19`).  
**Candidate:** V2.4.4n (`BOILERPLATE_VERSION=2.4.4.20`).

## Scope

This is strictly a Core admin presentation-architecture refactor. The Modules controller retains all GET/POST routes, permission enforcement, manifest registry synchronization, active module booting, activation/deactivation/update handling, CSRF verification, notices, activity logs, redirect behavior and error handling. No database queries or module lifecycle code are changed.

- `public_html/admin/modules.php` now renders `modules/index` instead of the old top-level `modules` view.
- `public_html/admin/views/modules/index.php` owns the main page and layout call.
- `public_html/admin/views/modules/partials/module-list.php` contains the installed-module list, statuses and action buttons/forms.
- `public_html/admin/views/modules/partials/deactivate-dialog.php` contains the existing confirmation modal.
- `public_html/admin/views/modules/partials/update-dialog.php` contains the existing update confirmation modal.
- The former `public_html/admin/views/modules.php` is removed from the distribution.
- The read-only `core/cli/code-audit.php` checks the new controller mapping, view/partial existence and references, and absence of the legacy Modules view.

Modules only has **one GET screen**. POST-only `?mode=activate`, `?mode=deactivate` and `?mode=update` still return redirects; they do not need artificial HTML views. The existing modal workflow is retained.

## Automated tests performed

- 70 byte-identical **full-page** HTML regression fixtures across 14 module-list and notice scenarios × 5 locales (`en`, `da`, `de`, `sv`, `nb`) against V2.4.4m. Fixtures use deterministic translation stubs; separate translation catalog QA validates all five real catalogs.
- PHP syntax checks pass for 144 PHP files; Node syntax checks pass for 3 JS files.
- Core QA: 37 PASS, 2 environment WARN (`mysqli` and `mbstring` unavailable to this CLI PHP), 0 FAIL.
- Translation QA: 1245 keys across Core and three modules / five locales.
- Read-only Code Audit: 60 PASS, 0 FAIL.
- Negative audit tests detect (1) a missing Modules partial, (2) a stale top-level Modules view, and (3) a wrong Modules controller reference. Files restored afterwards; audit passes again.
- SCSS/CSS, JavaScript, frontend, module code, database schema and `.htaccess` remain untouched.

The automated tests do not replace browser testing and real activation/deactivation/update testing against the user's local Apache/MySQL installation.

## Existing installations

ZIP extraction and merging will not remove old files. After backing up the installation and verifying that V2.4.4n works, the **legacy view only** may be deleted if it remains:

`public_html/admin/views/modules.php`

**Do not delete `public_html/admin/modules.php`** (the active controller), or the new directory `public_html/admin/views/modules/`.

## Suggested local test checklist

1. Open `/admin/modules`; verify module count and details for Forms, Gallery, Example Module.
2. Activate a test module from its button; confirm status and feedback after redirect.
3. Click deactivate; verify modal opens, Cancel does nothing, Confirm deactivates and retains module data.
4. If a real safe module update is available, verify the update-confirmation modal and update workflow; otherwise verify the modal markup/behavior only.
5. Check invalid/incompatible/update-available module states if applicable; verify no unintended activation.
6. Verify a user without `modules.manage` cannot perform actions.
7. Verify notices and admin language switching, and run `php core/cli/code-audit.php` and `php core/cli/qa.php`.

**No SQL import:** no database change in this release. Retain local `.env`, database and uploads.
