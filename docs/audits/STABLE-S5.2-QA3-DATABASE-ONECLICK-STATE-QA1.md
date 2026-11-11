# Boilerplate CMS — S5.2 QA3: Database-Aware One-Click State & Recovery Contract

**Scope:** isolated integration foundation, not public update enablement.
**Approved source base:** `stable-prep` at `28e795c907cf18a428381aa88a1c4f43d749b9b8`.
**Reference:** S5.2 QA1 source/ZIP comparison and QA2/QA2a real isolated MySQL migration + SQL restore.

## Why a separate state contract is required

The existing `UpdateOneClickInstaller` is explicitly *file-only*. Its migration gate rejects any new migrations before installation. The code-recovery service retains verified site backup archives, but intentionally records `approved_for_automatic_update=false` and `requires_restore_rehearsal=true`. The durable HTTP gate already recognizes `.db-update-pending-*` and `.db-update-blocked-*` recovery markers and blocks file-only restore actions for them.

A completed isolated Beta 9 database migration demonstrates that the SQL can run and that the original database can be restored on the tested local MySQL environment. It **does not prove** a browser update has an authorized, durable, recoverable transaction spanning filesystem and MySQL DDL. MySQL DDL cannot generally be rolled back by `ROLLBACK`.

## QA3 change set (no activation)

- `core/classes/UpdateDatabaseOneClickState.php`: pure, deterministic transition and proof contract; never reads or writes a database, file, or HTTP request.
- `tests/update-database-one-click-state-s52qa3.php`: fault injection at every transition; verifies the **real** `UpdateDurableRecoveryGate` on a disposable filesystem fixture.
- `docs/audits/STABLE-S5.2-QA3-DATABASE-ONECLICK-STATE-QA1.md`: this design and approval boundary.

The public update route, `UpdateOneClickInstaller`, migrations, SiteBackup, recovery archives, DB credentials, UI and active CMS are **not modified** by QA3. The new class is not referenced by public HTTP actions. It does not authorize new migrations. A future, independently tested executor must integrate the contract and provide real proofs.

## Required phases for database-aware execution

1. Verify the exact official release/ZIP and unchanged historical migration checksums.
2. Acquire exclusive writer gate, verify quiescence and fully rehearsed restore (both code and database).
3. Persist and durably sync a private DB-recovery journal **before** the first file or SQL mutation.
4. Stage/apply files with a complete old/new hash plan, then execute only verified new migrations.
5. Verify source version, all migration checksums, existing data, new schema and recovery integrity.
6. Commit the journal durably and only then clear website maintenance; retain verified recovery material.

An exception, crash or timeout **after journal durability** must retain a DB-recovery marker. Public HTTP stays in the recovery admission state; the file-only recovery POST must not handle it. Only independently verified restoration of **both code and database** can clear the interruption. A partial restore is not success. Failures before the durable journal must leave code/DB unchanged.

## Explicit blockers before a public database-aware One-Click update

- No production-safe PHP-native database restore executor has been approved for shared hosts.
- A backup/archive integrity check alone is not proof of a successful restore.
- No end-to-end HTTP/CSRF/authenticated installation, fault/restart or DB-restore integration is yet approved.
- No proof that external database writers are quiesced by the CMS maintenance gate.
- No authorization to bypass `new_migrations !== 0` in the existing file-only installer.
- The legal/asset distribution sign-off for Stable remains separately open.

## QA3 PASS criterion

The source repo and Stable candidate must match approved hashes; the new three-file addition must be the *only* local change. PHP syntax, state invariants, negative tests, actual recovery admission fixture, Core CLI QA, and code audit must all pass. No live CMS or existing private MySQL instance should be started, altered, or connected.

**Result semantics:** QA3 PASS = safe, non-executing state/recovery contract, not Beta 9 -> Stable browser One-Click availability. Next: real isolated executor and crash/restore proof, then separately authorized browser wiring.
