# Admin Controller / View Contract

**Aktuel politik:** Følg `CORE-UI-STANDARD-V1.0.md` for admin-komponenter og `DATABASE-AND-RELEASE-POLICY-V1.0.md` for SQL/legacy CLI-migrationer og pakning.

Controller entrypoints (such as `public_html/admin/pages.php`) own routing, authorization, request validation, read/write transactions and selection of view. Views in `public_html/admin/views/<section>/` render HTML and use supplied data, with reusable `partials/` for shared markup.

Recommended view names correspond to GET routes: `index.php`, `view.php`, `new.php`, `edit.php`, with specialized names when necessary (`history.php`, `revision.php`). `new` means the creation FORM, not a database write. POST actions are handled by the controller (typically create/update), with POST+CSRF for mutating operations. Do not change existing POST route contracts solely for naming consistency.

`admin_render('pages/new', $data)` is a logical view identifier, not a request-provided filesystem path. Do not pass untrusted route strings straight to the renderer. Shared admin presentation remains Core-owned; modules should reuse its components. Public frontend appearance is theme-owned. New schema changes default to standalone phpMyAdmin-importable `.sql`; already shipped CLI PHP migrations remain supported and unchanged.

Refactor existing sections incrementally and approve each release after actual local integration tests. Do not add empty view files to sections that do not need them.
