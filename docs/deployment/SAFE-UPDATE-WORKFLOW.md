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

## V2.5.0 QA1 — Check for Updates release discovery (26 October 2026)

The **Check for updates** button makes a read-only, administrator-only, CSRF-protected
POST lookup of the latest published stable GitHub Release. The software repository
is a **publisher-controlled** identifier and is pinned in the CMS source as
`joachimlindhadberg/boilerplate`. End users do not need to configure repository
names, access tokens or environment variables. The official repository is public.

As of 26 October 2026, the official repository contains **no published GitHub Releases**.
A successful lookup in this state yields **no_release**, not "up to date" and not
"update available". Merely committing to the default branch or creating a Git tag
is not a published stable release.

For isolated automated unit tests, the lookup service accepts an explicitly injected
repository identifier and HTTP transport. The ordinary admin page always uses the
pinned official repository; `.env` variables cannot redirect customers' update
checks to another repository. The official repository can be changed only through
a reviewed code release.

The lookup contacts **only** `https://api.github.com/repos/joachimlindhadberg/boilerplate/releases/latest`
with TLS verification, no redirects, an 8-second timeout and a 64-KiB response limit.
It accepts only well-formed published stable tags (`vX.Y.Z` or `X.Y.Z`) and compares
them with the installed CMS version using PHP's `version_compare`. The release URL
is constructed from the pinned repository and validated tag, not copied from
remote JSON. Failed lookups, rate limits and missing releases never show the
**up to date** message. Successful results are shown only for the current request
(not stored as verified persistent state).

This step does **not** download release assets, authenticate installation packages
or publisher signatures, install files, apply migrations, create backups or
initiate rollback. A GitHub release listing is **not** proof that a ZIP is safe
to install. One-click installation remains disabled pending a separate security
and recovery design.

## V2.5.0 QA1 — Recovery Code Snapshot Foundation (27 October 2026)

`UpdateRecoveryCodeSnapshot` is an **internal, not user-facing** building block which can
create and verify a temporary, private installed-code/configuration archive. This is
**not** a complete backup or automatic updater. `SiteBackup` still separately covers
SQL and managed media, and the two components are **not yet coordinated**. No admin
function calls the new service; no schema or frontend/UI changes are introduced.

The archive may contain `.env` and must not be downloaded to or stored within the
public webroot. It omits media contents, mutable `storage/`, external host config,
and live database data. A generated manifest/hash cannot prove a successful restore.
Test only with synthetic fixtures using `php tests/update-recovery-code-snapshot-qa1.php`.
See `docs/releases/SAFE-UPDATE-CODE-SNAPSHOT-FOUNDATION-QA1.md` for scope and limits.

## V2.5.0 QA1 — Recovery Bundle Foundation (F1b, 27 October 2026)

Internal `UpdateRecoveryBundle` coordinates the F1a installed-code/config snapshot with
`SiteBackup` SQL/media output. An independent `UpdateRecoveryDataArchive` verifies SQL and
media hashes without executing or extracting them. Complete bundles live in a private
`storage/update-recovery/` directory (0700; member files 0600), with creation locking,
atomic final-directory commit, integrity verification and fail-closed retention limit.
The end-user Updates page has **not** changed; no recovery bundle is created by HTTP.

**This is not yet a consistent point-in-time backup, a restore test or an automatic updater.**
It does not establish an application-wide write barrier, authorise GitHub ZIP assets,
install new PHP files, migrate databases, or provide automated rollback. In particular,
`SiteBackup` data capture is not atomic with its filesystem uploads or with the code
snapshot. See `docs/releases/SAFE-UPDATE-RECOVERY-BUNDLE-FOUNDATION-F1B-QA1.md` and
run `php tests/update-recovery-bundle-qa1.php` on an isolated DEV package.


## V2.5.0 QA1 — F1c Maintenance & Synthetic Recovery Drill (27 October 2026)

`UpdateMaintenanceGate` adds an internal shared/exclusive PHP request barrier and
`UpdateRecoveryDrill` privately reconstructs recovery bundle files (including SQL as
**data only**) before discarding the rehearsal. The local macOS F1c fixture passed
**26 PASS / 0 FAIL** on 27 October 2026. This did not test database import,
actual concurrent web traffic, arbitrary external writers, or a real rollback.

## V2.5.0 QA1 — F2a GitHub Release Package Verification (candidate)

