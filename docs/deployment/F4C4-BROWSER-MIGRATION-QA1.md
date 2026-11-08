# F4c.4 — Browser Migration Executor, isolated rehearsal QA1

**Pinned base:** `stable-prep` at `723d28eee7102ec809990782e8cf8dfd14b2880e` (F4c.3).\
**Status:** QA-only, NOT a production migration executor. `install_ready=false` always.\
**Installed files:** one QA-only Core class, one synthetic PHP QA test, this document.\
**No change** to `MigrationRunner`, `Db`, `DbConf`, `AppConfig`, Apache, `public_html`, the real Tools → Updates controller or code-only `UpdateOneClickInstaller`.

## Verified architectural constraints

- `MigrationRunner::apply()` remains CLI-only and operates against `DbConf::name()`. Its executed MySQL DDL can implicitly commit before `schema_migrations` is updated. **Rerunning a migration after an unacknowledged crash is unsafe.**
- F4c.2's `UpdateDurableRecoveryGate` rejects ordinary CMS HTTP and cooperative CLI writes whenever a `.db-update-pending-<32 hex>` marker exists. It does not currently authorize a browser database migration POST, and no such exception is added here.
- F4c.3 proves isolated restore and *synthetic* database-pointer cutover only. It does not change `DB_NAME`, provision a production recovery target, or establish a production-safe rollback contract.
- The current One-Click installer correctly refuses release packages with new or modified migrations. **This guard remains in force.**

## What QA1 implements

`UpdateBrowserMigrationRehearsal` provides a small, persistent state machine restricted to a direct, owner-only `/tmp/bp-f4c4-browser-<16 hex>` fixture. Only PHP CLI and PHP's loopback built-in test server may instantiate it. No method connects to MySQL, runs SQL/PHP migrations, activates a production database, unlocks recovery, or reads `.env`.

The **test-only HTTP router** ships *inside the downloadable QA ZIP*, not in the CMS repository. The Python harness copies it into a private `/tmp` test directory and runs a disposable PHP server bound exclusively to `127.0.0.1`. This router uses disposable test credentials, CSRF token and Origin checks; these **are not** actual CMS administrator authentication/CSRF and are never presented as such.

1. The QA caller supplies a static synthetic migration plan (source IDs and SHA-256 hashes) and simulated evidence flags (`release_verified`, `backup_verified`, `quiescence_verified`). These flags demonstrate control-flow *requirements*, **not proof** of an actual backup or release.
2. `begin()` writes a `.db-update-pending-<32 hex>` journal in a private recovery vault **before** creating synthetic database state. The existing F4c.2 gate recognizes that journal as `database_recovery`.
3. Each authenticated test POST processes **one** synthetic migration. It persists `phase=inflight` and migration identity *before* writing an atomic synthetic database effect. It records a durable `step_applied` checkpoint only after the effect.
4. A crash at either side of the synthetic effect leaves the journal `inflight`; a fresh PHP server reports `manual_recovery`, blocks POST retry, and never pretends the migration succeeded.
5. After all synthetic steps, `verify()` compares a QA history hash and marks `verified_qa_only`. The recovery journal is **not deleted**, and the result remains `blocked=true`, `install_ready=false`.

All filesystem writes are restricted to private synthetic `/tmp` fixture directories; no user CMS files or database contents are touched. The test harness sends `SIGKILL` **only** to its own child PHP test server and verifies persistence after restarting a new child process. The test detects process-kill behavior, **not power-loss/fsync-on-disk guarantees**.

## Test coverage

- PHP synthetic tests: validation of private paths, plan IDs and checksums, duplicate start, missing backup/release/quiescence flags, one-step-at-a-time execution, checksum review, corruption, journal identity, no production release/unlock APIs and abort-before/after-effect. 51 PASS in the development fixture.
- Loopback HTTP tests: authenticated synthetic GET/POST, test-only CSRF and Origin checks, denied methods/actions, deterministic step execution, restart after successful QA and hard `SIGKILL` before and after effects, no auto-resume, corrupt journal. 34 behavioral PASS in the development fixture; the packaged installer adds 7 source/tool preflight checks (41 expected total).
- Regressions to run on the user's complete local `stable-prep` tree: F4c.2 51 PASS, One-Click File Install 34 PASS, Core QA 39 PASS, Code Audit 173 PASS.

## Explicit remaining work before a real browser executor

1. **Real authorization and routing:** protected Core Tools controller with `tools.manage`, real session/CSRF and a narrow recovery-action exception in F4c.2; no unrestricted bypass. Investigate PHP server request timeouts and worker concurrency.
2. **Trusted release and backup evidence:** official authenticated release manifest, exact migration checksums, live verified point-in-time backup of both DB and media, and a durable ownership-bound job token. Do not accept client-supplied SQL, URLs or migration plans.
3. **Real SQL transaction model:** cross-request migration lease, MySQL advisory lock and cooperative/external writers, handling irreversible DDL and its registry separately. Unknown DDL completion must require independent inventory or explicit restore, not replay.
4. **Database recovery and activation:** restore candidate into a separately provisioned, empty schema, verify inventory and trigger compatibility on the hosting provider, resolve live DB identity precedence/environment overrides, and only then design a production database cutover. F4c.3's QA-only pointer must **not** be wired to `DbConf`.
5. **Hosting fallback:** if a shared webhotel cannot provision a second database, cannot create triggers, or denies safe cutover, refuse browser DB updates and offer manual recovery. Never overwrite an unknown live schema.
6. **Full staging:** real Apache/PHP-FPM, real administrator login/CSRF, file+database integration, crash windows, power-loss durability review, and an external-host test before any release.

**No Git commit, push, live CMS deployment, production schema change or database-release enablement is performed by this QA1 package.**
