# Update Compatibility Preflight – V2.5.0 QA1

**Status: Read-only diagnostic in a test candidate. This is not an updater and does not authorize an upgrade.**

## Where to find it

The ordinary administrator page is now **Tools → Updates**, showing the installed version and an inactive update-check button until a trusted official release source exists. The read-only compatibility preflight and protected package-review POST remain internal services: they are no longer shown as end-user controls. Neither service executes migrations, imports, backups or configuration changes.

## What is checked

- Current installed code version and minimum PHP runtime.
- Required PHP extensions and availability of Phar/zlib backup tooling.
- Presence of the historical minimum base tables and `schema_migrations` registry.
- Installed migration-file hashes and database registry statuses (`applied`, `pending`, `missing-file`, `checksum-mismatch`).
- A missing or invalid migration registry, missing core tables or any checksum mismatch blocks proceeding.

## How to interpret the result

- **Blocked:** Stop and investigate the affected installed environment. No automatic repair is performed.
- **Review required:** No blocking issue was conclusively detected by this limited check, but the upgrade is **not approved**. Pending migrations require an individual deployment plan.
- There is intentionally **no "ready to update" green result**. Package review can compare an uploaded ZIP’s basic structure, version and migration file hashes but **does not authenticate the release**, verify backup contents, execute migrations, validate deployment permissions or prove recovery. See [SAFE-UPDATE-WORKFLOW.md](SAFE-UPDATE-WORKFLOW.md).

## Before any update

Follow [UPDATING.md](UPDATING.md). Take and verify separate backups of source code, `.env`, database and uploads; restore them to an isolated staging environment; rehearse the exact code and schema migration plus rollback; keep public/active sites untouched during verification.

The current browser installer is only for **new installations**. There is still **no supported no-SSH one-click update mechanism** in V2.5.0 QA1.
