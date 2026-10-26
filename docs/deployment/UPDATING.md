# Updates and rollback — Boilerplate CMS 2.5.0 candidate

**CURRENT STATUS: A general, safe self-service webhost updater does not exist in this candidate.** An administrator-only, read-only release ZIP review is available (see [SAFE-UPDATE-WORKFLOW.md](SAFE-UPDATE-WORKFLOW.md)); it never installs an archive or runs SQL migrations. The browser-based `/install.php` is for **fresh installations only**. This document is a **staging rehearsal / planning checklist**, not approval to overwrite an active site or a guarantee of reversible database migrations.

## Update readiness: internal diagnostics

Administrators see a simple **Tools → Updates** page showing the installed version. Update checking is disabled until an official trusted source is configured. Internal preflight and ZIP review code can inspect PHP, database structure, migration hashes and candidate archives but is not exposed as a normal user-facing upload workflow. Neither service authenticates the publisher, applies migrations, writes files or approves an upgrade. See [UPDATE-COMPATIBILITY-PREFLIGHT.md](UPDATE-COMPATIBILITY-PREFLIGHT.md).

## Current mechanisms and limitations

- `core/cli/migrate.php --status` reports migration state; `--apply` performs writes. Both require **PHP CLI** and appropriate configuration. A generic no-SSH/phpMyAdmin-only migration workflow has **not** been validated for all migrations.
- The CMS data-backup facility exports database and managed upload files but **does not include application source code or the private `.env`**. Archive it separately and securely. Never upload a backup to the public webroot.
- New distribution ZIP files intentionally exclude user media and configuration. Replacing files is not an update strategy by itself: it can leave obsolete files and can mismatch application files with DB schema.
- Restoring a database snapshot may discard changes made since the backup; a rollback must be planned with matching **code, database and media** snapshots, a freeze window and testing.

## Required *isolated staging* rehearsal before any upgrade

1. Identify the **exact installed code version**, effective PHP/DB versions and installed migrations; take **complete, dated** backups of SQL data, uploads, current application code and `.env` (stored securely and separately). Verify backup integrity.
2. Restore the site into a **dedicated isolated staging directory and database**, not the active domain. Block outgoing mail, scheduled jobs and unintended public access; confirm the staging site cannot write to production services.
3. Inspect differences between old and new release files, migration status/checksums, file permissions, deprecated files and upload paths. No automatic deletion of old files or migration history is permitted.
4. If CLI is available **in staging only**, read migration state with `php core/cli/migrate.php --status`. Apply changes only under an individually reviewed, isolated test plan; `--apply` is **write-capable and must never be run by following these instructions on a live database**.
5. Verify login, permissions, Pages, Page Builder, Forms, Gallery, uploads, backups, scheduler behavior, HTTPS and frontend rendering. Restore the saved code + DB + media to the staging snapshot and verify the rollback process, including migration states.
6. Only after an approved, documented deployment/rollback plan and separate host testing should a production update even be considered. If the host has no safe update path for this version, **do not upgrade that production installation**.

## No-SSH webhosting

The first-time browser installer does not require SSH, but **upgrades that include database migrations may currently require operator assistance or CLI access**. This is an acknowledged distribution limitation, not a reason to skip migrations or import random SQL changes into an active database. A supported web-based update/migration workflow remains an unresolved release decision.

Do not treat a successful **source audit** as evidence that an existing customer's real site can be upgraded without data loss. See [V2.5.0-GO-LIVE-GATE.md](V2.5.0-GO-LIVE-GATE.md).
