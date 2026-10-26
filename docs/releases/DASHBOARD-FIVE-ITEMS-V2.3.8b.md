# V2.3.8b -- Dashboard: maximum five items per overview

Visible release: **V2.3.8b**; internal `BOILERPLATE_VERSION`: `2.3.8.2`.

- Dashboard > Recent Activity now requests only five records from `ActivityLog::latest(5)` instead of ten.
- Dashboard > Scheduled Content already queried `LIMIT 5`; that limit is retained.
- Both dashboard lists are read-only; no row-level Edit actions.
- Tools > Activity Log keeps its existing independent pagination and default page length.
- No change to database schema, migrations, frontend, or CSS/SCSS.

## Verification

1. With more than five activity events, confirm exactly five show on Dashboard, newest first.
2. With more than five scheduled events, confirm exactly five show on Dashboard, earliest first.
3. Open Tools > Activity Log and confirm its existing pagination is unchanged.
4. Confirm both dashboard panels retain their header navigation and show no Edit buttons.

Upgrade from V2.3.8a, preserving `.env`, database, and uploads. No SQL migration is needed.
