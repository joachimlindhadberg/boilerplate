# Boilerplate CMS V2.4.4z6a – Forms Row Settings Hotfix

**Internal version:** 2.4.4.56. **Parent:** V2.4.4z6 (2.4.4.55), still **not user approved**. **Last user-approved stable base:** V2.4.4z5 (2.4.4.54).

## Problem and fix

V2.4.4z6 introduced the approved Forms Row/Column design but omitted settings on existing rows. This hotfix restores the missing functionality without a general UI rewrite.

- Each Forms Row now has a Core-owned gear/settings SVG next to the existing Core delete action.
- A compact Core modal offers exactly **1 column (100%)** or **2 columns (50/50)** with existing shared Page Builder layout previews.
- Settings are applied **only when Gem indstillinger is clicked**. Closing/Cancel/Escape makes no changes.
- Changing **1 → 2** retains the first field and adds a new, empty, usable second column.
- Changing **2 → 1** is allowed only if column 2 has no field. If it has a field, the dialog displays a localized warning and leaves the row, field keys and content unchanged. The user must explicitly remove the second field before changing to one column. No silent deletion is ever performed.
- The existing `rows[index][layout]`, `rows[index][fields][column]` POST protocol, row metadata persistence, reindexing and frontend output are unchanged.
- All new text is localized in **da, en, de, sv, nb**. Existing Core SCSS and compiled CSS are untouched and remain byte-identical.

## Scope exclusions

No changes to Page Builder, other modules, database/migrations, frontend layout, Forms validation, templates, field-type options or module configuration. The later agreed Row header reduction (`Row` / `N kolonner`) is **not** part of this hotfix. No database update, import or data migration is needed.

## Automated verification (development environment)

- Code Audit `--release`: **165 PASS / 0 FAIL**.
- Core QA: **37 PASS / 2 CLI-extension warnings / 0 FAIL**.
- New targeted PHP test: **48 PASS / 0 FAIL**.
- Existing PHP regression scripts: **30 total test scripts pass**, including the new one (historic tests were adjusted to tolerate version increments and the new Core settings action).
- Chromium real Forms-view fixture + actual `admin.js`: **135 PASS / 0 FAIL** at 375, 390, 768, 1280, 1440 px. Core destructive confirmation is simulated in the fixture. No actual database writes are performed.
- PHP syntax lint: **200 PHP files / 0 errors**. Core SCSS/CSS synchronization verified; module manifests pass.
- `qa.php --database` is **not verified** here: this container CLI lacks `mysqli`. Use the local PHP/MySQL environment to check it read-only.

## Local acceptance procedure

1. Back up current files, `.env`, database and uploads. Install the complete ZIP over the current z6 candidate. **Do not replace `.env`, uploads or database**.
2. Open Forms → an existing form. For a row with two columns and an empty second column, click its new gear icon. Select **1 column**, choose **Annuller** and verify unchanged; reopen and click **Gem indstillinger**, then confirm the row becomes full width.
3. Reopen settings, choose **2 columns**, save, and confirm the original first-column field is retained and the new empty second column accepts a new field.
4. Add a field to the second column; attempt to switch back to **1 column**. You must get a clear error without field loss. Cancel; ensure both fields still exist.
5. Remove the second-column field deliberately; then change to **1 column**. Save the form, reload the page, and verify layout, first field label, key, required state and any other settings persist. Confirm normal frontend submission.
6. Optional: test Tab, Escape and small-screen browser widths; check row reorder by dragging/Alt+arrow.

**Status:** Development/test candidate. Await explicit local approval. No production sign-off implied by automation tests.
