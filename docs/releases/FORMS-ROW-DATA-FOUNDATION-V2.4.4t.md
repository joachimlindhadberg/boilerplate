# Boilerplate CMS V2.4.4t – Forms Row Data Foundation

**Base:** V2.4.4s1, locally tested and approved (`BOILERPLATE_VERSION=2.4.4.28`).  
**Candidate:** V2.4.4t (`BOILERPLATE_VERSION=2.4.4.29`, Forms module `1.3.10`).  
**Status:** Test candidate. Not considered stable until the user confirms the local MySQL/browser checks.

## Scope of this release

- Rows in Forms are persisted separately from fields in new `module_form_rows` table.
- An empty 50/50 Form Row, including one with neither field populated, can be saved and reopened. Row ordering and layout survive subsequent edits.
- Existing populated rows, 100% layouts, fields, field keys, validation and previous submissions remain in their existing structures.
- Old form records without metadata in `module_form_rows` are reconstructed from existing `module_form_fields` values until first save; **no conversion/rewrite of old forms is needed**.
- A form containing no fields may be saved as an incomplete form, but it is hidden from Page Builder's selectable components and does not render/accept frontend submissions until at least one field exists.
- New row metadata is saved in the same database transaction as the form and its fields, so failed saves roll back consistently.
- The existing Forms admin layout/JS, Core admin styling, Theme frontend CSS, other modules and Gallery compatibility adapter are unchanged except for the minimal admin row-read integration.

**Not included:** the modal-based Forms Builder V2 user interface, Skabeloner, new field types, or changes to Page Builder. Those require separate candidate releases.

## Database update – required BEFORE deploying the new PHP files

1. Make a local copy/backup of your current CMS folder, and export your current MySQL database.
2. In phpMyAdmin, select the **existing CMS database**.
3. Import `database/migrations/20261015_forms_rows_v2_4_4t.sql`. It only creates `module_form_rows` (if absent); it does not delete or modify old form, field or submission tables. This separate SQL file is the only required schema change; no new PHP migration is included.
4. Confirm `module_form_rows` exists with columns `form_id`, `row_order`, `row_layout` and a foreign key to `module_forms`.
5. Merge the complete ZIP's `boilerplate/` folder into your existing CMS root. Overwrite code files but **retain your installed `.env`, uploads and DB**. Nothing in the candidate archive contains those local files. Do not delete the Gallery compatibility file `modules/gallery/admin/views/form.php`.
6. Restart Apache/PHP if an old version remains cached by OPcache.

**Order matters.** New editor code expects the table; before importing SQL, displaying/saving the Forms editor can fail. If the SQL import fails, leave the installed V2.4.4s1 untouched while investigating.

## Regression checks

```bash
cd ~/Sites/boilerplate
php core/cli/code-audit.php
php core/cli/qa.php
php tests/forms-rows-v2_4_4t.php
```

1. Open `/admin/forms` and edit a previously created form; confirm every old label, field key, option, row and email setting is unchanged.
2. Add an empty 50/50 row **between two existing rows**, save, reopen, and confirm both empty slots and row position are preserved.
3. Put a new field in one column, save/reopen. Then fill the other column, save/reopen. Remove only one field and confirm the other column survives.
4. Create a **new** form with only one empty 50/50 row; save and reopen. It should keep both empty slots but should not be selectable from Page Builder until a field is added.
5. Test a real populated form on the frontend and submit once. Confirm required-field checks, notification/confirmation settings (if enabled), and stored submissions still work.
6. Verify Gallery open/new/edit and the Gallery compatibility adapter are unaffected.
7. Test that saving a form with rows and fields preserves existing submission history.

## Automated verification in the candidate build

- `core/cli/code-audit.php`: 165 PASS / 0 FAIL (before the additional release-specific test suite).
- `core/cli/qa.php`: 37 PASS / 2 environment warnings / 0 FAIL in the provided PHP CLI environment; `mysqli` and `mbstring` are unavailable there. Previously the user's local PHP 8.5.1 runtime reported 39 PASS / 0 WARNING / 0 FAIL.
- Standalone `tests/forms-rows-v2_4_4t.php`: empty rows, old-row reconstruction, field keys, row order, submissions, transaction rollback and admin row markup.
- Static PHP and JavaScript syntax checks, Core admin SCSS/CSS synchronization and strict distribution ZIP checks are performed before release.

**Limitations:** Tests in this execution environment use an in-memory fake database for the new transaction scenarios; no connection to the user's local MySQL/phpMyAdmin or manual browser is available. Those are explicit release acceptance criteria and must not be described as already verified.
