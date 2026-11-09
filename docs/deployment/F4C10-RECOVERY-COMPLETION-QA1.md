# F4c.10 — Recovery Completion & Safe Re-Admission QA1

## Approved development base

`stable-prep` commit `a9f24fef61c88428bc1d09c6b6ae2f425d88e812` (F4c.9). This phase is a **private integration and safety-boundary rehearsal**, not an implementation of production database recovery or a browser UI to clear journals.

## Exactly three QA-only repository additions

- `tests/recovery-completion-engine-f4c10-qa1.php` — a CLI-only, strictly `/tmp`-scoped completion state machine, requiring an owner-only private QA sentinel, private recovery vault, the existing Core maintenance exclusive barrier, and independent SQL-inventory observation.
- `tests/recovery-completion-f4c10-qa1.php` — private synthetic tests for unauthorized/repeated actions, SQL/config drift, competing markers, persistence across PHP objects, and interruption immediately before the completion rename.
- This document.

**No** existing production PHP, SQL, web entrypoint, updater, schema, migration or UI is edited. The private HTTP/MySQL runner and PHP fixture are available **only inside the verified external QA ZIP** and are never installed in `public_html`.

## Completion contract under test

1. A marker `.db-update-pending-<32 hex>` is created inside a private CMS copy, BEFORE mutating test data. The genuine `UpdateDurableRecoveryGate` must return `database_recovery`.
2. The test-owner token and owner ID are checked, and a separately observed native MySQL inventory must equal the original isolated baseline; the private `.env` and Core installation source must retain their exact SHA-256 values.
3. The evidence is persisted as `verified_pending` **under the same pending marker name**. The real recovery gate **must remain blocked** after this step and after PHP restart.
4. Final completion requires a **new** independent SQL/config/source read and an exclusive `UpdateMaintenanceGate` lease. Any drift, unexpected marker, bad owner/token, altered proof, or replay fails closed.
5. The same private journal inode is atomically renamed to `.f4c10-completed-<id>`; the original Core gate ignores this private evidence name, yielding `clear`. The archived proof is retained 0600; no SQL or original CMS file is modified by the completion class.
6. Real HTTP is checked both before and after completion with the private PHP server. Native MySQL drift is injected only into the isolated `page_access` table, then explicitly reverted and independently re-queried. A process restart before rename must remain blocked; after rename, normal login/Updates must work without elevating editor access. A second completion attempt must fail.

The atomic rename protects the *process-interruption boundary*, not sudden power loss across unsynced directory metadata or arbitrary host filesystem semantics. In production we must additionally address fsync of directory, host permissions, web and non-web concurrent writers, original backup provenance, real restore/cutover, and owner-authenticated browser authorization before exposing any clearance path.

## Safety boundary and release blockers

- `UpdateRecoveryCompletionF4c10Qa` rejects all non-CLI use, real project directories, symlinks, non-private roots, absence of the private QA sentinel, and non-private journals.
- This code MUST NOT be included from CMS Core, web bootstrap, or Tools. **Do not copy the QA state machine into a live installation or manually rename/rewrite production `.db-update-*` markers.** Real database recovery remains blocked in the actual CMS.
- The isolated test token is **not** a substitute for Core Auth + CSRF + owner-bound recovery authority. The SQL observer is supplied by the fixture; production must implement a separately authenticated, non-spoofable verification source.
- The test uses real FirstInstall and native MySQL 9.5, but injects a reversible QA-only row mutation; it does **not** rehearse an actual production database backup restore, migration, host-level incident or restore on Simply.com.
- No `~/Sites/boilerplate`, active MySQL, Apache, remote host, real site backups, Git commits, release tags, or GitHub releases are modified.

## QA and approval sequence

1. Read-only pinned GitHub/source/package preflight, install exactly three new QA files and run the synthetic F4c.10 contracts plus F4c.9–F4c.2 regression tests, Core QA and code audit.
2. A separate native test creates new socket-only MySQL and private HTTP/CMS copies, verifies real first-install, auth/recovery block, tests dirty/clean SQL evidence, tests verified pending across PHP restart, atomic private clearance, new login, access control, and no replay.
3. Final source audit and **explicit owner approval** before committing/pushing only the three QA files to `stable-prep`; no beta tags or releases.
4. A separate future phase is required for the *production* completion mechanism, including real full backup restore verification, concurrency and durable authority. Do not treat this QA phase as authorization to enable automatic production SQL updates.
