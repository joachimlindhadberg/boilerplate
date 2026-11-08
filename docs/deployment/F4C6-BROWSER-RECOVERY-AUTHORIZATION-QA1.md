# F4c.6 — Browser Integration & Recovery Authorization, isolated QA1

**Pinned base:** `stable-prep` at `b9c302304adbd0a8c2a76355985590678de8ec2e` (approved F4c.5).
**Status:** Policy-only browser authorization rehearsal; `install_ready=false`; production SQL migration routes remain disabled.

## Objective and boundary

F4c.2's persistent recovery admission deliberately rejects **all DB-recovery POST** on real admin routes. F4c.4 simulated migration state transitions over loopback HTTP, and F4c.5 executed two hardcoded DDL statements **only in CLI** on a disposable socket-only MySQL server. None is a production browser migration controller.

This phase adds a deliberately disconnected, read-only policy component that tests a **future authorization contract** without weakening F4c.2. It never calls the F4c.5 SQL adapter, grants a production exception, or changes the existing update UI. Its successful result is `policy_review_only`, which is **not permission to execute a migration**.

## Files installed into the Git working copy

Exactly three new, QA-only files:

- `core/classes/UpdateBrowserRecoveryAuthorizationQa.php`
- `tests/update-browser-recovery-authorization-f4c6-qa1.php`
- `docs/deployment/F4C6-BROWSER-RECOVERY-AUTHORIZATION-QA1.md`

The private test HTTP router, synthetic-session seeder, and Python loopback harness remain **in the QA ZIP**, not in `public_html` or the Git repository. The installer never modifies production routes, `.env`, MySQL, Apache, PHP-FPM, releases or Git refs.

## Verified existing Core contracts

- `public_html/admin/incl/module-bootstrap.php` calls real `Auth()` after the normal CMS bootstrap and enforces `admin.access`, required `tools.manage`, and forced-password-change behavior.
- `Auth::can()` delegates to `CapabilityRegistry`; editors and ordinary users do not get `tools.manage`.
- `admin_verify_csrf()` compares a real session-scoped `admin_csrf` value via `hash_equals`.
- `AdminToolsRoute::resolve()` resolves canonical/danish Tools URLs and MultiViews conflicts.
- `UpdateMaintenanceGate` is a cooperative CMS admission lock, not an external MySQL transaction guarantee.
- `UpdateDurableRecoveryGate::permitsHttp()` permits the protected update **GET**, but no database recovery **POST**. F4c.6 does **not** modify or bypass that gate.

## F4c.6 strict QA policy

To produce `policy_review_only`, all conditions must hold: private direct owner-only `/tmp/bp-f4c6-browser-<16 hex>` QA root; PHP CLI or loopback CLI server only; exact private Tools script identity and canonical update path; valid current session; real Core `admin.access` and `tools.manage` capability evaluations; no forced password reset; a single private `.db-update-pending-<32 hex>` file with exact QA format and manual-recovery state; owner matching the authenticated QA session; a random 256-bit session run token matching the journal SHA-256 digest; an exact three-field POST (`action`, `csrf_token`, `run_token`); the existing Core session CSRF function; and exact loopback Host + Origin with a same-origin Fetch Metadata header if provided.

A successful decision always returns `blocked=true`, `install_ready=false`, and `can_execute_sql=false`. Authenticated GET returns read-only state only. Corrupt journals, changed owners, stale tokens, unexpected inputs, wrong methods, unsupported routes, unexpected headers and misconfigured paths fail closed. The PHP class only reads on-disk recovery evidence; never creates, repairs, removes or unlocks a marker.

**Critical:** even when F4c.6 returns `policy_review_only` in its private test fixture, the existing F4c.2 gate still denies the corresponding real DB-recovery POST. A future production exception would require a **separate design and approval**, after real authentication, backup, restore and locking prove safe.

## Test realism and limitations

- Static PHP policy QA uses actual `Auth::check`, `Auth::can`, `CapabilityRegistry`, `AdminToolsRoute`, `UpdateDurableRecoveryGate` and `admin_verify_csrf`, but a test subclass omits `Auth::__construct()` to avoid touching a live SQL-backed session registry.
- HTTP harness runs the PHP built-in server on `127.0.0.1`, uses the **real Core helper functions** and signed-off policy class, with **synthetic** session records created in an owner-only disposable directory. This is **not** a genuine administrator login or a production Apache/FPM request. The separate private router simulates `SCRIPT_FILENAME` mapping to a dummy Tools file. It is never installed.
- HTTP QA tests authorization/CSRF/Origin/method/body, restarts the PHP test server, verifies the DB blocker remained byte-identical, and cleans up only its own private fixture after success. Failure preserves the private evidence and attempts to stop its PHP test server.
- No real MySQL, dynamic migration PHP, real backup, host power-loss, external writers, shared-host compatibility, or real browser administrator UI is covered in F4c.6 QA1.
- A correct F4c.6 result does not prove F4c.5 can safely execute SQL from the browser. F4c.5 remains CLI-only and production cutover is still unavailable.

## Before a production browser migration can be considered

1. Decide how the pre-bootstrap recovery gate can admit **only a named, authenticated, owner-bound recovery capability** without reading the CMS DB before bootstrap. A naked POST action or CSRF token is not an adequate bypass.
2. Design cross-request durable authorization, freshness/expiry/revocation, replay protection, a strict job owner, and a maintenance lease surviving PHP request boundaries.
3. Bind verified official release manifests, migration source hashes, real DB+media backup, isolation/quiescence proof, and restore candidate identity to the job **server-side**, never to user POST fields.
4. Define an explicit manual-recovery procedure for ambiguous DDL, database restoration/cutover and shared hosting without a second schema or requisite privileges.
5. Test real registered administrator login, CSRF rotation, 401/403/503 behavior, Apache MultiViews, FPM timeouts and worker concurrency; then isolated MySQL native fault windows and external hosting.
6. Only then consider an explicitly reviewed Code/SQL integration into `Tools → Updates` or new Core recovery endpoints. Do not weaken the current F4c.2 gate opportunistically.

## Run order

1. Verify the complete ZIP SHA-256 and use `apply.py` to install **only the three new files** into a clean, pinned `stable-prep` checkout. It runs private policy, HTTP and existing regression tests.
2. Inspect the Terminal QA log. No commit, push, releases or tags are created.
3. On explicit user approval, verify exact staged files and produce a separate local Git commit, then a separately controlled push to `stable-prep`.
