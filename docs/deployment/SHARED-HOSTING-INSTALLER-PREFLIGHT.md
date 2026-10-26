# 4A.6.9d1 — Hosting Requirements & Installer Preflight

**Status:** historical preflight technical detail retained. Current locally approved cumulative base is **4A.6.9i3**. For up-to-date release requirements see [HOSTING-REQUIREMENTS.md](HOSTING-REQUIREMENTS.md), [INSTALLATION.md](INSTALLATION.md), and [V2.5.0-GO-LIVE-GATE.md](V2.5.0-GO-LIVE-GATE.md). MySQL 8.4/MariaDB runtime verification remains outstanding.

## Supported deployment target (not yet externally certified)

- Apache 2.4 with enabled `.htaccess` overrides, `mod_rewrite`, and enforcement of upload-directory security directives; HTTPS for public sites. The website document root must be the CMS `public_html/` directory while `core/`, `database/`, `modules/`, `.env` and `storage/` remain in its private parent directory.
- PHP **8.3+** with `mysqli`, `mbstring`, `fileinfo`, `openssl`, `json`, `session`, `filter` and `hash`; writable private CMS root for creating `.env`, writable private `storage/`, and writable public `uploads/`; legacy `assets/uploads/` is optional, read-only and must retain its security policy if present. PharData and zlib are recommended for integrated backups.
- A precreated **empty** MySQL database with `utf8mb4`, InnoDB and suitable account privileges for **tables, indexes, foreign keys and all nine triggers**. Only MySQL 9.5.0 has been locally verified so far. This preflight does **not** certify MySQL 8.4 or MariaDB.
- FTP/SFTP or a hosting File Manager with access above the public webroot for an SSH-free initial install.

## What the installer does

The keyless Database connection step automatically runs **Hosting requirements**. Successful checks are not displayed. If a mandatory check fails, an error is displayed and **Technical details** can be expanded to show the failed requirements. The checks are read-only: inspect loaded PHP extensions/version, real document root, source-file presence/readability and local filesystem writability. Required failures appear as **Action required**, with concrete corrective advice. The database-test button is disabled when mandatory checks fail; the same checks are enforced server-side on POST both for database testing and immediately before executing irreversible schema DDL.

When the owner tests database credentials, the installer also connects and performs only read-only checks against `INFORMATION_SCHEMA.TABLES` (empty schema) and `INFORMATION_SCHEMA.ENGINES` (InnoDB support). **No DDL, throwaway tables, trigger probes, file permission changes or cleanup operations occur during preflight.** The `.env` root write probe remains part of the final guarded installation path and is performed before database DDL; filesystem `is_writable` does not guarantee all host ACLs will allow a real file creation.

## What the installer intentionally cannot verify

- **Effective Apache `.htaccess` enforcement**, `AllowOverride`/`AllowOverrideList`, or `mod_rewrite` from PHP. The three current files being present is a required source/layout check, **not a runtime security PASS**. Test routing, upload directory listing denial and script execution denial externally on the target HTTPS host.
- **All database DDL/TRIGGER privileges.** Non-destructive metadata queries cannot guarantee actual CREATE TABLE and CREATE TRIGGER execution, grant activation, binary-logging limitations, or quota. Hosting account owners must confirm privileges; a failure after DDL may leave partial tables. **Never** retry installation against that nonempty database.
- Real public HTTPS/proxy/cookie behavior, `open_basedir` restrictions beyond the paths PHP can inspect, FTP ownership after upload, host quotas, upload size/timeouts, backups, cron or secure upgrades. These remain separate hosting QA gates.

## Manual local acceptance test (isolated site only)

1. Preserve your **4A.6.9b2** approved ZIP and existing running sites. Deploy 4A.6.9d1 to a separate loopback-only virtual host and a fresh, empty dedicated test DB. Keep `INSTALL_ALLOW_LOCAL_HTTP` restricted to your test vhost. Do not reuse partially created tables or existing `.env`.
2. Open the keyless installer on an isolated test host and review the expandable checklist. Check that every required item says `OK` and advisory items say `Verify`. Normal **Test connection** and **Install CMS** must still work.
3. In a separate **disposable fixture** only, temporarily hide the packaged `uploads/.htaccess` or make the isolated upload directory unwritable. Reload the Database connection step: it must say **Action required** and block the connection/installation action. Restore the file/rights and reload. **Do not remove security files from any working/public site.**
4. Confirm installation creates 25 InnoDB tables, 9 triggers, administrator data and working login/logout. Separately verify effective Apache/HTTPS behavior before a public release.

**Code-only tests**: `php tests/installer-hosting-preflight-qa1-4a6_9d1.php`, previous installer, FK-order and InnoDB regression scripts, code/security/release audits. Actual external hosting compatibility still pending.
