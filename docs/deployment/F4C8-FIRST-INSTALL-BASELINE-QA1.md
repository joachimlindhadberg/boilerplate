# F4c.8 — FirstInstall Migration Baseline Alignment QA1

Base: `stable-prep` at `059c8e123e730ae6074651a1cd1bea2ddc783763` (approved F4c.7).

## Root cause

`FirstInstall::stampMigrations()` had a strict seven-ID allowlist while actual CMS discovery returned eight migration files. The missing ID was `core:20261105_000_page_access`. Independently, `database/install/empty-schema.sql` lacked the `page_access` table. Simply adding the ID would falsely mark the migration as applied without creating its table.

## Narrow correction

1. Add `page_access` DDL to the fresh-install SQL baseline **after** the `pages` primary key has been created. Its columns, primary key, InnoDB charset, and `ON DELETE CASCADE` foreign key must match the existing `20261105_000_page_access.php` migration exactly in meaning.
2. Add only the missing migration ID to the *fixed* `FirstInstall` allowlist. Keep the fail-closed unknown-ID comparison, SHA-256 stamping, empty-database refusal, and one-time `.env` installation guard.
3. Update the F4c.7 static regression assertion so its formerly known blocker must now be aligned, not absent.
4. Add independent F4c.8 static Core contracts and an external native FirstInstall test.

Production migration `database/updates/20261105_000_page_access.php` is **not edited**. Existing CMS installations and their migration history are **not modified** by this package. A normal fresh install must not silently execute an upgrade against an existing database.

## Five reviewed source changes

- Modified: `core/classes/FirstInstall.php`
- Modified: `database/install/empty-schema.sql`
- Modified: `tests/update-real-auth-recovery-f4c7-qa1.php`
- New QA: `tests/first-install-baseline-f4c8-qa1.php`
- New documentation: `docs/deployment/F4C8-FIRST-INSTALL-BASELINE-QA1.md`

The ZIP's `apply.py` pins the F4c.7 commit, remote, three existing Git blob SHAs, three additional unchanged dependency SHAs, exact source replacement anchors, and the two added QA file SHA-256 digests. It installs **only** the five paths above, executes static and existing regression QA, and never pushes to GitHub.

## Two-step QA

1. `python3 -B apply.py`: verifies source, installs exactly five file changes on `stable-prep`, runs static contracts and prior regressions. No native MySQL, Apache, or active CMS used.
2. `python3 -B qa_native_first_install.py`: copies the **approved F4c.7 Git commit** into a new private `/tmp/bp-f4c7-site-<token>` tree, overlays **only** the three reviewed F4c.8 changes, spins up a fresh `/tmp/bp-f4c8-mysql-*` MySQL 9.5.0 server (`--skip-networking`, no binlog), and calls the genuine `FirstInstall::install()` without reflection or shortcuts. The F4c.7 private naming in the copied HTTP router is intentionally retained from the previously tested isolation harness; no existing F4c.7 test directory is touched.

Native QA must verify a previously empty database, private `.env`, the eight migrations with matching SHA-256, `page_access` columns/PK/FK, a prepared hashed-password write inside a rolled-back transaction, nonempty DB rejection, duplicate installation rejection, real `MigrationRunner::status()`, and actual administrator login on a private `127.0.0.1` HTTP server.

Private servers are stopped after the run. Only when every check passes and shutdown succeeds are *this invocation's* private directories removed. On failure, only this invocation's evidence remains for inspection. Do not automatically remove previous F4c.7 R1 evidence.

## Scope exclusions and safety

- No active `~/Sites/boilerplate` changes, production `.env` access, shared Homebrew MySQL service, MySQL production schema, external hosting, user media, or Apache changes.
- No change to general website routing, Core UI, migrations, backup/restore, update recovery locks, SQL execution through browser, or any public update channel.
- No Git commit, push, tag, or Beta Release until explicit user review/approval.
- This is **fresh-install baseline alignment only**, not a promise that every installer UI/hosting combination is verified; a separate browser wizard and external hosting test are still required before the first Stable Release.
