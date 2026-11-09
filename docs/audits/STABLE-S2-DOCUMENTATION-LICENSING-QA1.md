# Stable S2 — Documentation & Licensing QA1

## Approved source

Pinned to `stable-prep` S1 SHA `65a5498fc2623b90145f69532f0c6b969cd23a01`. This is a **documentation-only** development patch. No version bump, production release, new migration, runtime route or hosting changes are authorized.

## Exact changes

- Update existing `README.md` to retain the actual `2.5.1-beta.9` preview status while acknowledging the maintainer-reported Simply.com fresh-install smoke test without claiming live-update certification.
- Correct historical headings and runtime support caveats in `docs/deployment/INSTALLATION.md`, `HOSTING-REQUIREMENTS.md` and `UPDATING.md`; keep browser installation and file-only updater boundaries explicit.
- Reconcile bundled Forms and Gallery README version/minimum requirements with their untouched `module.json` manifests. Clearly label previous module release notes historical.
- Add `docs/deployment/LICENSE-AND-ASSETS.md` to inventory GPLv3, bundled icons/brand files, lack of tracked font binaries, and **outstanding third-party copyright/provenance sign-off**.
- Keep this internal audit under `docs/audits/`, which the S1 production ZIP builder already excludes.

## Evidence and limits

- GitHub tree and source files independently reviewed at S1. LICENSE contains GPLv3 text; licensing review is **not** final legal clearance.
- Source `core/classes/FirstInstall.php` validates a minimum administrator password length of 12 characters.
- Core requirement is PHP 8.3.0+; native local tests used PHP 8.5.12 and MySQL 9.5.0. No blanket compatibility claim for MySQL 8.4, MariaDB, all Simply.com packages or public recovery.
- Existing One-Click Update remains file-only; database-migration releases are refused. No production database recovery unlock is introduced.
- QA verifies patch pins, documentation structure/link targets, version consistency, public ZIP inclusion of license and user-facing docs, exclusion of internal audit, S1 regression and Core audits. It does not perform a fresh external webhost install or prove third-party copyright ownership.

## Acceptance

S2 may be approved for Git only after the local installer and full final source audit pass. Never commit/push before owner approval. An approved S2 commit is **not** public Stable release authorization. Outstanding third-party provenance remains a separate S3/S4 release gate.
