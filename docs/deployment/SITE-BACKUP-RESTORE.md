# Restoring a Boilerplate CMS site-data backup

This guide applies to **Download site backup** archives (`boilerplate-site-backup-*.tar.gz`) containing `database.sql` and `manifest.json`, plus any managed files under `public_html/uploads/` and the legacy `public_html/assets/uploads/` directory.

**Do not import this backup into a running website or a nonempty database.** The site-data backup is **not** a full installation backup and is **not** accepted by the Partial JSON content importer. There is no One-Click site restore.

## Manual restore to an isolated site

1. **Keep the archive private.** It can contain accounts, password hashes, form submissions, and settings with sensitive data. Work in an isolated, access-restricted staging environment. Disable outgoing email and scheduled jobs, and do not connect staging to production services.
2. **Verify the archive before extracting it.** Inspect `manifest.json`, validate file paths (no traversal or symlinks), and check `database.sql` and every upload against the recorded SHA-256 checksums. Extract only into a private folder, never directly into the public webroot.
3. **Install a separate copy of compatible Boilerplate CMS code.** Preserve the correct directory layout and `.htaccess` restrictions. The backup does **not** contain source code, `.env`, webserver configuration, private logs, or external/unregistered files. Obtain those separately from trusted sources and secure backups.
4. **Create a new, EMPTY MySQL database** with suitable permissions, including permission to create database triggers. Import `database.sql` using phpMyAdmin or a compatible MySQL client. The dump creates tables, inserts rows, then creates triggers. If SQL import or trigger creation fails, stop; never omit triggers or import over an active database.
5. **Restore managed upload files** into their matching relative directories under the separate installation (`public_html/uploads/` and, if included, legacy `public_html/assets/uploads/`). Keep the installed CMS's original `.htaccess` files and security protections; do not overwrite them from a backup.
6. **Configure the new installation independently.** Use a private `.env` and correct staging URL, database credentials, mail settings, and cron settings. Do not place `.env` inside `public_html/` or reuse production secrets in an uncontrolled environment.
7. **Verify the restored site.** Compare table counts, row counts, and trigger count to `manifest.json`; confirm login, roles, Pages/Page Builder, modules, forms, media links, and frontend routes. Review the environment's security/access restrictions. Remove temporary extracted SQL and private files securely when finished.

Only consider a controlled production cutover **after** the isolated restore has been verified and a separate host-level rollback plan exists. A successful archive hash check proves integrity, **not** that a full disaster recovery will succeed on a particular host. Keep independent host/offsite backups.

## Notes

- The backup SQL includes database triggers. The `database_triggers` field in the manifest records how many were exported.
- Database and upload-file capture may not be one single point-in-time snapshot on a busy site. Use a maintenance window or coordinated host backup where consistency matters.
- For a complete disaster recovery, separately safeguard the installed code version, `.env`, server/hosting configuration, and any external data stores.
