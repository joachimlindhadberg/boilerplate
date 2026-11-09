# Boilerplate CMS 2.5.1 — Unpublished Stable candidate

Boilerplate CMS is an open-source PHP/MySQL CMS with a browser-based first-time installer, Page Builder, Forms, Gallery, and a minimalist administration interface.

**Release status:** Source version `2.5.1` is an **unpublished Stable release candidate**, not an approved public Stable release. This candidate is for isolated fresh-installation and acceptance QA only. The maintainer reported a successful earlier fresh installation on Simply.com; it does not certify this exact candidate, live-host One-Click Update or production recovery.

## Getting started

- **Fresh installation:** [Installation guide](docs/deployment/INSTALLATION.md)
- **Hosting requirements:** [Hosting requirements](docs/deployment/HOSTING-REQUIREMENTS.md)
- **Existing installation:** [Updates and rollback](docs/deployment/UPDATING.md). Do **not** run the first-time installer to upgrade an existing site.
- **Licensing and assets:** [License and asset-provenance review](docs/deployment/LICENSE-AND-ASSETS.md). Ownership of bundled assets and third-party notices require final maintainer sign-off before Stable.
- **Historical readiness assessment:** [V2.5.0 go-live gate](docs/deployment/V2.5.0-GO-LIVE-GATE.md) (archived; not the current release status).

## Installation and security

The web server's document root must point to `public_html/`. Keep `core/`, `modules/`, `database/`, `storage/`, and `.env` outside the public document root. Never expose the whole project directory as a website.

The first-run installer is **keyless**. On an uninstalled public site, an unauthorized first visitor could create the administrator account. Restrict access to the new site until initial setup is complete. Use HTTPS and a dedicated empty MySQL database for public installation.

## Updates

**Existing Beta 9 sites:** Do not use One-Click Update or manually extract this `2.5.1` candidate over `2.5.1-beta.9`. The source contains migration `20261105_000_page_access.php`, absent from the published Beta 9 source, and the first-install SQL baseline changed. The present updater intentionally refuses migration-bearing upgrades. An isolated, separately approved upgrade/restore procedure is required.

Under **Tools → Updates**, click **Check for updates**. If newer official releases are available, select the desired version from the release list and choose **Update selected version**. There is no persistent update-channel setting on this page. A Beta / RC release is identified as a preview.

The current One-Click Update is limited to **file-only releases without new database migrations**. It uses a private recovery snapshot and protected installation flow. Releases containing new migrations are refused. A release can explicitly retire a known old CMS file only if its expected SHA-256 matches; arbitrary files and user uploads are not automatically deleted.

An official GitHub release must contain a matching uploaded `Boilerplate-CMS-v<version>.zip` asset. Do not extract a new distribution ZIP over an existing website manually. See the [update policy](docs/deployment/UPDATING.md) for limitations and recovery guidance. **Publishers:** use the [immutable GitHub release checklist](docs/deployment/PUBLISHING-RELEASES.md): draft, verify the exact asset and digest, then publish.

## QA scope and distribution

Earlier local browser QA confirmed a Beta 5 → Beta 6 **file-only** update; subsequent private integration QA verified recovery blocking and re-admission using isolated MySQL and HTTP fixtures. These tests do **not** certify live-host database-migration upgrades or disaster recovery. The maintainer separately reported a successful **fresh installation** on Simply.com; this is not evidence that updates or restore were tested there.

The distribution excludes local `.env` secrets, user uploads, private storage, logs, and development-only tests. Back up code, configuration, database and uploads as appropriate before upgrading.

**License:** The repository includes the GNU GPL version 3 text in [`LICENSE`](LICENSE). Review [LICENSE-AND-ASSETS.md](docs/deployment/LICENSE-AND-ASSETS.md) for the pending copyright, icon and brand-asset provenance checks; no unverified asset rights are claimed.
