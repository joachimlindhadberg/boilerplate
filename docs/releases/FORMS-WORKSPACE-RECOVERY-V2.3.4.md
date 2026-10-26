# Boilerplate V2.3.4 — Forms Workspace Recovery

**Source:** Last verified base V2.3.1; replaces experimental Forms refactors V2.3.2 and V2.3.3.

## Root of regression and architecture

V2.3.2 removed the Forms view-owned page headings and navigation before the new workspace was verified in the user environment. V2.3.3 retained that dependency and introduced a second fallback mechanism. The exact reason the injected workspace was not visible on the user's server cannot be proven remotely, so no PHP cache diagnosis is asserted.

This release restores the Forms controller and CMS Core from the working V2.3.1 base, and makes each Forms admin view explicitly render the existing Core `section-workspace.php` partial through one shared, module-owned adapter. Forms templates do **not** create global UI component styling. The adapter isolates scope and remains compatible with an old cached controller passing `sectionWorkspace`, without double-wrapping output.

- Admin: Forms / Forms · Submissions / Forms · Settings; editor and detail views retain workspace nav.
- Forms POST handlers, repository, frontend, schema, routes, notifications and submission processing: unmodified from V2.3.1.
- Existing live-search, pagination, modals and form builder: preserved.
- Existing Blog workspace and other CMS functionality: unmodified.
- Five admin locales: updated page title key.
- No database migration or CSS/SCSS changes.

## Deployment

1. Back up code, DB, `.env` and uploads. Keep V2.3.1 ZIP for fallback.
2. Replace the **complete** `modules/forms/` directory with the one in V2.3.4 (remove older loose Forms files first) and update `public_html/incl/init.php`, or copy the complete ZIP while preserving configuration and uploads.
3. Restart your local PHP service (clears any stale OPcache), then hard-refresh the browser.
4. Verify `/admin/forms`, `/admin/forms/submissions`, `/admin/forms/settings`, New/Edit form, and a Submission detail. Confirm title, description and navigation are visible. Check save/edit, live search, pagination, and confirmation modal for deletion.
5. No SQL import. Do **not** re-import `boilerplate.sql` into an existing database.

## Verification boundaries

Actual Forms admin views rendered through the real ModuleContext and CMS layout in a PHP CLI harness with English/Danish translations. Browser previews test markup/layout/responsive behavior, but a real authenticated request against your MySQL/Apache stack still needs local confirmation.
