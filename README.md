# Boilerplate CMS 2.5.1 — Stable release candidate

Boilerplate CMS is an open-source PHP/MySQL CMS with a browser-based first-time installer, Page Builder, Forms, Gallery, and a minimalist administration interface.

**Release status (14 November 2026):** Source version `2.5.1` is an **unpublished Stable release candidate**. Isolated Beta 9 → RC bridge → Stable 2.5.1 upgrade/recovery tests and a local Apache/PHP-FPM/MySQL browser test have passed. A public release, its HTTPS delivery, and live-host update/recovery are not yet approved. An earlier fresh installation on Simply.com covered a previous version only.

## Getting started

- **Fresh installation:** [Installation guide](docs/deployment/INSTALLATION.md)
- **Hosting requirements:** [Hosting requirements](docs/deployment/HOSTING-REQUIREMENTS.md)
- **Existing installation:** [Updates and rollback](docs/deployment/UPDATING.md). Do **not** run the first-time installer to upgrade an existing site.
- **Licensing and assets:** [License and asset-provenance review](docs/deployment/LICENSE-AND-ASSETS.md). Ownership of bundled assets and third-party notices require final maintainer sign-off before Stable.
- **Archived QA:** The 2.5.0 readiness assessment remains in the development repository only; it does not determine the status of this 2.5.1 candidate.

## Installation and security

The web server's document root must point to `public_html/`. Keep `core/`, `modules/`, `database/`, `storage/`, and `.env` outside the public document root. Never expose the whole project directory as a website.

The first-run installer is **keyless**. On an uninstalled public site, an unauthorized first visitor could create the administrator account. Restrict access to the new site until initial setup is complete. Use HTTPS and a dedicated empty MySQL database for public installation.

## Updates

**Existing Beta 9 sites:** A direct Beta 9 → Stable 2.5.1 upgrade is **not supported**. The approved two-step design is `2.5.1-beta.9` → migration-free preview bridge `2.5.1-rc.1` → `2.5.1`. The bridge must be published and separately verified before the Stable release. It preserves Beta 9 database migration history; the final step permits only the pinned forward-only `page_access` migration. Do not manually overwrite files or use the first-time installer to upgrade.

Under **Tools → Updates**, click **Check for updates**. If newer official releases are available, select the desired version from the release list and choose **Update selected version**. There is no persistent update-channel setting on this page. A Beta / RC release is identified as a preview.

One-Click Update supports **file-only releases** and one explicit, narrow exception: the pinned `core:20261105_000_page_access` migration in Stable `2.5.1` when upgrading from the prepared RC bridge. This migration is forward-only, uses durable recovery state and must not restore an older SQL snapshot over newer writes. Unsupported new or modified migrations remain blocked. An old CMS file can be retired only when the approved file plan and expected checksum match; user data/uploads are protected.

An official GitHub release must contain a matching uploaded `Boilerplate-CMS-v<version>.zip` asset. Do not extract a new distribution ZIP over an existing website manually. See the [update policy](docs/deployment/UPDATING.md) for limitations and recovery guidance. **Publishers:** use the [immutable GitHub release checklist](docs/deployment/PUBLISHING-RELEASES.md): draft, verify the exact asset and digest, then publish.

## QA scope and distribution

Private end-to-end QA confirmed official Beta 9 source → migration-free RC bridge → Stable 2.5.1, including a committed MySQL DDL interruption, independent external writes and browser recovery without blanket SQL restore. Local Apache 2.4/PHP-FPM 8.5/OPcache/MySQL 9.5 browser QA confirmed login, sessions, admin, frontend and update UI. These do **not** certify live-host HTTPS delivery or disaster recovery on arbitrary hosting; the reported Simply.com smoke test concerned an earlier build.

The distribution excludes local `.env` secrets, user uploads, private storage, logs, and development-only tests. Back up code, configuration, database and uploads as appropriate before upgrading.

**License:** The repository includes the GNU GPL version 3 text in [`LICENSE`](LICENSE). Review [LICENSE-AND-ASSETS.md](docs/deployment/LICENSE-AND-ASSETS.md) for the pending copyright, icon and brand-asset provenance checks; no unverified asset rights are claimed.
