# Hosting requirements — Boilerplate CMS 2.5.1 (unpublished candidate)

**Status:** proposed profile for the **unpublished 2.5.1 Stable candidate**. A previously reported fresh installation on Simply.com is an installation smoke test of an earlier build, not a certification of this exact ZIP, live updates, production restores or all supported version combinations.

## Required for the first supported hosting profile

| Item | Requirement | Verification limit |
|---|---|---|
| Web server | Apache 2.4, `mod_rewrite` and effective `.htaccess` overrides | Presence of `.htaccess` is **not** proof of enforcement; confirm over HTTPS |
| Document root | Points directly to the CMS `public_html/` | Private `core/`, `database/`, `modules/`, `storage/` and `.env` must not be public |
| PHP | PHP 8.3 or later; `mysqli`, `mbstring`, `fileinfo`, `openssl` and standard JSON, session, hash/filter facilities | PHP 8.5.12 verified locally; PHP 8.3 shared-host runtime and complete PHP 8.4 matrix remain unverified |
| Database | Dedicated, **empty** MySQL database with `utf8mb4` and InnoDB | MySQL 9.5 verified locally in isolated native QA; MySQL 8.4 and MariaDB **not runtime-certified** |
| DB privileges | Ability to create tables, indexes, foreign keys and **triggers** | The installer cannot guarantee privilege suitability until DDL is attempted |
| HTTPS | Public installer and login require HTTPS | Loopback-only local HTTP testing is a separate, restricted opt-in |
| Filesystem | Host file manager/SFTP access to the private parent and public document root; PHP writes private `.env`, `storage/` and `public_html/uploads/` | Temporary private-root writability may be needed during installation; re-tighten after setup |
| Backup | Phar/PharData, zlib, space for secure temporary archive and database/media content | Must be tested on host with real size and quota limits |

Image variants use available GD/Imagick capabilities and may behave differently by PHP configuration. Upload caps are also constrained by the host's `php.ini`, disk quotas and request size limits, regardless of the application's limits.

## Deployment boundaries

- File Manager or SFTP is sufficient for **new installation** on a compatible webhost; SSH is not required for the browser wizard. Existing-site upgrades are a separate process.
- Do **not** put all CMS files inside a forced single public directory; do **not** disable upload `.htaccess` rules as a workaround.
- A domain installed in a subdirectory (`example.com/cms`), Nginx/IIS without equivalent rules, and hosts blocking private-folder access are outside the currently documented support target.
- Do not assume a different hosting plan, PHP/MySQL runtime or document-root setup is supported merely because one fresh installation succeeded. Verify the target environment independently.
- Optional MariaDB support, MySQL 8.4, and additional server profiles may be added *after runtime verification*. Unsupported does not necessarily mean incompatible; it means **unverified**.

For deployment steps see [INSTALLATION.md](INSTALLATION.md). The [V2.5.0 go-live gate](V2.5.0-GO-LIVE-GATE.md) is a **historical**, not current, readiness assessment.