`UpdateOfficialReleasePackage` is an **internal** component, with no admin route or
update action. It fetches only the official public GitHub `/releases/latest` API
endpoint. Stable numeric tags newer than the installed version require **exactly
one published binary asset named** `Boilerplate-CMS-vX.Y.Z.zip` (four-part numeric
versions are also supported). The source-code ZIP that GitHub automatically
generates is **not** an acceptable installation package.

A valid asset must have an uploaded state, an integer size <= 20 MiB and a
GitHub-published `sha256:` digest. The binary is fetched through a constructed,
pinned GitHub asset-ID endpoint, with manually reviewed HTTPS redirects restricted
to GitHub's two asset CDN hostnames. No publisher-controlled `browser_download_url`
is trusted as a fetch target. TLS verification and streaming size limits remain
enabled for cURL or PHP HTTPS streams.

The temporary binary is stored only under owner-only `storage/update-recovery/`
and always removed after inspection. Length and SHA-256 must match the release
metadata, then the existing **read-only** `UpdatePackageReview` must recognize
it as a non-blocked production ZIP with the same version. PHP `ZipArchive` is
required for this second stage. A successful result is **review-only**, never
install-ready; no archive is extracted, installed, persisted, or executed.

GitHub's SHA-256 digest validates transport/upload integrity **within the trust
placed in the official GitHub release metadata**; it is not a separate digital
signature and does not independently prove publisher authenticity if the GitHub
account or release is compromised. No signed-release policy, upgrade transaction,
full MySQL restore, or automatic rollback is implemented. A stable release with
no correctly named uploaded asset will fail closed.

Test with `php tests/update-official-release-package-qa1.php` (synthetic only;
no real GitHub requests or active-site/database writes). The isolated tool
runtime lacks PHP `ZipArchive`, so the complete positive ZIP-review path must
also be exercised on the user's PHP 8.5 installation. Do NOT publish or relabel
QA1 as a stable release merely to test downloads.

## V2.5.0 QA1 — F2b Live GitHub Probe (candidate, 27 October 2026)

F2b adds **internal/read-only** `UpdateOfficialReleaseProbe` and two DEV-only
command-line tests. The live CLI test is deliberately opt-in and must never
be called by a web route or automated release QA sweep:

```sh
php tests/update-official-release-probe-qa1.php  # mock transports only
php tests/update-official-release-live-qa1.php   # REAL outbound GitHub HTTPS
```

The service first reads the local installed version, consults the pinned
stable release lookup, and **only if a newer version is confirmed** calls F2a's
verified temporary asset download and ZIP inspection. Two GitHub reads must
agree on the exact version; a disappearing/changed release fails closed. A
verified package is **immediately discarded** and never marked install-ready.
A `no_release` or `no_new_version` diagnostic is a successful *connectivity
attempt*, **not** a verified ZIP or an update approval. No migration,
application-code change or database connection is performed. F2a may create
its owner-only private recovery-download directory and immediately delete its
temporary verified/failed download.

The official public GitHub releases listing visible during F2b planning
(27 October 2026) contains no release entries, despite an earlier successful
local update-lookup observation. Do not fabricate a latest release, change the
CMS version, or publish QA1 as Stable to make the probe pass. A future
individually approved, correctly packaged newer official Stable release is
needed to test the **real asset download path**.

F2b's integration only reaches the download-and-review boundary. It does NOT
establish a consistent backup point, real SQL restore, code deployment,
crash recovery, autonomous update, or one-click admin action.

### Publisher policy for later GitHub updates

The default branch holds source code; pushes to it must **never** deploy a
software update automatically. Only after QA, explicit release approval and
publication of a proper GitHub **Release** should the CMS consider a stable
update. Do not publish local `ONLY` or `DEV` ZIPs as installable assets.
Use exactly one uploaded Unix-regular-file distribution ZIP named:
`Boilerplate-CMS-vX.Y.Z.zip` for stable tag `vX.Y.Z` (optional fourth numeric
segment only if product release policy formally allows it). Archive members
must be rooted at `boilerplate/`, contain the required runtime structure, be
free of test/secret/media content, match the version constant, and retain
unmodified historical migrations. GitHub must expose its uploaded-asset
`sha256:` digest. Validate `UpdatePackageReview` before publication.
QA builds should be drafts or prereleases, which the updater ignores.

**Version-number cutover warning:** a future initial public **1.0.0** tag is
numerically older than the existing internal **2.5.0 QA1** constant.
`version_compare` will therefore reject 1.0.0 as an update to that existing
installation. The first public version can still be 1.0.0 for fresh installs,
but migration of internal QA installs requires an *explicit, separately tested*
cutover procedure; never silently bypass downgrade checks.
