# S1 — Stable Production ZIP Cleanup QA1

**Development base:** `stable-prep` at `2a336195e4194829b29358c26ee545c0c30a2a26` (approved F4c.10).

## Purpose and precise changes

Exclude five independently reviewed development-only / isolated-QA recovery classes and nine internal QA-only deployment notes from public runtime ZIPs. Preserve their files **in the development repository**, including test code and historical evidence. Do not modify any executable runtime CMS PHP, existing SQL, migration, installer, admin UI, data, or uploaded media.

Only these paths change in the development checkout:

- `tests/release/production_zip.py` — extend the development-only exclusion inventory and enforce it in `omitted()` and the existing bundled-PHP reference scan.
- `tests/release/test_production_zip_stable_prep.py` — assert excluded classes and QA notes are not packaged; assert necessary live updater, recovery gate, and maintenance classes remain; reject attempts to reintroduce QA-only contents.
- `docs/deployment/STABLE-S1-PRODUCTION-ZIP-CLEANUP-QA1.md` — this document, also excluded from production ZIPs.

## Excluded QA-only classes

- `UpdateBrowserMigrationRehearsal`
- `UpdateBrowserRecoveryAuthorizationQa`
- `UpdateDatabaseCutoverRehearsal`
- `UpdateDatabaseIsolatedRestore`
- `UpdateNativeMigrationRehearsal`

No production route should load these private rehearsal classes. The builder rejects any bundled PHP referencing their names. Runtime components such as `UpdateOneClickInstaller`, `UpdateDurableRecoveryGate`, `UpdateMaintenanceGate`, `FirstInstall`, `MigrationRunner`, and the update/recovery services are **not** excluded.

## Excluded internal notes

The eight `docs/deployment/F4C3-...QA1.md` through `F4C10-...QA1.md` notes, plus this `STABLE-S1` record, remain in development only. End-user installation, hosting, backup, release publishing, and update documentation remain included; their version and content updates belong to S2.

## Acceptance evidence / non-goals

Run the development-only production ZIP regression (builds, verifies and discards a private candidate), the Core QA, Code Audit, and existing F4c.10 regression. Confirm source hashes, exactly two modified release-tool files and one new QA document, Git HEAD still F4c.10 and GitHub branch unchanged. Only after owner approval may this scoped change be committed/pushed to `stable-prep`; no Beta Release or tags.

This phase does **not** certify publication, an actual upgrade, SQL migration, shared-host restore, HTTP security or final Stable release readiness. Version stays `2.5.1-beta.9`. The public ZIP remains a **release candidate** until S2–S5 pass. The active CMS and Simply.com installation are never modified.
