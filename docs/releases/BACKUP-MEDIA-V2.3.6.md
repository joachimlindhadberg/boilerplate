# Boilerplate V2.3.6 — Backup, Export & Media Safety

## Scope

This is an **additive data-safety release** based on V2.3.5. Blog module version increases to **1.2.2** for its fresh-install media FK. No changes to frontend routing, Page Builder, Forms, Gallery UI or global CSS. Keep your existing installation/database/uploads.

### 1. Two clearly distinct exports in Tools

- **Download site backup** downloads a **TAR.GZ** containing `database.sql`, `manifest.json`, and files under `public_html/uploads/` and legacy `public_html/assets/uploads/`.
- `database.sql` enumerates **all current database base tables**, including core tables and module tables, not a hard-coded subset. It includes users, role records, revisions, schedules, activity log, Blog, Forms and Gallery when installed.
- `manifest.json` reports the table row counts, timestamp, archive format, DB SQL SHA-256 and SHA-256 checksums of backed-up uploaded files.
- The existing JSON exporter is now explicitly **Partial JSON content export** (Pages, Page Builder, Redirects and Settings only). This JSON format is the **only** format accepted by the existing Import panel. Its format version is unchanged for compatibility with prior exports.
- Site backup is an **admin-only**, CSRF-protected POST response, served as an attachment with `Cache-Control: no-store`. Temporary files are outside `public_html` and deleted after the response; unreadable or missing referenced media makes the backup fail rather than silently omit files.

**Security:** TAR.GZ database backups contain private information, including user records, password hashes, form submissions and **possibly confidential settings or API keys stored in the database**. Store downloads encrypted, limit access, use HTTPS on production, and remove obsolete backups. `.env`, application source code, private server logs and any third-party external storage are **not included**. Existing CMS code and `.env` still need separate secure backups for disaster recovery.

**Operational limitations:** Requires PHP Phar and zlib. Large datasets can exceed PHP execution-time, disk-space or memory limits. Production-grade scheduled/offsite backups are still recommended. The DB dump uses a consistent read snapshot for transactional tables, but upload files are read afterward: a busy site is **not** guaranteed to be a single point-in-time snapshot across DB and filesystem. For a coherent restore point, enter maintenance mode or use hosting-level coordinated backups. Views, triggers, routines, external object storage and server/Apache settings are not backed up by this feature.

### 2. Media reference protection

- `MediaLibrary::usage()` now checks Blog featured-image references and Gallery image references **even when these modules are disabled**. The existing Pages reference check remains intact. If the relevant module table does not yet exist, it is safely ignored; any other database error stops deletion.
- Blog now adds an `ON DELETE RESTRICT` foreign key to `media(id)` in its new-install table definition. **Existing installations need the migration below**. Gallery already had an FK.
- The database FK protects against direct SQL deletion and races between usage checking and deletion. It does not erase invalid prior references automatically.
- Upload workflows and existing CMS delete-confirmation modals are unchanged.

## Upgrade from V2.3.5

1. Take an independent backup of the existing database and uploads *before* replacing any files.
2. Replace CMS code from the new release ZIP. Preserve the existing `.env`, uploads and database.
3. Open phpMyAdmin and select the existing database. **Do not import `boilerplate.sql`.**
4. Before importing the migration, run this read-only orphan check (only if the Blog module has been installed):

```sql
SELECT a.id, a.featured_media_id
FROM blog_articles a
LEFT JOIN media m ON m.id = a.featured_media_id
WHERE a.featured_media_id IS NOT NULL AND m.id IS NULL;
```

If any rows appear, correct those references deliberately (choose an existing image or clear the broken reference) **before** importing the migration. No automatic deletion/repair is performed.

5. Import `database/migrations/20261013_blog_featured_media_fk_v2_3_6.sql`. The migration is re-runnable and skips the FK if already installed. If Blog has not yet created its tables, it safely skips them; the Blog module creates the FK on future fresh activation.
6. Refresh `/admin/vaerktoejer`, locate **Site data backup**, download an archive and check that it contains `manifest.json`, `database.sql` and the expected files.
7. Test deletion of an image referenced by a Blog post; deletion **must be blocked**. Remove the image reference from the post and verify deletion can then succeed.

### Restore procedure (manual, tested on staging before production)

**There is NO one-click full restore/import in V2.3.6. Do not upload the TAR.GZ to the JSON importer.**

1. Provision a **new empty** compatible MySQL database with access restrictions. Do not restore directly over an active/production database.
2. Inspect the downloaded archive's `manifest.json` and check hashes before use. Unpack to a private directory using a trusted TAR tool; validate file paths before extraction, especially if a backup has been modified externally.
3. Import the `database.sql` file to the fresh database using MySQL CLI or phpMyAdmin (respecting upload size and timeout limits). The dump expects an empty schema and uses foreign-key checks temporarily disabled during import; it must **not** be executed blindly against an existing website.
4. Copy `public_html/uploads/` and `public_html/assets/uploads/` from the archive into the matching directories in a **separately installed copy of the correct CMS code version**.
5. Independently configure `.env`, the website URL, PHP/Apache permissions, mail providers and cron. Never copy old credentials into an uncontrolled environment.
6. Confirm tables, user logins, uploaded images, Blog featured images, forms, scheduled publishing and frontend routes. Confirm that confidential data is removed from temporary restore locations.
7. Only after a full staging verification should a maintenance window and controlled cutover to production be considered.

**IMPORTANT:** The manual procedure above has not been end-to-end tested against the user's Apache/MySQL installation in this environment. Until a clean-db restore exercise is completed, **do not treat a downloaded backup as a proven disaster-recovery solution**. Keep hosting/database backups as the primary safety net.

## Acceptance / regression checklist

- [ ] JSON partial export downloads; JSON import still accepts prior v1 JSON packages
- [ ] TAR.GZ downloads for an admin, with every installed module table present
- [ ] User and form submission records included in SQL dump; `.env` and source code excluded
- [ ] Uploaded files in both supported directories included with checksums
- [ ] Missing/blocked upload triggers an explicit error (no falsely successful archive)
- [ ] Blog image reference prevents media deletion, including after deactivating Blog
- [ ] Gallery and Page Builder references still prevent media deletion
- [ ] Unused image can be deleted normally after modal confirmation
- [ ] Existing Forms, Blog, Gallery and Navigation CRUD still work
- [ ] **Staging restore onto empty MySQL DB has been completed and verified**

### QA performed in build environment

Static PHP/JS lint, language-file key parity, archive-builder TAR.GZ smoke test, upload-path/symlink tests, mocked media usage query tests, ZIP integrity and Unix permission audit. No live MySQL server, Apache or production backup restore exercise is available in this environment.
