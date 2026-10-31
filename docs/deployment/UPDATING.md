# Updates and rollback — Boilerplate CMS 2.5.0 candidate

**CURRENT STATUS: An experimental file-only One-Click Update is available in this QA candidate, but is not production approved; schema-migration releases are blocked.** A separate read-only release ZIP review is available (see [SAFE-UPDATE-WORKFLOW.md](SAFE-UPDATE-WORKFLOW.md)); it never installs an archive or runs SQL migrations. The browser-based `/install.php` is for **fresh installations only**. This document is a **staging rehearsal / planning checklist**, not approval to overwrite an active site or a guarantee of reversible database migrations.

## 31 October 2026 QA1 code-only update candidate

A **new experimental administrator action** under Tools → Updates can now download and install an official *file-only* version update after a private recovery bundle has been created and checked. This action is **not yet production approved** and must first be exercised on an isolated test installation. The action blocks automatically when the release contains new database migrations or when installed migrations are not fully applied. The rest of this document records the pre-existing limitations of a general, migration-capable updater. For accurate current scope see `docs/releases/ONE-CLICK-UPDATE-FILE-ONLY-QA1-2026-10-31.md` in the development source.

## Stable / Beta update channels — 31 October 2026

Under **Tools → Updates**, an administrator can select **Stable** (default) or **Beta**, then click **Check for updates**. Stable considers only published stable releases through GitHub `/releases/latest`. Beta considers supported `X.Y.Z-beta.N`, `X.Y.Z-rc.N`, and stable releases, choosing the numerically newest published compatible version; GitHub drafts and inconsistent prerelease flags are ignored. The site-level choice is stored in the existing `settings` table (`update_channel`, non-autoload). Only an administrator with `tools.manage` may change it.

A published release MUST have its matching uploaded asset `Boilerplate-CMS-v<version>.zip`; GitHub's automatic source archive does not qualify. **The beta channel does not guarantee safe installation**: private backup, download integrity, valid file structure, equal version in the ZIP, and a migration-free release are still required. The updater never runs database migrations. Beta should not be selected on production websites without independent backup/restore verification.

**Release rehearsal**: Upload a genuine, unpublished-as-stable `v2.5.1-beta.1` GitHub Pre-release with a verified `Boilerplate-CMS-v2.5.1-beta.1.zip` asset, check from an isolated test CMS running 2.5.0, and perform a guarded browser update. Do not test destructive installation on the active CMS. The archived ZIP and SHA-256 are checked, but this alone does not authenticate the publisher independently of GitHub.

## Update readiness: internal diagnostics

Administrators see a simple **Tools → Updates** page showing the installed version. Update checking uses the pinned official GitHub repository and offers a Stable channel (default) or an explicitly selected Beta channel. Internal preflight and ZIP review code can inspect PHP, database structure, migration hashes and candidate archives but is not exposed as a normal user-facing upload workflow. Neither service authenticates the publisher, applies migrations, writes files or approves an upgrade. See [UPDATE-COMPATIBILITY-PREFLIGHT.md](UPDATE-COMPATIBILITY-PREFLIGHT.md).

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
