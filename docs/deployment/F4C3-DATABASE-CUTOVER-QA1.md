# F4c.3 — Database Restore & Cutover (isolated QA1)

**Pinned base:** `stable-prep` commit `556c0625018d070528943cc80a8f205b660d0e4a`. **Scope:** private QA only. **Production database updates: disabled (`install_ready=false`).**

## Finding: actual CMS database identity

`DbConf::name()` resolves `DB_NAME` via `AppConfig::get`. Configuration has environment-variable precedence over `.env`. Every normal CMS `Db` instance uses these credentials and maintains a request-local mysqli handle. Replacing `.env` or a new file would **not** reliably switch all deployments (in particular where `DB_NAME` is injected by hosting or existing requests still have open connections). This release deliberately does **not** modify `DbConf`, `.env`, AppConfig, web routing or One-Click Update.

The approved F4b.1 restore engine is **CLI-only**, restricted to `boilerplate_f4b1_<16 hex>` disposable empty schemas, with a 32 MiB SQL limit. It cannot be used on a populated production database and must not be silently broadened.

## QA1 contract

`UpdateDatabaseCutoverRehearsal` is a CLI-only class with direct, owner-only `/tmp/bp-f4c3-cutover-<16 hex>` fixture root. It never connects to MySQL or reads CMS credentials; the native QA harness independently provides source/destination connections confined to its own socket-only MySQL instance. Its `active-target.json` is a **synthetic routing pointer**, not a live CMS setting. The class never clears `.db-update-pending-...` marker, which existing F4c.2 logic treats as a blocked database recovery. No production enabling method exists.

Sequence: (1) create verified source database backup (F4b.2/F4b.3 path); (2) create persistent **blocked** database-recovery marker and initial old-db pointer; (3) mutate isolated old schema; (4) restore full CMS baseline into a newly created, empty schema using the approved F4b.1 engine; (5) independently compare 26 tables, 9 triggers, 8 migrations, 27 rows, 22 FKs and canonical table/schema fingerprints; (6) mark candidate independently verified; (7) persist switch intent *before* atomically replacing synthetic pointer; (8) independently re-query selected MySQL database after switching; (9) keep journal BLOCKED, `install_ready=false` and old schema quarantined.

For an interruption after journal intent but before pointer replacement, a new process detects the **old** target and remains blocked. If interrupted after pointer replacement but before completion, a new process detects the **candidate** and still remains blocked. No automatic rollback/unlock is inferred from either state. An unknown pointer, multiple markers, missing metadata or wrong fingerprint fails closed. The native test sends `SIGKILL` only to a test-owned PHP child, never to a service or another process.

## Hosting and production blockers

1. **Second database provision:** many shared hosts limit a site to one schema and do not grant `CREATE DATABASE`. Without the ability to provision a second database via normal hosting facilities, **automatic cutover is unsupported**. Never delete or overwrite a populated database as a workaround.
2. **Trigger rights:** host-specific binary-log settings may block trigger creation for schema-scoped users. F4b.1b detects this and stops before import; do not change global MySQL flags to bypass.
3. **Routing authority:** a production database-selection mechanism must be designed and verified for `DB_NAME` environment precedence, request-local connection lifetime, FPM/opcache, CLI, installer, scheduler and extensions. A QA JSON pointer is not a production resolver.
4. **Durability and filesystem:** PHP fsyncs each private JSON file before same-directory rename, but **portable parent-directory fsync is not implemented**. Host-specific crash/rename durability must be tested before deployment.
5. **Restore capacity:** F4b.1's 32 MiB SQL limit, timeouts, managed media volume and 4 GiB archive guard limit eligibility. Large sites require streaming restore with independent QA.
6. **Consistency:** F4c.2 gates cooperative CMS traffic and CLI writers; unrelated SQL writers, external cron/hooks, mail workers and hosting processes are not blocked. Pre-update write quiescence and consistency of SQL with uploads are prerequisites.
7. **Runtime health:** cutover must check authenticated admin login, roles, page builder rendering, modules/forms/gallery/media and code/database version compatibility **before** permitting traffic, including failures after switching.
8. **Secrets and privacy:** recovery archives contain accounts/password hashes and submissions. Never move backups under webroot, leak `.env` or expose credentials via errors.
9. **Trust and release:** official release and migration verification remain mandatory. This new QA class confers neither migration execution authority nor release install readiness.

## Test and approval boundaries

`tests/update-database-cutover-f4c3-qa1.php` performs synthetic pointer/journal/policy tests without database access, and uses separate PHP processes to check restart reconstruction. `qa_native_cutover.py` additionally creates a fresh **private socket-only MySQL 9.5.0**, exports via `SiteBackup`, restores via `UpdateDatabaseIsolatedRestore`, verifies the real 26-table corpus and performs the three scenarios. The private server and its schemas are removed **only after all tests pass and shutdown is confirmed**. On failure the owned private fixture may remain for debugging. Neither test accesses the running CMS database or modifies Git.

Before **any** future production integration: design capability detection for second schema, add fail-closed production routing that supports hosting env precedence, controlled cutover and rollback (with durability tests), verify backups/media and MySQL trigger compatibility on external staging, then test authenticated Core UI, real Apache/FPM/login/CSRF, admin update and crash-restart end-to-end. Browser migration executor (F4c.4) and overall integration (F4c.5) are still separate.

**Decision:** F4c.3 QA1 verifies an isolated cutover primitive. Do **not** wire it into One-Click Update or publish it as a production recovery feature.
