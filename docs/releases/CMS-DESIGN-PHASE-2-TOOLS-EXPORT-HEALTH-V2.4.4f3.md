# V2.4.4f3 — Tools Export Layout & Detailed Site Health Report

Candidate internal version: `2.4.4.10`. Based directly on V2.4.4f2 (`2.4.4.9`).

## Scope

- **Tools → Export**: The sensitive-site-backup notice is now part of the site-backup option, directly below its description. It no longer occupies an independent grid row.
- Updated export introduction so it describes both full TAR.GZ backup and partial JSON content export accurately.
- **Tools → Site health**: Added an administrator-only, CSRF-protected **Download detailed report** button. The report is a UTF-8 `.txt` download and uses the selected admin language.
- Expanded diagnostics: current health checks, PHP runtime/limits/extensions, security settings, HTTPS detection, protected file permission metadata, free disk space, database connection version/charset/collation/selected table counts, registered modules, media reference index state, and actionable warnings.
- The report is strictly **read-only**; it never reads or includes the `.env` content, API secrets, password hashes, user records, form submissions, file contents, private paths or IP addresses. An installation that has credentials in its site URL will have the URL userinfo redacted.
- Report-generation errors are logged server-side, while users receive a translated generic failure message. The report is served with `no-store` and `nosniff` headers.
- New interface/diagnostic translations for Danish, English, German, Swedish and Norwegian Bokmål.

## Boundaries

- No database migrations, no module changes and no public-facing theme changes.
- Existing site backup, JSON export/import, redirects and activity logging flows are unchanged.
- Core SCSS and compiled CSS updated identically. Existing 3 px radius, dropdown 11 px offset and subtle focus styling from V2.4.4f2 are retained.
- Report is intended for support diagnostics. It may disclose technical installation details; do not post it publicly.

## Suggested local testing

1. Open **Admin → Værktøjer** and confirm warning is below the full-backup description, not between export options.
2. Click **Hent detaljeret rapport** and confirm a `.txt` file downloads; it should contain PHP, database, module and file-permission sections and the actual Site Health summary.
3. Change administration language and download again; verify headings change language.
4. Confirm that the report does not include environment secrets or user/form records.
5. Check at mobile and desktop widths, and ensure existing Site Backup and partial content export still work.

## QA performed

- PHP lint + JS syntax + translation CLI + Core QA.
- Read-only simulated database report generation on five locales, with assertions for no write queries and no private paths/secrets.
- Isolated browser layout checks at 390/620/760/1024/1440 px; no horizontal overflow.
- ZIP content/permissions/integrity verification.
- MAMP/MySQL end-to-end operations must be verified locally before this becomes the stable release.
