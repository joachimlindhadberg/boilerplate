# Updates and rollback — Boilerplate CMS 2.5.1 (unpublished candidate)

**Current status:** Source `2.5.1` is an **unpublished Stable candidate**, not an approved production release. One-Click Update has been tested locally for **file-only** releases. A release containing new or changed database migrations is **refused** by this flow. Live-host update, database restore and disaster-recovery certification for this candidate are not complete.

## Known upgrade limitation: Beta 9 to Stable 2.5.1

The official `v2.5.1-beta.9` source does **not** contain `database/updates/20261105_000_page_access.php`, which is required by the `2.5.1` source. The first-install baseline `database/install/empty-schema.sql` has also changed. The current One-Click Update does not execute database migrations, and therefore **must refuse** this transition. Do not bypass the refusal or overwrite an installed Beta 9 site with a fresh-install distribution ZIP. Only a separate, isolated, tested migration and full restore procedure could authorize an existing-site upgrade.

## User workflow

1. Sign in as an administrator and open **Tools → Updates**.
2. Click **Check for updates**. The CMS checks the official GitHub repository for eligible releases. The current screen has **no permanent Stable/Beta channel selector**.
3. If new releases are shown, select a version. Beta and release-candidate versions are labeled **preview**; they are not Stable releases.
4. Click **Update selected version**. The CMS rechecks the selected official release and requires a matching `Boilerplate-CMS-v<version>.zip` release asset before installation.
5. After success, verify the installed version and site behavior. If an error occurs, **do not retry blindly**: inspect the version and any interrupted-update/recovery status first.

The publisher-controlled GitHub release and asset are checked, but a separate cryptographic publisher signature is **not** verified. Integrity validation is not an independent publisher-authentication guarantee.

## Supported update behavior and safety controls

- **File-only updates:** The CMS validates the release ZIP, version and migration history. New/changed unapplied migrations block installation. It does not run SQL migrations through the One-Click flow.
- **Recovery:** Before changing application files, the updater creates and verifies a private recovery snapshot. A fourth recovery bundle may be held temporarily during an update; after a successful commit, the oldest eligible verified backup can be retired to return to three. An interrupted update must prevent that cleanup.
- **Retired CMS files:** An official release may explicitly list old CMS PHP files for removal by path and old-content SHA-256. Modified, unknown, upload or user-managed files are not eligible for automatic deletion. A failure during the operation must remain recoverable. An earlier Beta 6 example retired no files; this does **not** promise that every future release has an empty retirement plan.
- **No manual ZIP overwrite:** A fresh distribution archive is not a safe replacement strategy for an installed site. Do not extract the ZIP over the active installation or use the first-time `/install.php` as an updater.

## For maintainers publishing releases

GitHub release creation, immutable asset checks and attestations are **publisher-only tasks**, not steps for website administrators. See the [maintainer publishing checklist](PUBLISHING-RELEASES.md). A published GitHub digest is not an independently pinned publisher signature checked by the installed CMS.

## Backups and restore limitations

The normal site-data backup includes database content and managed uploads, but **not** the complete application source or the private `.env`. Store protected copies of code, configuration, database and media separately, and verify that they can be restored together.

A database restore can discard writes made after the snapshot. A code-only successful browser update does **not** prove that new database migrations can safely run or be rolled back. Production-grade restore on typical shared hosting remains to be verified.

## Before using real hosting

- Check supported PHP/MySQL versions, HTTPS, private document-root layout, permissions and the first-run access restriction on an isolated test site.
- Restore a representative complete backup to an **isolated** database and directory, without writing to an active website or accidentally sending mail/running cron jobs.
- Verify admin login, permissions, Pages, Page Builder, Forms, Gallery, uploads, media, frontend output and backup/recovery.
- Test a guarded upgrade and a restoration on that host before enabling production updates. Do not disable backup/migration safeguards to force an installation.

## Related references

- [Hosting requirements](HOSTING-REQUIREMENTS.md)
- [Immutable release publishing checklist](PUBLISHING-RELEASES.md) — maintainer-only draft, ZIP validation and attestation checks.
- [Safe Update Workflow](SAFE-UPDATE-WORKFLOW.md) — internal package review and historical design details; it is not an instruction to manually upload ZIP files.
- Historical 2.5.0 readiness notes remain in the development repository only; they do not authorize this 2.5.1 release.
