# F4c.7 — Real CMS Authentication & Recovery Integration QA1

**Pinned base:** `stable-prep` at `60168ec29a1206118da67a66af48b0d1180a8ef9` (approved F4c.6).
**Scope:** Completely isolated CMS source clone and QA-only test baseline + dedicated MySQL 9.5.0 server + PHP loopback HTTP test. **NO production DB migration**, no release, no recovery unlock.

## Objective

Close F4c.6's simulated-admin-session limitation by exercising **actual** Boilerplate CMS `Auth`, `SessionManager`, `Users`, `Db`, login form, `public_html/admin/index.php`, `public_html/admin/tools.php`, `module-bootstrap.php`, session-CSRF and F4c.2 `UpdateDurableRecoveryGate` over HTTP with a genuine SQL-backed session. This is a **test-only** integration, not a deployed recovery action.

## Source changes

This package adds only two QA files to the `stable-prep` working copy:

- `tests/update-real-auth-recovery-f4c7-qa1.php` — static/isolated Core authorization and recovery regression.
- `docs/deployment/F4C7-REAL-CMS-AUTH-RECOVERY-QA1.md` — this audit/QA record.

The test harness, private database first-install helper and local HTTP router live **only inside the downloadable ZIP**, never under `public_html` and never in a public release. Nothing is connected to the live Tools controller. Existing F4c.6 and One-Click update guards remain unchanged.

## Isolation

1. Installer refuses a non-clean Git working tree, wrong branch, mismatched remote/commit or changed pinned dependencies. After passing checks it adds only the two files above; it does **not** commit or push.
2. Native integration test creates private owner-only folders **directly under `/tmp`** (private MySQL datadir and a separate CMS copy). The CMS copy is built using `git archive` of the **approved F4c.6 commit**—not copied from any installed CMS. No installed `.env` or uploads are read.
3. It invokes `mysqld --no-defaults --initialize-insecure` only for the newly allocated private datadir, then starts only that new MySQL process with `--skip-networking`, `--skip-log-bin`, private Unix socket, private process log and X Plugin disabled. It checks server identity before every HTTP dispatch and never connects to Homebrew MySQL's normal socket/service.
4. It creates a restricted fixture database user bound to one randomly named test schema, sets a random root password, and uses private 0600 client option files. There is no real customer data or credential reuse.
5. A **QA-only private baseline provisioner** calls the unmodified `FirstInstall` internal `createSchema`, `createTriggers` and `seed` methods through PHP reflection to initialize the actual CMS schema, nine triggers and test administrator. This is necessary because the current `FirstInstall::stampMigrations()` expects seven migrations but the approved source contains eight. **`FirstInstall::install()` is not executed or claimed to work**, and no migration history is falsely stamped. The real `Users` repository creates one editor. Passwords and DB credentials are random test secrets written only in private owner-only files; neither appears in logs or commands.
6. A standalone `php -S` child listens on `127.0.0.1` only. The **test-only router is outside the webroot**, routes a strict allowlist to actual CMS entry scripts, and verifies the exact private MySQL socket/datadir/schema before loading any CMS bootstrap. All other paths are 404.
7. Normal `Auth::login`, real database `auth_sessions` binding, session cookies, `admin_verify_csrf`, login rate limiter, and current user capability checks are exercised. An explicit `.db-update-pending-<32 hex>` is then written **only** under the cloned CMS recovery vault.
8. Failed login-CSRF, editor/anonymous denial, admin recovery redirect, read-only update status, forbidden file restore/install POST, frontend 503, PHP server restart, SQL session revocation and persistent marker immutability are checked.
9. Child servers are stopped before deleting **only the folders just created by the test**. On any failure, private evidence is retained for inspection and the harness attempts to shut down both of its child servers. Never delete an unverified recovery journal from a real CMS.

## Important boundaries

- This checks *real* CMS auth and Core controllers **against an isolated freshly installed CMS**, not the user's live installation or a public host. It uses the PHP built-in loopback server, not Homebrew Apache/PHP-FPM; a later staging gate must test those separately.
- The `qa_real_router.php` shim is not production routing, not installed into the repo and not a bypass of the real durable recovery gate. `UpdateDurableRecoveryGate::guardHttp()` still runs from the real `init.php`.
- The native fixture **does not apply or stamp a CMS database migration**. The missing `page_access` migration remains a known untested schema difference from a fully updated site. It does not test non-cooperative external writers, host power loss, shared-host database cutover, recovery unlock, cross-version release deployment, or actual production database rollback.
- Only the private MySQL child is stopped. No Apache service, Brew service, existing MySQL server, real CMS, existing `.env`, GitHub tag, release, commit or push is changed.
- Test prerequisites: local MySQL 9.5.0 command-line server/client/admin utilities and PHP 8.3+ CLI with mysqli. If absent, this test must **STOP**, not switch to any existing MySQL service.
- **Do not run the native harness if the test folders cannot be exclusively owned by the current user.** Its only destructive operations are on newly created test fixtures.

## Acceptance sequence

1. Verify the downloadable ZIP by the SHA-256 printed by the assistant and run `python3 -B apply.py` after extraction. This adds two QA files and performs static + Core regression checks without MySQL.
2. Inspect the output and confirm only the two expected paths are untracked. Do not commit yet.
3. In a separate explicitly authorized Terminal step run `python3 -B qa_real_cms_http.py` from the **same checksum-verified extracted package** to test a fresh dedicated MySQL and actual CMS browser flow. If it fails, preserve the evidence and stop.
4. After native QA passes, perform final source checksum and Git-status audit, ask for explicit user approval, and only then commit/push to `stable-prep`. No release/tags.

**KNOWN BLOCKER:** The original `FirstInstall::stampMigrations()` migration allowlist excludes tracked `database/updates/20261105_000_page_access.php`. The real fresh installer must be audited and repaired as a separate approved source change before stable release; F4c.7 does not mask or modify it.

**QA status:** Packaged and syntax-checked in the assistant environment; **native MySQL/real CMS HTTP execution must still be run on the user's Mac and inspected before acceptance.**
