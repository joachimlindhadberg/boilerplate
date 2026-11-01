# Updates and rollback — Boilerplate CMS 2.5.1-beta.6

**Current status:** One-Click Update has been tested locally for **file-only** Beta releases. This is not a public production-compatibility or disaster-recovery certification. Updates that require new database migrations are **blocked**, not applied automatically.

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
- **Retired CMS files:** An official release may explicitly list old CMS PHP files for removal by path and old-content SHA-256. Modified, unknown, upload or user-managed files are not eligible for automatic deletion. A failure during the operation must remain recoverable. **Beta 6 retires no files** (`core/update-retired-files.json` has an empty list).
- **No manual ZIP overwrite:** A fresh distribution archive is not a safe replacement strategy for an installed site. Do not extract the ZIP over the active installation or use the first-time `/install.php` as an updater.

## Publisher workflow for immutable GitHub releases

The official GitHub repository has **release immutability enabled for future releases** (repository setting confirmed by its maintainer on 1 November 2026). Already published releases are **not retroactively immutable**. For every *new* Beta or Stable version:

1. Complete source, runtime ZIP, version, migration and local QA checks. Ensure `main` is pushed and the ZIP matches the exact intended source revision; do not package unpublished or unreviewed files.
2. Create a GitHub **Draft release** targeting that revision. Upload **one** asset with the exact versioned filename `Boilerplate-CMS-v<version>.zip`. Keep Beta/RC releases marked as prereleases.
3. While still a draft, check the asset's exact name, size, `uploaded` state and GitHub `sha256:` digest against the **locally computed** SHA-256. Check the tag's target commit, archive integrity and required runtime files. **Do not publish if anything differs.**
4. Only after those checks, publish the draft. Publication makes the asset and tag immutable. Do not attempt to overwrite a published immutable asset or reuse its tag: issue a **new version and tag** for a correction.
5. Verify the published release with GitHub's release attestation and local asset verification (GitHub CLI `gh release verify` / `gh release verify-asset` if supported), and recheck it is not a draft. Record the digest in release notes or internal QA records.

This improves publisher-side integrity but **does not add an independently pinned publisher-signature check in the CMS**. The CMS continues to trust the official repository's HTTPS metadata and SHA-256 and to refuse unsafe packages. The release publishing workflow is described with commands in [PUBLISHING-RELEASES.md](PUBLISHING-RELEASES.md); it is **not** an installation procedure for end users.

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
- [V2.5.0 go-live gate](V2.5.0-GO-LIVE-GATE.md) — historical 2.5.0 QA assessment, not the current release status.
