# Safe Update Workflow – Internal package review (QA1)

**Candidate only. No installation or production update is authorized.**

The former technical admin view has been replaced by **Tools → Updates**. The underlying installed-site preflight and production-ZIP review services remain implemented, but **neither is presented as a user-facing workflow**. The legacy review POST remains protected by the `tools.manage` capability and CSRF checks; it is not an advertised end-user action. This review is deliberately limited: it reads the PHP-upload temporary file in memory for this request; it never extracts, stores, executes or installs archive members and never calls SQL migrations. The PHP zip extension is required for uploaded archive inspection.

## Checks

- Maximum 20 MiB ZIP; at most 1,500 entries; declared uncompressed total maximum 128 MiB and per-file limit 12 MiB.
- Expected `boilerplate/` folder structure, canonical names, no path traversal, duplicated/case-colliding names, symlinks, non-Unix permission metadata, development/test files, `.env` or user-upload media.
- Presence of baseline runtime files, version from `public_html/incl/init.php`, and whether target version is newer than installed version.
- Byte hashes of migration sources in the package compared with installed migration files. Any missing/changed historical migration blocks; any new migration mandates review in an isolated staging database.
- Show SHA-256 of inspected archive for manual comparison. **A hash displayed for the same uploaded ZIP does not authenticate it**; it must be verified against a separately trusted official release digest or signature.

## Still not implemented

- Authenticated releases / trusted digital signatures, version-to-version upgrade policy and third-party module migration compatibility.
- Complete backup verification for code + `.env` + database + uploads; rollback validation and maintenance mode.
- Applying files or migrations in a transactionally safe manner on shared hosting. MySQL DDL cannot generally be rolled back by a SQL transaction.
- Installer `/install.php` remains fresh-install only. Do not use it as an updater.

## Before any real update

1. Keep the current production installation intact. Work in a private, isolated copy with an independent database.
2. Obtain the release from an authenticated trusted source; verify the publisher-supplied SHA-256 independently, not only the ZIP's self-reported contents.
3. Take and validate complete matching backups: code, private `.env`, database and media. Do not rely solely on SiteBackup for code/config.
4. Rehearse code deployment, migrations, user workflows, and restoring all backup parts **on staging**. Document removal of obsolete files and owner/permission differences.
5. Only after this process and an explicit production release decision should an updater capable of writing be considered.

See [UPDATING.md](UPDATING.md) and [V2.5.0-GO-LIVE-GATE.md](V2.5.0-GO-LIVE-GATE.md).

## V2.5.0 QA1 — Updates admin UI redesign (26 October 2026)

The normal administrator-facing **Updates** page intentionally shows only the installed
CMS version and an update-check action. The action stays **disabled** until an official,
trusted update feed is configured in a separately tested release. Do not display
“up to date” or “update available” without a genuine successful release check.
The existing `UpdateCompatibilityPreflight` and `UpdatePackageReview` services are
retained for future internal security checks. The regular page no longer asks
end users to upload ZIP packages or manually interpret migration and backup details.
This UI-only release does **not** perform network lookups, software updates,
backups, database migrations or rollbacks.
