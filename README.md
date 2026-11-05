# Boilerplate CMS 2.5.1-beta.9 — Preview release

Boilerplate CMS is an open-source PHP/MySQL CMS with a browser-based first-time installer, Page Builder, Forms, Gallery, and a minimalist administration interface.

**Release status:** `2.5.1-beta.9` is a **Beta / Pre-release**, not an approved public Stable release. Local tests have passed, but real shared-hosting deployment and production recovery are not yet verified.

## Getting started

- **Fresh installation:** [Installation guide](docs/deployment/INSTALLATION.md)
- **Hosting requirements:** [Hosting requirements](docs/deployment/HOSTING-REQUIREMENTS.md)
- **Existing installation:** [Updates and rollback](docs/deployment/UPDATING.md). Do **not** run the first-time installer to upgrade an existing site.
- **Historical 2.5.0 readiness assessment:** [V2.5.0 go-live gate](docs/deployment/V2.5.0-GO-LIVE-GATE.md). This is an archived assessment, not the current Beta 9 release status.

## Installation and security

The web server's document root must point to `public_html/`. Keep `core/`, `modules/`, `database/`, `storage/`, and `.env` outside the public document root. Never expose the whole project directory as a website.

The first-run installer is **keyless**. On an uninstalled public site, an unauthorized first visitor could create the administrator account. Restrict access to the new site until initial setup is complete. Use HTTPS and a dedicated empty MySQL database for public installation.

## Updates

Under **Tools → Updates**, click **Check for updates**. If newer official releases are available, select the desired version from the release list and choose **Update selected version**. There is no persistent update-channel setting on this page. A Beta / RC release is identified as a preview.

The current One-Click Update is limited to **file-only releases without new database migrations**. It uses a private recovery snapshot and protected installation flow. Releases containing new migrations are refused. A release can explicitly retire a known old CMS file only if its expected SHA-256 matches; arbitrary files and user uploads are not automatically deleted.

An official GitHub release must contain a matching uploaded `Boilerplate-CMS-v<version>.zip` asset. Do not extract a new distribution ZIP over an existing website manually. See the [update policy](docs/deployment/UPDATING.md) for limitations and recovery guidance. **Publishers:** use the [immutable GitHub release checklist](docs/deployment/PUBLISHING-RELEASES.md): draft, verify the exact asset and digest, then publish.

## QA scope and distribution

The Beta 5 → Beta 6 file-only update was completed successfully in the local browser environment; Core QA showed 39 PASS / 0 FAIL, and three recovery bundles remained with no interrupted journal. The Legacy HTML role restriction also passed 25 isolated tests and three role-based browser checks. File-retirement and rollback behavior passed earlier isolated synthetic tests; **no actual file retirement was exercised in the Beta 6 browser update**. These results do not certify production hosting, live database-migration upgrades or disaster recovery on another host.

The distribution excludes local `.env` secrets, user uploads, private storage, logs, and development-only tests. Back up code, configuration, database and uploads as appropriate before upgrading.

**License:** GNU GPL version 3. See `LICENSE`. Review third-party assets and their notices independently before a Stable release.
