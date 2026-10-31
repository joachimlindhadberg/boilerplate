# Boilerplate CMS 2.5.0 — Release candidate

A modular, PHP/MySQL website CMS with browser-based first-time setup, Page Builder, Forms, Gallery and administration. **This is a QA/release candidate, not an approved public Stable release.**

## Read this first

- **New installation:** [Installation guide](docs/deployment/INSTALLATION.md).
- **Server requirements:** [Hosting requirements](docs/deployment/HOSTING-REQUIREMENTS.md).
- **Existing website / upgrade:** [Update and rollback policy](docs/deployment/UPDATING.md) and [Safe Update Workflow – internal package review](docs/deployment/SAFE-UPDATE-WORKFLOW.md). **Do not use the first-time installer to update an existing website.**
- **Release status:** [V2.5.0 go-live checklist](docs/deployment/V2.5.0-GO-LIVE-GATE.md).

A compatible host must allow the *document root* to point to `public_html/` while the application (`core/`, `modules/`, `database/`, `storage/`, `.env`) stays in its **private parent directory**. Never expose the entire CMS source as a public document root.

The first-run wizard is **keyless**. On a publicly accessible, uninstalled site, the first person to complete it can create the administrator account. Restrict access to a **new, isolated test site** through the host until setup is complete. HTTPS and a dedicated **empty** MySQL database are required for public setup.

## Scope of this release candidate

This package is based on the owner's locally accepted **QA1 4A.6.9i3** source, dated 25 October 2026. Source/package checks are not a certification for all hosting providers. No live site was modified in packaging. Software version remains `2.5.0`.

**License:** This source package includes the GNU General Public License version 3 in `LICENSE`, consistent with the project's existing official GitHub repository license declaration. Review third-party assets and their notices separately before a public stable release.

## 31 October 2026 source cleanup candidate

The development archive keeps full tests, module-starter and F3 rehearsal evidence. The distribution archive excludes development-only tests and rehearsal helpers, and ships no local `.env`, log files, installer locks or database/media contents. A distribution candidate ZIP is **not** an automatic upgrade payload and must **not** be unpacked over an installed website. See `docs/deployment/RELEASE-CANDIDATE-2026-10-31.md`.
