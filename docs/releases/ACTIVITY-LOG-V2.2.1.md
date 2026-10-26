# Boilerplate V2.2.1 – Activity Log & Recent Activity

## Installation from V2.2.0

1. Back up the existing database, source, `.env` and uploads.
2. Update the CMS code without replacing local `.env` or uploads.
3. Import **only** `database/migrations/20261012_activity_log_v2_2_1.sql` into the existing database via phpMyAdmin.
4. Reload the admin. Open **Dashboard → Recent Activity** and **Tools → Activity Log**.
5. Make a test edit on a page, then check its actor, action and local time in both places.

**Do not re-import `boilerplate.sql` into an existing database.** New installs can use `boilerplate.sql`, which already contains `activity_log`.

## Scope

- The Dashboard displays the 10 latest completed changes, newest first.
- **Tools → Activity Log** is read-only and restricted by the existing `tools.manage` capability; it supports action/type/user-or-label search and pagination (10 items per page).
- Events include page CRUD and restore, media uploads/edits/deletes, navigation, users, modules, redirects, settings, Forms, Gallery, Blog, and system-triggered page publishing/unpublishing.
- Events record who performed the action, object type/ID, a safe short label and a UTC timestamp. Display time uses the CMS timezone. A deleted user's display name is preserved in the activity snapshot.
- It intentionally does **not** record form answers, user email, passwords, tokens, raw POST data, uploaded file contents or request bodies.
- Entries start accumulating after installation. Previous CMS operations cannot be reconstructed automatically and are not backfilled.

## Important reliability boundaries

Activity logging is **best effort**, not a tamper-proof security audit. If the migration is missing or a write fails, core content saves must continue to work; the failure is recorded in the PHP error log. Automatic page-publication activity is recorded inside the existing scheduler transaction, where practical.

The log table is append-only through the CMS UI (no delete/edit controls). Direct database administrators can still change/delete records. Data retention and privacy policy should be agreed before production deployment. The complete checkout/sensitive events for future E-commerce should require a separately reviewed, transaction-consistent audit design.

## QA checklist

- Create/update/delete a page and restore an earlier revision.
- Upload multiple media and verify each successful upload creates one event.
- Save and delete menu items/menus, modify a user and change Blog/Form/Gallery records.
- Test scheduled publishing to confirm actor is `System` and action is translated.
- Verify filtering/search/pagination and empty state.
- Switch admin language English/Danish; check label translations.
- Confirm editor without `tools.manage` cannot open `/admin/vaerktoejer/activity`.
- Verify failed saves, invalid CSRF and invalid form submissions do not create events.
- Confirm existing routes, module styling, public pages and existing data remain untouched.

Automated checks cannot replace the required local browser + MySQL test.
