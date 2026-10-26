# Boilerplate V2.2.3 – Activity Log styling / CSS fix

## Fixed

- Closed a missing `@media (max-width:760px)` brace in the shared `admin.scss` and compiled `admin.css`. Previously the language controls and Activity Log rules were inadvertently scoped to narrow viewports on desktop.
- Reused core list-panel styling in Tools → Activity Log: `admin-list-toolbar`, `admin-list-search`, `admin-list-search--wide-filters`, the shared search icon, compact selects, standard buttons, reset link, `admin-list-summary`, and shared table styles.
- Removed Activity Log-specific form control CSS; only the secondary text layout rule remains in core CSS.
- Added a read-only results summary matching other CMS lists.
- Both admin stylesheet files remain byte-identical. No frontend or module styling has changed.

## Installation

Replace application code from V2.2.2. Preserve your `.env`, media uploads, and database. **No SQL migration required.** Hard refresh your browser so updated `admin.css` is loaded.

## Suggested verification

Open Tools → Activity Log on desktop and mobile. Check search, filters, reset, table, pagination and Dashboard → Recent Activity, plus the language preference dropdown. Browser- and database-level verification in the target environment remains necessary.
