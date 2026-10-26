# V2.4.0 — Full CMS QA & Production Readiness

**Base:** user-approved V2.3.11. **Status:** release candidate pending local Apache/MySQL sign-off. **Migration:** none for existing sites. **CSS/SCSS:** untouched, synchronized.

## Concrete fixes and deliverables

1. **ModuleMediaReferences cache:** prevent the table-reference loop from overwriting its directory cache key. Cache stays deterministic across multiple module directories and calls; fail-closed reference semantics remain.
2. **Canonical origin:** include explicit/non-default ports when comparing `APP_URL` to the incoming Host header, and preserve POST methods with HTTP 308 when redirecting to the canonical origin. GET/HEAD keep 301.
3. **Safe reusable SQL seed:** remove an earlier installation's user/password-hash and form submission from the *shipped* `boilerplate.sql`, replace notification addresses with neutral placeholders, and leave seeded modules inactive. No default administrator account is shipped. Existing MySQL data is not touched.
4. **First-admin bootstrap (new site only):** `core/cli/create-admin.php` rejects databases with existing users, generates a random 40-character temporary password and requires password change on first login. Use it only after setting up an empty database and migrations.
5. **Read-only QA CLI:** `core/cli/qa.php` validates PHP, essential files, manifest compatibility, media declarations, migration file discovery, styling synchronization, upload protection rules and optional production/database checks. It never applies migrations.
6. **Contract and sign-off:** `docs/design/QA-PRODUCTION-CONTRACT.md` provides installation, rollback, security, usability, plugin, restore and scheduled publishing acceptance criteria.
7. **Version:** `BOILERPLATE_VERSION` set to `2.4.0`.

## Verification actually completed in the build environment

- PHP syntax: 148 PHP files (including CLI tools).
- JavaScript syntax: three files using `node --check`.
- Shared admin stylesheet is byte-identical in CSS/SCSS. Frontend CSS/SCSS unmodified.
- Four existing manifests validate; Module Starter generator produces a valid PHP module and refuses to overwrite an existing module.
- 43 independent PHP assertions: module metadata, media reference consistency/cache, hook ownership and order, Auth role access, sitemap/scheduling integration ordering, time-zone/DST rejection, safe backup upload enumeration, symlink rejection, and translations.
- HTTP canonical redirect exercise against a temporary PHP server: GET wrong port => 301, POST wrong port => 308, correct port => 200.
- Read-only QA in this build environment: 32 PASS, 2 WARN, 0 FAIL. The WARNs are unavailable MySQLi and mbstring extensions *in the build container CLI*, not results from the user's server.
- Static project integrity, ZIP CRC and Unix file permissions verified.

## Not verified; mandatory local/staging acceptance

We **did not** have a live application MySQL connection or the user's MAMP/Apache session. Thus we cannot claim actual DB migration, authenticated routing, CRUD, plugin activation against MySQL, real email delivery, scheduled task execution or a successful backup/restore of their own data. The user's previously accepted V2.3.11 workflows should be re-tested in staging using the checklist. A static/lint pass alone is not an approval for a public deployment.

## Upgrade an existing V2.3.11 installation

1. Take independent database and upload backups.
2. Replace code with the new package; **do not import `boilerplate.sql`** into an existing database.
3. Preserve `.env`, existing database, uploads and local files not owned by the release.
4. Run `php core/cli/qa.php` and `php core/cli/qa.php --database` using the server's/MAMP's PHP executable.
5. Confirm `php core/cli/migrate.php --status` reports no pending migration; V2.4.0 adds **no new migrations**.
6. Test the staging checklist before promoting to production.

## Fresh deployment only

1. Configure `.env` outside `public_html` and a new empty database.
2. Import the *cleaned* `boilerplate.sql` using phpMyAdmin (SQL text only).
3. Execute `php core/cli/migrate.php --apply` in Terminal. Do not import PHP migrations into phpMyAdmin.
4. Execute `php core/cli/create-admin.php --email=admin@yourdomain.tld --first=Site --last=Owner` and keep the one-time password secret; login and change it immediately.
5. In Modules administration, synchronize/activate modules needed by the project after running all migrations; run the read-only QA diagnostics.
6. Configure a real cron for scheduled content and verify HTTPS, permissions, media and backup restoration in staging.

Never reuse `boilerplate.sql` as a backup of an existing site's content. It is a generic, non-personal starter seed only.
