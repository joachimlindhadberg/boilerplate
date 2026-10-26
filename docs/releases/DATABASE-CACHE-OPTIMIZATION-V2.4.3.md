# Boilerplate V2.4.3 — Database & Cache Optimization

**Base:** V2.4.2 (user-confirmed functioning). **CMS version:** 2.4.3.

## Changes

1. Reuse a single non-persistent mysqli connection for ordinary CMS services within the same PHP request. Avoid repeat handshakes and charset setup. `SiteBackup` retains its own independent snapshot connection.
2. Preserve per-`Db` insert ID semantics even though mysqli is shared.
3. Add `RequestCache` for explicit per-request memoization and generation-based invalidation; no cross-request data storage.
4. Read global autoload settings once per request across multiple `Settings` objects. Invalidate and refresh on writes, including direct `Db::query()` statements to `settings`.
5. Memoize navigation menu lists, lookup-by-slug, and menu items per request. Invalidate on changes to pages/menus/menu items.
6. Bypass memoization in transactions; invalidate on commit/rollback, so rolled-back settings/navigation changes do not poison cached results.
7. Reject accidental nested transactions on the same shared connection. The existing public model and route APIs remain unchanged.
8. Update `PERFORMANCE-CODE-QUALITY-CONTRACT.md` and introduce `DATABASE-CACHE-CONTRACT.md`.

## Unchanged

All existing plugin source, database schema/migrations, frontend and admin styling, pages, forms, gallery, scheduler, activity log and plugin lifecycle UI. No migrations and no CSS/SCSS changes.

## Local installation

1. Back up the V2.4.2 code and existing database. Keep `.env`, uploaded files and current database.
2. Install V2.4.3 files over V2.4.2; do **not** import `boilerplate.sql`.
3. Restart PHP/MAMP to clear OPcache, then test login/logout, Pages edits/revisions, Blog, Forms submissions, Gallery, Media, Navigation menu updates, Modules activation, Scheduled Content and Settings.
4. Verify a setting changed on the Settings page takes effect immediately, and that updates to page titles and navigation links show without stale cached text.
5. Run `php core/cli/qa.php --database` and `php core/cli/migrate.php --status` with your MAMP PHP binary if desired; no new migrations should be pending as a result of this release.

## Verification limitations

The supplied automated regression harness (kept **outside the distributed ZIP**) checks request sharing, insert ID isolation, cache invalidation, transaction bypass, and rollback/commit against a deterministic in-memory mysqli stand-in because this build environment lacks the MySQLi PHP extension and a running MySQL server. PHP/JS syntax, module manifests, file integrity and unchanged assets are checked separately. Performance claims concern avoided operations in code, **not** measured MySQL response times. Complete real MySQL/MAMP integration and concurrency testing require the local deployment.
