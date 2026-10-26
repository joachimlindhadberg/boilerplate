# Boilerplate CMS 2.5.0 — First-Time Installation technical reference (historical RC2 filename)

**For current customer-facing instructions see [INSTALLATION.md](INSTALLATION.md) and [HOSTING-REQUIREMENTS.md](HOSTING-REQUIREMENTS.md).** This is a retained technical reference. Current cumulative locally approved base: 4A.6.9i3. Not a public Stable release.

## Prerequisites

- PHP 8.3+ (minimum 8.3.0; PHP 8.4 preferred, PHP 8.5 locally exercised) with `mysqli` and `mbstring`, supported MySQL, Apache `mod_rewrite` and HTTPS for a public server.
- Webroot points to `public_html` (never the project root). The project root and `.env` must not be publicly served.
- A **new, empty** MySQL database with a dedicated database account that can create tables, keys and the nine media-reference triggers (`CREATE TRIGGER`). Do not use the live CMS database.
- Project root must be temporarily writable by PHP to create `.env`; private `storage/` must be writable (the wizard can create it if root is writable). Restrict permissions after installation.
- On a new installation, `APP_ENV`/DB_* variables from another site must not be configured. An existing `.env` or DB_* environment disables the wizard by design.

## Keyless browser installation (4A.6.9e candidate)

1. Upload the CMS: serve **only** `public_html/` as the actual domain document root; place `core/`, `database/`, `modules/`, `.env` (after installation) and `storage/` in the private parent. Do not expose the whole project root.
2. **Immediately** open `https://your-domain/install.php` after publishing the files; the installer no longer asks for an installation/activation key. Complete setup before announcing or linking the site publicly. **Important: the first person to complete setup can become administrator.** No keyless first-run flow can prove hosting ownership. If you need a guaranteed private first-run setup, temporarily restrict access to the whole new site using the hosting control panel until installation is finished, then remove that restriction. This does not add a CMS activation key.
3. Step **1 – Database**: supply credentials for a newly created, empty database. The installer runs read-only environment checks automatically. Successful hosting checks are silent. If a mandatory check fails, the installer shows an actionable error and an expandable **Technical details** section; required failures block submission. Test connection checks table/view emptiness and InnoDB support without creating tables.
4. Step **2 – Website & administrator**: supply site name, canonical URL on the same host, administrator name/email and a password of at least 12 characters. **Install CMS** performs the schema work once and writes `.env` privately afterward, using exclusive creation.
5. Step **3 – Complete**: sign in at `/admin`. The installer is blocked once configuration exists. Verify operation in an isolated environment before public release.

The wizard does not support upgrading an existing installation. It will refuse databases with existing tables/views, including partially installed databases. It cannot prove that Apache enforces `.htaccess` or that MySQL grants all trigger privileges; the preflight lists these as unverified advisory items rather than guaranteed passes. The progress counter has three steps, of which two are input forms. No Terminal or SSH is required for a compatible host.

A new site starts with **English administration (`en`) and English (United States) website locale (`en_US`)**. Core supports five admin languages (`en`, `da`, `de`, `sv`, `nb`). Administrators can change the site-wide administration language, and users can keep a personal preference. Existing installations and saved preferences are not reset by this release.

A new site starts with inactive optional modules; enable Forms/Gallery after sign-in as desired. No notification emails are sent by the installer.

## Critical safety policy

- **Never** run the wizard on an existing CMS database. It refuses databases with any table or view, including a partially completed installation.
- An existing `.env`, even if misconfigured, prevents reinstallation. Fix connectivity instead.
- MySQL DDL is **not transactional**. A mid-install failure may leave partial tables. The wizard will refuse retries against that database. Recover manually or use a new empty database; it never drops/clears tables.
- If `.env` creation fails after schema creation, retain the database, review the server logs, and repair securely with an experienced administrator. Do not delete a populated schema indiscriminately.
- Production requires HTTPS for the installation form. If TLS terminates at a reverse proxy, configure `TRUST_PROXY_HEADERS=true` and the **actual immediate proxy IP or restricted CIDR** in `TRUSTED_PROXY_IPS` through the host's environment configuration **before visiting** `/install.php` (the `.env` does not exist yet). A correctly configured proxy must **remove inbound client-supplied forwarded headers and overwrite `X-Forwarded-Proto` with one exact `http` or `https` value on every request**. The backend must not be reachable directly by external clients through the trusted proxy address range. Missing, ambiguous or multi-valued header values fail closed; the peer IP must be the real immediate proxy, not a client IP rewritten by `mod_remoteip`/`real_ip`.
- **Local HTTP first installation is opt-in**, to avoid treating a public same-host proxy (loopback `REMOTE_ADDR`) as a local browser. For an isolated Apache development virtual host bound to `127.0.0.1` and using `boilerplate.test` or another reserved `.test`/`.localhost` host, explicitly set `INSTALL_ALLOW_LOCAL_HTTP=true` in the local virtual-host/PHP environment, e.g. Apache `SetEnv INSTALL_ALLOW_LOCAL_HTTP true` when supported. The server and remote addresses must both be loopback, hostname and vhost `SERVER_NAME` must match, and proxy headers must be absent. Do **not** enable this on public servers. This is a local development setting, not a replacement for HTTPS. If the hosting provider does not expose a trustworthy public HTTPS indicator, secure browser installation requires the provider to correct its TLS proxy configuration; do not bypass this check.
- Proxy settings are optional for ordinary direct HTTPS hosting. `TRUST_PROXY_HEADERS` defaults to false; setting it to true without `TRUSTED_PROXY_IPS` does **not** enable header trust. Never use catch-all CIDR ranges, arbitrarily broad shared-host networks or client-supplied `Forwarded` / `X-Forwarded-Host` to determine the scheme. Verify browser Set-Cookie flags, public redirects and login/logout independently on the actual HTTPS host.
- Never put `.env`, private logs, SQL dumps or private storage inside public webroot. A legacy `storage/install.key` file from an older, unfinished test installation is ignored by this release and may be cleaned up by the host owner after verifying it is not used elsewhere.
- Preexisting installs and older databases require their normal explicit migration/upgrade procedures; the first-time wizard is **not an upgrader**.

## Tests

Safe source-only tests:

```sh
sh tests/integration/run.sh verify-code
php tests/first-install-rc2.php
php tests/installer-hosting-preflight-qa1-4a6_9d1.php  # DEV package only
php tests/english-default-localization-qa1.php  # DEV package only
sh tests/integration/run.sh verify-production
```

`verify-fixture`, SQL imports, and write tests must be performed only against a **separate disposable** database with explicit permission, never the active installation. Source-only testing does **not** establish real database or staging readiness.
