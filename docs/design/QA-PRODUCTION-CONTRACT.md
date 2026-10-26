# QA & Production Readiness Contract — V2.4.0

**Opdateret leveringspolitik:** `DATABASE-AND-RELEASE-POLICY-V1.0.md` er autoritativ for de to arkivtyper og for nye manuelle `.sql`-scripts versus understøttede historiske CLI-PHP-migrationer; `CORE-UI-STANDARD-V1.0.md` er autoritativ for delte admin-komponenter.

**Release policy:** A release is a candidate until it is verified against a real Apache/PHP/MySQL installation and the owner accepts it. Code lint, static checks and mock tests alone do not establish production readiness.

## Non-negotiable boundaries

- Never ship `.env`, real credentials, user records, password hashes or real form submissions in reusable starter/seed files.
- An upgrade must never replace the existing database or uploaded media. Back up both before installation.
- Runtime routes must never execute `CREATE TABLE` / `ALTER TABLE`. New schema changes use separately delivered, manually imported `.sql` scripts; the existing CLI `MigrationRunner` continues to support historical PHP migrations (including the newer 20261016 Pages author audit). Neither path executes automatically on web requests. See `DATABASE-AND-RELEASE-POLICY-V1.0.md`.
- A plugin may own data and behavior, not shared admin components or public theme CSS. SCSS source and generated CSS must be kept in sync.
- Read-only dashboard summaries: at most five items in Recent Activity and Scheduled Content, no Edit actions. Full content lists use standard Actions column.
- Disabling a plugin must not erase its data or release stored media references.
- Confirmation modal for destructive admin actions, POST with CSRF and capability enforcement on the server.
- Migrations and full database restore must be tested against a **throwaway copy**, never first on the live database.

## Repeatable checks (no data writes)

Run from the project root using the same CLI PHP version as your web server:

```bash
php core/cli/qa.php
php core/cli/qa.php --database
php core/cli/qa.php --production
php core/cli/migrate.php --status
php core/cli/module.php validate all
```

`--database` only reads migration status; it does **not** execute migration files. `--production` intentionally fails on a development environment without production configuration. A warning/failure must be investigated, not suppressed. The CMS must run on PHP 8+ with MySQLi enabled; full backup needs Phar and zlib. Any example PHP path in documentation should be replaced by the user's actual MAMP/server PHP binary.

## Staging sign-off checklist

1. **Deployment/configuration:** PHP, extensions, MySQL permissions, `APP_ENV=production`, HTTPS effective Website URL (database `site_url`, with `APP_URL` fallback), canonical redirect, private writable logs, stable `RATE_LIMIT_SECRET`, safe upload paths, working Apache rewrite/headers.
2. **Authentication:** anonymous redirect to login, successful and invalid login, forced initial password change, remember-me and logout, role restrictions, IP/account throttle with multiple sessions.
3. **Database:** no pending/checksum-mismatch/missing migrations; no DB mutations on ordinary page load; a fresh installation requires a clean seed plus migrations and one-time CLI admin bootstrap.
4. **Core admin:** Pages CRUD, Page Builder saves/preview/revisions, dashboard five read-only records per list, Media upload/edit/deletion protection, Users and roles, Navigation, Redirects, Settings, Live Search/filters/pagination; browser back/forward works.
5. **Retired Blog:** confirm no Blog menu/routes/manifests/tables, module registry entry, scheduled jobs or sitemap entries remain after importing `database/20261013_v2_4_4c_remove_blog.sql` in phpMyAdmin. Other module data must remain intact.
6. **Forms:** fields/editor, submit, notification policy, stored submissions, spam/rate limiting, deletion restrictions. Never email real users from staging.
7. **Gallery:** create/update/delete, media reference guards, public rendering and plugin disable/re-enable.
8. **Plugin system:** manifests validate, example plugin generator/activation/deactivation and route/menu behavior; disabled plugin PHP not booted. Temporary plugins must be deleted from release copies.
9. **Backup:** produce site-data archive, inspect manifest and hashes, restore to isolated empty MySQL + upload directory, compare all table counts, spot-check file hashes and authentication, then securely dispose of backup.
10. **Scheduling:** configure cron (prefer one-minute schedule), verify scheduled Pages (and any future module providers) with real clock/timezone; traffic-trigger fallback is not a substitute for cron for an idle site.
11. **Web/browser:** 390/768/1024/1600px, keyboard navigation and confirmation modal focus, mobile menu, login show/hide password, dropdown contrast, no page-level horizontal overflow, correct 404/maintenance/robots/sitemap behavior.
12. **Production packaging:** `644` files including `.htaccess`, `755` directories, no personal data, **no tests/fixtures in production**, ZIP passes CRC, no `.env`, no logs or transient state. **Development/local-test ZIPs include tests/** for reproducible QA; see `DATABASE-AND-RELEASE-POLICY-V1.0.md`.

## Rollback

1. Save both a verified database snapshot and uploads **before** deploying.
2. Stop background cron/traffic writers if a rollback includes DB restoration.
3. Restore matching CMS code and, only if schema/data changed, the corresponding DB/media snapshot.
4. Run read-only diagnostics again. Never attempt to "undo" a MySQL migration by deleting a row from `schema_migrations`.

## Known non-blocking residuals for further QA

- Full import/restore, load testing and a complete live browser/Apache/DB matrix cannot be established from a code-only build environment.
- Legacy HTML pages are capable of containing scripts; allow editing only for trusted roles and consider a tighter CSP/content-permission design in a separate release.
- Scheduled Content admin loads at most 2,000 combined rows before client-side filtering/pagination; large installations need server-level pagination/search in a future release.
- Referenced-media scans over Page Builder JSON can grow expensive with large collections; optimize only with database-backed integration tests.
- A formal automated full HTTP + MySQL regression suite should be added in a dedicated test environment, separate from deployment ZIPs.
