# V2.5.0-RC1 — URL Configuration & Fallback (development candidate)

**Scope:** only Phase 1 of pre-stable work. The internal version remains `2.5.0`. This is **not** a Stable Production Release.

## Behavior

1. Database setting `site_url` (Indstillinger → Hjemmeside URL) is authoritative when it contains a valid HTTP(S) **origin**.
2. Environment `APP_URL` is the fallback when `site_url` is absent, invalid or not yet available to the URL resolver.
3. Request host is a final fallback **only in development**. Production without a trusted origin stops with a configuration error.
4. The Core `SiteUrl` resolver has no implicit database access. The current main application bootstrap still requires an installed database. First-time installation/bootstrapping is reserved for Phase 2.
5. Canonical redirects now resolve the effective URL **after** Settings have been instantiated, **before** response output. Enabled by `CANONICAL_REDIRECT` (default enabled only in production), preserving 301 for GET/HEAD and 308 for other request methods.
6. Public canonical URLs, sitemap, robots, export metadata and Forms sender-domain selection now share the URL resolver. Internal relative URLs are preserved.
7. Admin site settings show the active URL and source inline, no large runtime notice. Site Health diagnoses invalid/mismatched settings. Production source-only QA warns when it cannot verify database URL; `--production --database` performs read-only effective-URL HTTPS validation.

## Compatibility and deployment caution

- No database migration or data mutation. Historical migrations, modules, themes, SCSS/CSS, `.env` and uploads are unchanged.
- **Before updating production**, verify `settings.site_url` is the correct live HTTPS origin on an isolated staging copy. A stale database URL now overrides `APP_URL` and can redirect browsers away from the intended host when canonical enforcement is enabled.
- Existing root-of-domain deployments remain supported. URL entries with paths, query strings, fragments or userinfo are rejected to avoid silent stripping/redirect discrepancies; sites hosted in a subdirectory are not yet supported by current routing.
- Reverse proxies must be configured correctly for `TRUST_PROXY_HEADERS` and incoming Host/port; verify redirect behavior on staging for nondefault public ports. Production host headers are not used as redirect destinations.
- Staging must cover canonical redirection, admin login, cookies, forms, email headers, relative links, sitemap, robots, export and changing settings. The source tests here are **not** live web/staging verification.
- Rolling back to the approved Development Candidate requires restoring the previous code package only. Site URL database values must be reviewed before a rollback as the older runtime prefers `APP_URL`.

## Source validation (no live database)

- `sh tests/integration/run.sh verify-code` (includes 85 PHP regression files and DB-free URL tests).
- `sh tests/integration/run.sh verify-production` (source only).
- `python3 tests/release/release_zip.py verify /path/to/dev.zip`
- `python3 tests/release/production_zip.py verify /path/to/production-candidate.zip`

**Explicitly excluded:** installation wizard, frontend markup audit, admin topbar, dashboard-card configuration, live database fixtures, migrations and staging go-live sign-off.
