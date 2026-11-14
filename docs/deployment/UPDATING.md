# Updates and rollback — Boilerplate CMS 2.5.1 (unpublished candidate)

**Current status (14 November 2026):** `2.5.1` is an **unpublished Stable candidate**. Its narrowly scoped, forward-only `page_access` upgrade has passed an isolated native MySQL/browser recovery test. A fresh `2.5.1` installation also passed local Apache/PHP-FPM browser checks. These results do not certify the unpublished release assets, live-host HTTPS updates or recovery on all hosting environments.

## Supported upgrade path: Beta 9 to Stable 2.5.1

The published `v2.5.1-beta.9` source lacks `database/updates/20261105_000_page_access.php`. **Do not upgrade directly from Beta 9 to Stable.** The reviewed two-step path is **`2.5.1-beta.9` → `2.5.1-rc.1` (migration-free bridge) → `2.5.1` (one pinned migration)**. The bridge must first be published and confirmed usable through Tools → Updates. It keeps the original Beta 9 migration files and first-install SQL source unchanged. After installing the bridge, PHP-FPM/OPcache must serve the newly installed updater; if the host still serves stale PHP opcodes, stop and resolve that before attempting Stable. Do not disable safeguards or perform a manual ZIP overwrite.

The final upgrade permits only the exact, checksummed `core:20261105_000_page_access` migration (source SHA-256 `51f597fdff8540d29adc9ba1350297ebc78bf35d0df55fcaebe0075a2b8ef31a`). A durable forward-only journal is used; if interrupted, an authorized recovery flow checks the committed schema and can resume safely. It **does not** blindly restore a pre-upgrade SQL backup. Unknown migrations and unexpected schemas are blocked for manual investigation.

## User workflow

1. Sign in as an administrator and open **Tools → Updates**.
2. Click **Check for updates**. The CMS checks the official GitHub repository for eligible releases. The current screen has **no permanent Stable/Beta channel selector**.
3. If new releases are shown, select a version. Beta and release-candidate versions are labeled **preview**; they are not Stable releases.
4. Click **Update selected version**. The CMS rechecks the selected official release and requires a matching `Boilerplate-CMS-v<version>.zip` release asset before installation.
5. After success, verify the installed version and site behavior. If an error occurs, **do not retry blindly**: inspect the version and any interrupted-update/recovery status first.

The publisher-controlled GitHub release and asset are checked, but a separate cryptographic publisher signature is **not** verified. Integrity validation is not an independent publisher-authentication guarantee.

## Supported update behavior and safety controls

- **File-only updates:** The CMS validates official release metadata, ZIP, version, file plan and migration history. File-only updates remain supported.
- **Exact Stable 2.5.1 exception:** Only the approved `page_access` forward-only migration from the prepared bridge may execute automatically; arbitrary new/modified SQL/PHP migrations are blocked. Code files are activated only after this migration and its journal postflight succeed.
- **Recovery:** The normal file-updater makes protected snapshots. If `page_access` DDL commits but a later step fails, the durable recovery journal remains blocked until authorized, non-destructive reconciliation. A database snapshot must **not** be restored automatically over writes made after the snapshot. An interrupted update must not retire its backups.
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
