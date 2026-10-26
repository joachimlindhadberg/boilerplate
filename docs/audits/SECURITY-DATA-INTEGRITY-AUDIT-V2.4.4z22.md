# Security & Data Integrity Audit — V2.4.4z22

**Base:** user-approved V2.4.4z21 / 2.4.4.107. **Candidate:** 2.4.4.108.  
**Scope:** source-only, non-mutating security and data-integrity review. **No `verify-fixture`, live MySQL writes, migrations, HTTP requests, backups or restores were run.**

This is a *bounded code audit*, not a penetration test or proof of production readiness. The automated checks inspect selected invariant source locations and execute only pure value-validation/capability probes. They do not prove that every code path, module, deployment or browser is secure. Important boundaries and follow-ups are recorded below.

## Priority findings / residual risks

| ID | Priority | Assessment | Evidence / risk | Required next action |
|---|---|---|---|---|
| SEC-01 | P1 testing boundary | **Open / not verified** | `verify-fixture` is intentionally **not executed** at the owner's request. Cross-module DB workflow behavior and transaction rollback cannot be attested by structural checks alone. | Do not run it on the real DB. Before production, use an isolated throwaway clone under separate credentials, or explicitly accept reduced coverage. This audit does not require doing so now. |
| SEC-02 | P1 safety clarity | **Mitigated for this release** | Existing `verify` includes `http-smoke.sh` GET requests. Although the script performs no explicit writes, the normal frontend bootstrap may run its legitimate scheduling fallback and modify normal CMS data. | For a **strict no-write** audit use `sh tests/integration/run.sh verify-security` only. Do not describe general `verify` as absolutely DB-write-free. |
| SEC-03 | P2 hardening | **Open / production review** | The existing admin and builder use some inline scripts, and trusted HTML content may contain scripts. No comprehensive Content Security Policy is established by the reviewed Apache defaults. This is *not proof* of an exploitable XSS vulnerability. | Plan a separate CSP/content-trust design and test it with browser coverage before enforcement; keep trusted editing permissions restricted. |
| SEC-04 | P1 readiness | **Open / not verified** | Backup creation has manifest/upload hashes and excludes `.env`, but end-to-end restore to a fresh isolated installation, checked table counts and attachment checksums have not been demonstrated here. | Validate restore in an isolated environment before production; never restore over the user's current CMS. |
| SEC-05 | P1 deployment | **Open / deployment-specific** | Production HTTPS, secrets, permissions, `.htaccess` effective Apache handling, cron, error-log privacy and MySQL permissions depend on the deployed environment; source inspection cannot guarantee they are effective. | Run the documented production checklist against a **staging** environment before V2.5.0 sign-off. |
| SEC-06 | P2 scalability | **Known limitation** | Scheduled Content caps combined records at 2,000 before filtering/pagination. With large datasets, records may be omitted from the screen. | Consider server-side filtering/pagination in a later, isolated feature release. |

No newly confirmed critical exploit was reproduced during this bounded audit. This does **not** certify the system free of vulnerabilities.

## Source-level coverage

- **Authentication / session security:** password verification, active status and `admin.access`, session ID renewal and strict secure cookie policy, remember-me token hashing, session revalidation, logout, DB-backed login throttle, unknown capability deny.
- **Authorization / forms:** core controllers' POST/CSRF gates, Forms and Gallery controller protection, module-route capability and CSRF dispatcher, immutable/typed request constraints, escaped output and safe redirect restrictions.
- **Media and integrity:** MIME/size/upload verification, guarded media deletion, transactional indexed Page Builder references, fail-closed reference-trigger expectations and read-only media integrity inspection.
- **Database / backup:** prepared DB operations, CLI-only trigger migration helper, backup manifest/hash and symlink checks, packaging rules excluding secrets/user data.
- **Web:** protected include/view paths, restriction of executable uploads, defensive headers, no-cache admin content and allowlisted module assets.
- **Negative behavior probes:** malformed array IDs, negative IDs, unknown enum/boolean, invalid dates, javascript URLs, protocol-relative/header injection URLs, HTML escaping and unauthorized roles/capabilities.

Each automated PASS means the named source condition or isolated pure-code probe passed. The status **does not mean** a complete authenticated HTTP+MySQL end-to-end flow has been tested.

## Safe commands: guaranteed no database / HTTP calls by the new audit

```sh
cd ~/Sites/boilerplate
sh tests/integration/run.sh verify-security
# Same check, machine-readable:
php core/cli/security-audit.php --json
```

`verify-security` invokes **only** `core/cli/security-audit.php`. The audit reads a fixed set of shipped source, configuration and documentation files; it never reads `.env`, connects to MySQL, calls an HTTP endpoint, loads the CMS bootstrap, creates a session, or modifies installation data. It intentionally leaves the legacy `verify`/`verify-code`/`verify-site` commands unchanged.

`verify-fixture` remains available for an *optional* fully isolated environment but is **not required for this release's local approval and must never target an ordinary CMS database**. No manual SQL, PHP migrations or module activation is needed for V2.4.4z22.

## For a future staged security verification (not part of this release)

1. Verify capability denial for unauthenticated/editor users, including malformed POST/CSRF against representative module endpoints.
2. Verify Apache rules against actual sensitive paths and executable upload extensions, without placing any files in the user's live uploads.
3. Review backup+restore on an empty isolated environment with checksum and row count comparisons.
4. Verify production HTTPS, log privacy, rate limiting under contention, error handling and session lifetime.
5. Resolve or explicitly accept each open finding above. Do not equate passing static tests with completion of production readiness.

**Release decision:** no runtime security or DB changes introduced. User's locally approved 2.4.4.107 remains rollback reference until the owner explicitly approves 2.4.4.108.
