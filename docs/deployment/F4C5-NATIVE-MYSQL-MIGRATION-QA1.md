# F4c.5 — Native MySQL Migration Execution (isolated QA1)

**Approved base:** `stable-prep` at `7f783380dae17fbb571df0129c881ec992ea8abb` (F4c.4).
**Status:** Isolated native QA only. `install_ready=false`. No live browser or production database integration.

## Objective

Prove that the F4c.4 durable migration-checkpoint approach remains fail-closed when a **real MySQL 9.5 DDL statement** executes and commits independently of PHP, using a full verified CMS backup and a dedicated isolated server. The existing `MigrationRunner` remains CLI-only. The One-Click Installer still rejects releases containing migrations.

## Scope and isolation

- Adds one QA-only PHP class, one synthetic regression test, and this document to `stable-prep`.
- The native harness remains inside the distributed QA ZIP; no native harness is deployed into `public_html` or the active CMS.
- Native QA starts a fresh MySQL 9.5.0 server with an owner-only datadir `/tmp/bp-f4c5-native-<16 hex>`, Unix socket only, `--skip-networking`, binary logging disabled, and a newly secured root account.
- It reads pinned CMS source but never reads `.env`, `DbConf`, `DB_NAME`, active uploads, or existing service sockets. It has no permission to restart Homebrew MySQL or edit Apache.
- Every test restores the **real SiteBackup** synthetic baseline (26 tables, 9 triggers, 8 migration rows, 27 data rows, 22 foreign keys) into a new empty F4b.1 schema with restricted credentials.
- Every test gets a fresh direct owner-only `/tmp/bp-f4c5-run-<16 hex>` recovery journal root. F4c.2 detects the `.db-update-pending-...` file as a persistent **database recovery block**.
- SQL adapter verifies the exact 9.5.0 server version, Unix socket path, private data directory, disabled networking and binary logging, selected isolated schema, and restricted SQL user **before each migration**.

## Allowed real SQL

The native adapter can issue exactly these two *hardcoded QA-only* DDL statements, one per CLI invocation:

1. `ALTER TABLE pages ADD COLUMN f4c5_qa_flag TINYINT UNSIGNED NOT NULL DEFAULT 0`
2. `CREATE TABLE f4c5_qa_marker (...)` containing two non-sensitive columns.

It cannot execute arbitrary SQL, run published PHP migrations, change migration history, drop a schema, or enable production database cutover. This tests native DDL semantics and durable checkpointing, **not** the safety of arbitrary third-party migration code.

## Durable contract

1. Confirm a private MySQL server and matching baseline. The separate harness independently verifies the backup content and inventory.
2. Create an owner-only `.db-update-pending-<32hex>` marker with `blocked=true` and `install_ready=false` **before any QA DDL**.
3. Under an exclusive fixture lock, independently check the real SQL inventory against the last committed journal step.
4. Persist and flush `phase=inflight` and the exact step index **before executing DDL**.
5. Execute one fixed SQL statement; query a fresh SQL inventory; only then persist `step_applied` and increment the completed count.
6. If PHP dies before or after MySQL DDL, a new process sees `inflight`, reports `manual_recovery`, refuses automatic replay, and retains the database recovery block.
7. On normal completion, independently verify the selected database inventory and store `verified_qa_only`. Never delete the recovery marker or report production readiness.

A durable PHP file and atomic rename protect against **process termination**, not necessarily complete host power loss; portable directory-level durability, simultaneous external DB writers, real shared hosting restrictions, and recovery unlock remain unproven.

## Native scenarios

| Mode | Native SQL behavior | Expected durable state |
| --- | --- | --- |
| normal | Both DDLs commit; independent SQL checks after each | `verified_qa_only`, blocked |
| sigkill-before | PHP killed after `inflight` but before DDL | `manual_recovery`, old SQL inventory, no retry |
| sigkill-after | PHP killed after committed DDL, before checkpoint completion | `manual_recovery`, changed SQL inventory, no retry |
| native-sql-error | Revoke ALTER on dedicated test account, causing real DDL privilege error | `manual_recovery`, old SQL inventory, no retry |

Only the test-owned PHP child receives SIGKILL; never the MySQL server or system services. The harness removes its private database/server and fixture directories **only after all tests succeed and private MySQL stops**. On failure it preserves its private evidence and attempts to shut down its own server. Never manually delete or reset an unverified recovery folder in production.

## Explicitly NOT approved

- No integration into production `Tools → Updates`, authentication, CSRF or Apache/FPM routing.
- No production SQL migrations, real `DbConf` cutover, real restoration activation or automatic journal unlock.
- No arbitrary migration PHP execution, background job orchestration, concurrency safety across noncooperating writers, or full power-loss proof.
- No install-ready decision and no GitHub release; commit/push require separate verification and user approval.

## Run order

Run the ZIP's checksum-pinned `apply.py` for static/synthetic/Core QA in the Git working copy. After it passes, run the ZIP's `qa_native_migrations.py` separately against private socket-only MySQL on the Mac. Preserve and inspect logs before any commit.
