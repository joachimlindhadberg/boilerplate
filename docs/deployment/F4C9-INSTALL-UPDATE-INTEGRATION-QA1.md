# F4c.9 — Installation & Update Integration Regression QA1

Approved source: `stable-prep` commit `fe30c7d47cecc06bbe7c21b437ce6cb3e1ab86ed` (F4c.8).

## Goal

Verify that the genuine first-install baseline, migration registry, CMS sign-in, role-based Update access, one-click *file-only* regression, durable database recovery guard, restart persistence and session revocation behave consistently together. This is QA, **not a new production database update implementation**.

## Repository changes

Two **new** development-only files: this document and `tests/installation-update-integration-f4c9-qa1.php`. No existing CMS class, admin interface, SQL schema, migration, installer, update code, media or `.env` is modified. The private Python/PHP HTTP/MySQL fixtures are supplied in the external ZIP only and never copied into `public_html`.

## Step 1 — static / established regression

The ZIP `apply.py` refuses any working tree changes or unexpected branch, remote, commit or pinned Core blob. It verifies SHA-256 manifests, creates only the two new QA files and runs the read-only F4c.9 contract tests, F4c.8–F4c.2 regression suite, existing file-only One-Click regression, Core QA and code audit. No MySQL process starts in this step.

## Step 2 — real private integration

`qa_native_integration.py` (only after step 1 PASS) validates the clean F4c.8 commit and exact two new QA files; starts a new private socket-only MySQL 9.5.0 (`--skip-networking`, `--skip-log-bin`) in `/tmp`; checks server identity; `git archive`s the exact approved commit into a private source directory and calls **real `FirstInstall::install()`** through the already-tested F4c.8 fixture. It independently checks all eight migrations, the `page_access` table and triggers. Then it adds an editor through the real Users repository (private test only), boots a private loopback PHP HTTP server, and tests:

- Authenticated admin can read the normal Updates page; unauthenticated user and editor cannot.
- Privately seeded recovery marker blocks frontend, denies install POST **before CMS bootstrap**, denies file-only restore during database recovery, and permits only protected read-only admin status.
- The persisted marker and critical migration inventory remain unchanged across HTTP requests and PHP-server restart.
- SQL-backed session revocation removes the admin's recovery access without clearing the database block.
- The private `.env`, migration checksums, user count, page_access inventory and triggers remain unchanged during blocked HTTP requests. No update is applied.

The private test PHP server and MySQL process are terminated. This invocation's private directories are deleted **only on total success** and a confirmed shutdown. Failures retain private evidence (owner-only permissions); investigate before retrying. Other test directories, including the older F4c.7 R1 failed attempt, are not touched.

## Explicit non-goals

- This is **not** an actual browser-driven file update installation or a database migration/execution through the admin UI; that path remains disabled. The file update engine is exercised in isolated synthetic CLI regression fixtures, **not installed over the live CMS**.
- Does not perform full disaster restore or filesystem/database cutover on an actual production installation. Faults remain tested in prior isolated rehearsals.
- Does not test the full installer wizard in Apache, hosted environments, concurrent requests, HTTPS, hosting migration, backup transport, persistent deployment, release publishing or Simply.com.
- Does not touch `~/Sites/boilerplate`, system MySQL, system Apache, remote hosting, active uploads or GitHub. No commit/push until explicit owner approval, and no tags/releases.

## Approval criteria

1. Static & prior regressions pass, no file drift.
2. Real private installation, admin/editor browser gate, recovery persistence and SQL snapshots pass.
3. Final source audit verifies exactly two new files unchanged from ZIP; explicit approval then local commit/push to `stable-prep`.
