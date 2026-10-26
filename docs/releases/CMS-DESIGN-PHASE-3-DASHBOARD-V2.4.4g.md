# V2.4.4g — CMS Design System Phase 3 / Dashboard

Base: **user-approved V2.4.4f6** (internal 2.4.4.12). Internal release: **2.4.4.13**.

## Scope

- Dashboard only: metric navigation, pending scheduled-content alert, read-only recent-activity state and minor scoped styling.
- The **Scheduled** metric now opens `/admin/scheduled-content?scope=upcoming` rather than only jumping to a 5-item preview.
- The overdue alert always offers **Review scheduled content** linking to `/admin/scheduled-content?scope=overdue` (previously no action when <= 5 overdue items). Overdue titles link to their existing approved edit URLs.
- Recent Activity now explicitly distinguishes **temporarily unavailable** from **genuinely empty**; its query behavior stays unchanged.
- Added one five-language translation (`dashboard.activity_unavailable`). Shared styles and markup elsewhere remain intact.

## Preserved

- Same Dashboard metric figures and five-item list limits; existing no-data fallback `—`.
- No database/schema changes, module changes, JS changes or frontend theme edits.
- Existing CSRF/authorization remain unchanged. No data is mutated by Dashboard.
- SCSS and compiled CSS are **identical**, 3px radius, 11px select-chevron offset and quiet focus colors retained.

## Local acceptance

1. On Dashboard, **Scheduled** opens the upcoming filtered list.
2. If pending changes are overdue, alert **Review scheduled content** opens the overdue filtered list, even when 1–5 overdue items exist. Clicking the overdue title opens its editor.
3. Recent Activity correctly shows five items, an empty message if none exist, and an unavailable message if the activity query fails. Confirm empty/failed status in a test environment only; do not break your live database just to test.
4. Check 390px/mobile and desktop responsiveness, Pages/Media metrics, and navigation with keyboard.

The release stays a local test candidate until the user approves it. Keep the existing `.env`, database and uploads. No SQL import required.
