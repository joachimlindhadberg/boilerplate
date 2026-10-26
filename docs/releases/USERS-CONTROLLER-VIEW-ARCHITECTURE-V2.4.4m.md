# Boilerplate V2.4.4m — Users Controller & View Architecture

**Base:** Locally approved V2.4.4l (`BOILERPLATE_VERSION=2.4.4.18`).  
**Candidate:** V2.4.4m (`BOILERPLATE_VERSION=2.4.4.19`).

## Scope

Only the Users admin presentation structure changes:

- `public_html/admin/users.php` retains the existing controller, permissions, create/update/delete logic, POST/CSRF verification, and URLs. Its four view lookups now use `users/index`, `users/view`, `users/new`, `users/edit`.
- New `public_html/admin/views/users/` views follow the shared Pages/Media/Navigation convention.
- `users/new.php` and `users/edit.php` reuse `users/partials/form-context.php` and `users/partials/form-fields.php` rather than duplicating the shared user form.
- The existing deletion modal, shared `delete-confirm` view, protected self-account/last-admin handling, and password policies are unchanged.
- Core read-only code-audit is extended to verify the new Users mappings, files and absence of the three old top-level templates.
- CSS/SCSS, JavaScript, other modules, frontend, translations, and database are unchanged.

## Existing installation cleanup

A ZIP copy does not delete stale files. After backing up and validating the installation, the following legacy top-level Users templates can be removed from the active `public_html/admin/views/` directory:

- `users.php`
- `user-detail.php`
- `user-form.php`

Do not delete `public_html/admin/users.php` (the active controller) or `public_html/admin/views/delete-confirm.php` (shared by existing delete flows).

## Local test checklist

1. Load `/admin/brugere`, search/filter and paginate, and open a user.
2. Create a test user with the selected role, password and password-change requirement.
3. Edit and save the user, including role/status and optional password update.
4. Verify validation errors preserve entered values.
5. Verify editing own account does not permit changing its own role/status.
6. Verify deletion modal + existing confirmation screen for a test account; test blocked deletion for self/last active admin.
7. Test admin/editor authorization and all five admin languages.
8. Run `php core/cli/code-audit.php` and `php core/cli/qa.php`.

**Do not import SQL**: no database changes. Retain local `.env`, database and uploads.
