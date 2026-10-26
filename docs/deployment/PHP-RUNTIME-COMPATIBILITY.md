# Boilerplate CMS V2.5.0 QA1 — PHP Runtime Requirement (4A.6.9a)

**QA test candidate, NOT production release approval.**

## Version policy as of 2026-10-24

| PHP version | Installer/runtime response | Release readiness |
| --- | --- | --- |
| 8.0 / 8.1 / 8.2 and below | **Rejected** by shared minimum-version guard | Not supported by this candidate (PHP 8.2 security support ends 2026-12-31) |
| 8.3.x | Accepted, starting at 8.3.0 | Target minimum; full PHP 8.3 integration test **pending** |
| 8.4.x | Accepted | Preferred target; PHP 8.4.24 source QA only |
| 8.5.x | Accepted | PHP 8.5.1 was locally exercised by the project owner; broader compatibility pending |

Source for PHP upstream lifecycle: https://www.php.net/supported-versions.php (8.3 security supported until 2027-12-31; 8.4 until 2028-12-31; 8.5 until 2029-12-31).

## Single source of truth

- `core/bootstrap/php-requirement.php` defines the minimum version and guard using PHP 7-compatible syntax.
- `public_html/incl/init.php` and standalone `public_html/install.php` call the same guard **before** CMS code is loaded or database credentials handled.
- `core/cli/qa.php` reports PASS/FAIL for the same version floor but remains a **read-only diagnostic**.
- Admin Tools / Site Health evaluates the same minimum. All bundled `module.json` manifests, module starter and deployment instructions specify PHP 8.3.0.
- User-configured and third-party modules may declare higher PHP requirements individually; they cannot lower the Core minimum.

## Required extensions and distinct capabilities

The candidate relies on `mysqli` for MySQL, and uses PHP's JSON, session, fileinfo, filter and hashing capabilities. `openssl`, `mbstring`, `zlib` and `Phar` support additional functionality and should be verified by Site Health and with relevant workflows. A PHP version PASS alone **does not** mean host dependencies or permissions are correct. Existing extension-status behavior is not changed in 4A.6.9a.

## Hosting and QA limitations

Only an isolated source/test candidate has been produced. No automated changes to the database, PHP configuration, Apache or active CMS. A PHP upgrade can affect installed extensions, PHP-FPM pools, opcode caches and session handling; perform it independently on an isolated host with backup/rollback.

Test on actual PHP 8.3/8.4 shared hosting (and MySQL/MariaDB variants) before production GO. Nginx, nonstandard webroots, restrictive `open_basedir`, disabled `.htaccess` overrides, proxy/TLS deployments and panel-only upgrades have independent acceptance gates.
