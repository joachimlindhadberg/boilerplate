# Boilerplate V2.4.4 — Media References & Data Integrity

**Base:** User-approved V2.4.3. **Version:** 2.4.4.

## Changes

1. Add explicit, restart-safe Core migration `database/updates/20261013_010_media_reference_index.php` with `media_reference_state`, `media_reference_index`, a restrictive FK and 9 database triggers.
2. Add `MediaReferenceIndex` to maintain/refresh the Page Builder media reference snapshot. It scans only when the database has changed since the previous build. Normal deletions use an indexed page lookup.
3. Guard media deletion with a single DB transaction and state-row lock. Race conditions between content writes and media deletion are serialized by BEFORE triggers. SQL integrity failures or corrupt Page Builder JSON prevent deletion.
4. Preserve legacy HTML path checks and all ModuleMediaReferences checks, including inactive modules.
5. Introduce `core/cli/media-index.php --status|--audit|--rebuild` for safe diagnostics and explicit refresh.
6. Enhance the existing read-only QA database check to inspect index readiness.
7. Fix complete site backup export to include **database triggers**. This prevents a restored database from silently losing the reference-maintenance logic.
8. Document the plugin/media ownership contract in `docs/design/MEDIA-REFERENCES-CONTRACT.md`.

No frontend, admin layout, JS, SCSS or compiled CSS changes. No Blog, Forms, Gallery or Page Builder editor changes. Uploaded files and existing database content are preserved.

## Installation on existing V2.4.3

1. **Back up the live database and uploads with an independent known-good method** and retain the previous V2.4.3 code ZIP. Do not import the bundled `boilerplate.sql` into your live database.
2. Deploy all V2.4.4 code files while keeping `.env`, database and upload files unchanged.
3. Use your web server/MAMP-compatible CLI PHP (with `mysqli`) from the project root:

   ```bash
   php core/cli/migrate.php --status
   php core/cli/migrate.php --apply
   php core/cli/migrate.php --status
   php core/cli/media-index.php --rebuild
   php core/cli/media-index.php --status
   php core/cli/media-index.php --audit
   ```

4. Check that the new Core migration is **applied**, the media index has `dirty: false`, and the audit shows no unexpected integrity problems.
5. Test: a media file used by an ordinary Page Builder block, a nested/hidden block, Blog featured image, and Gallery cannot be deleted. An unused test image can be deleted. After editing a Page Builder image, deletion of the newly used image must remain blocked.
6. Verify the full site backup archive includes database triggers, and perform a restore drill on a separate disposable database before treating the backup as a disaster-recovery solution.

### IMPORTANT: Database privileges

The migration needs `CREATE`, `ALTER` (if further updates require it), `REFERENCES`, and the MySQL `TRIGGER` privilege. If migration fails with an access error, **do not skip or manually mark it applied**. The schema is deliberately restart-safe; fix the privilege and rerun `--apply`. When migration or triggers are missing, safe media deletion is blocked instead of allowing silent data loss.

### phpMyAdmin warning

The migration is a **PHP migration** executed by the CMS CLI tool, **not** a `.sql` file for phpMyAdmin. Importing the PHP file into phpMyAdmin will cause a MySQL syntax error near `<?php`.

### Running from MAMP on macOS

If the system `php` binary lacks MySQLi, substitute the full path to MAMP's PHP executable (the version matching your server). Run the same commands after changing directory to the Boilerplate project root.

## Verification boundary

PHP syntax, migration structure and simulated database/usage scenarios have been checked, but this environment has **no working MySQLi extension or MySQL server**. Trigger DDL, isolation/locking behavior, phpMyAdmin restoration of compound triggers and full browser workflows **must be tested on the actual MAMP/MySQL installation before approving V2.4.4 as stable**. This is not a claim of measured performance improvement on a production-sized database.
