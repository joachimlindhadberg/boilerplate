# Boilerplate V2.4.4r1 – Forms Delete Action Hotfix

**Candidate release – requires local Homebrew Apache/MySQL approval.**

## Purpose

Restore the missing **Slet formular** option on `/admin/forms?screen=edit&id=<id>`. V2.4.4r implemented the new Forms Controller/View layout but did not provide a delete action for forms (only for individual submissions).

## Implemented

- A **Slet** panel in the existing sidebar on an existing form's edit view, with the CMS Core danger button and confirmation dialog.
- Confirmation submits a separate POST form (not the edit/save form) to the existing Forms route with a CSRF token, matching form ID and explicit `confirm_delete=permanent`.
- The Forms controller handles the delete action separately from save; existing creation and editing logic is unchanged.
- `FormsRepository::deleteForm` locks the form row during the transaction, checks dependencies, deletes only eligible forms and relies on the existing foreign key cascade for form fields.
- **Data protection**: deletion is blocked for forms with saved submissions (existing `ON DELETE RESTRICT`) or currently referenced by Page Builder components (top-level and nested grid blocks, including drafts). The edit panel displays the reason. To remove such a form, first clear its submissions and/or page references. Deactivating a form is a non-destructive alternative.
- Clear translated success/error messages in English, Danish, German, Swedish and Norwegian Bokmål.
- Three additional static Forms code-audit checks.

## Files changed

- `modules/forms/FormsRepository.php`
- `modules/forms/admin/controllers/FormsAdminController.php`
- `modules/forms/admin/views/partials/form-editor.php`
- `modules/forms/lang/translations.json`
- `core/cli/code-audit.php`
- `public_html/incl/init.php` (`2.4.4.25` → `2.4.4.26`)

New: `docs/releases/FORMS-DELETE-ACTION-V2.4.4r1.md`.

Unchanged: forms module manifest version, database and schema, page builder, frontend, CSS/SCSS, JavaScript, other modules, Core routing.

## Automated verification

- 9 isolated repository deletion scenarios: successful deletion, blocked submissions, direct and nested Page Builder references, false matches, nonexistent form, database error rollback, malformed unrelated JSON.
- Existing **new form** HTML remains byte-identical to V2.4.4r in the fixture test.
- 8 Chromium UI runs: edit/new/submission-blocked/page-blocked at 390px and 1440px, with real existing Core `admin.js`; modal open/close, confirmation POST fields and client-side JS error check.
- Full PHP syntax checks, JS syntax checks, translation validation, Core QA and standard/release mode code audit.
- ZIP structure, exclusions, permissions (0644/0755) and fresh extraction tested.

This is not a substitute for live database/browser workflows on the user's Mac.

## Local test checklist

1. Update the complete CMS from V2.4.4r while preserving `.env`, MySQL data and uploads. No `.sql` import.
2. Open an existing form with **no submissions and no Page Builder uses**; choose **Slet formular**. Cancel once, then confirm on a disposable test form. Expect a redirect to `/admin/forms` and a success notice.
3. Edit a form **with saved submissions**. Confirm deletion is unavailable with an explanatory message. Submissions remain intact.
4. Reference a test form in the Page Builder (including in a grid). Confirm deletion becomes unavailable. Remove the reference and confirm it becomes available again.
5. Confirm ordinary form creation/editing, frontend rendering and submissions continue to work.
6. Run `php core/cli/code-audit.php` and `php core/cli/qa.php` from the project root.

**Approval:** keep the previous approved production/stable version until this hotfix passes local tests.
