# Boilerplate CMS V2.4.4z22 — Security & Data Integrity Audit

**Internal release:** `2.4.4.108`  
**Approved parent:** `V2.4.4z21 (2.4.4.107)`  
**Status:** local test candidate, awaiting explicit approval.

## Change scope

- Adds `core/cli/security-audit.php`: a standalone **source-only** audit, independent of CMS bootstrap and DB/HTTP. Includes static checks across auth, CSRF, capability, request validation, media, reference index, backup, Apache routing and packaging, with 10 pure negative behavior checks.
- Adds `sh tests/integration/run.sh verify-security`, a separate read-only command. Existing `verify`, `verify-code`, `verify-site` and `verify-fixture` semantics remain unchanged.
- Adds `tests/security-data-integrity-audit-v2_4_4z22.php`, a read-only regression contract for the dedicated command and source audit.
- Records prioritized scope/follow-ups in `docs/audits/SECURITY-DATA-INTEGRITY-AUDIT-V2.4.4z22.md`.
- Bumps only internal CMS version metadata to `2.4.4.108`.

**No CMS runtime change, UI change, database schema change, SQL migration, new module dependency or CSS change.** Existing Forms/Gallery/Page Builder and all approved UI behaviors are untouched.

## Important safety clarification

The owner declines the database-active synthetic `verify-fixture`, because their real database must not be put at risk. **That preference is respected.** This release does not run any database-active fixture, migrations or destructive operations. For strict source-only safety, run only:

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify-security
```

The older `verify` sends local HTTP GET requests that may indirectly trigger scheduled-content execution through the CMS frontend fallback. It should **not** be described as guaranteed no-write. This new security audit does not make any HTTP request or open a DB connection.

## QA boundaries

Source checks can validate documented guards and pure validation behavior, but not prove effective Apache/PHP production configuration, full route-level privilege enforcement in a live HTTP session, transactional MySQL behavior, complete media upload flow or the ability to restore a real backup. The detailed audit identifies these residuals rather than misrepresenting them as passed.

The candidate must pass the project's existing read-only code regression checks, source audit and release packaging checks. UI-audit stays at 0 open items (13 resolved + one accepted filter-button decision).
