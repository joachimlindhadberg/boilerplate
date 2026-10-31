# Boilerplate CMS 2.5.0 QA1 — Complete Source Cleanup Candidate (31 Oct 2026)

**Status:** source-reviewed candidate, not a final public stable release, not a production upgrade package. `Opdater nu` remains disabled.

## What this package does

- Based on the complete user-supplied `boilerplate(7).zip`; the source was sanitized before any code was edited.
- Removes the legacy hidden manual ZIP upload handling from `public_html/admin/tools.php`.
- Removes the no-longer-used manual upload review partial and its obsolete dropzone-specific regression test.
- Keeps the internal `UpdatePackageReview` validation service for the official GitHub release-review workflow.
- Distinguishes developer-only F3 rehearsal classes/CLI tools from runtime files in the production package builder.
- Keeps all essential CMS components, templates, SCSS and matching compiled CSS, forms, gallery, Page Builder, database schema and migrations.
- Includes the standard GPLv3 LICENSE from the same licensing family already declared for the official repository; third-party asset license audit still required.

## Important exclusions / privacy

Never publish `.env`, site-specific storage, logs, media uploads, backups, credentials, caches, private test fixtures or macOS ZIP metadata. The submitted ZIP included a live-looking `.env` and runtime files; these were not imported into the source tree or either generated artifact.

## Use

- **DEV**: a complete clean checkout for developers, including synthetic tests and the packaging tools. Work from this tree; do not replace the live site's private files.
- **DISTRIBUTION**: a complete file set for a **fresh, isolated test installation**, not an incremental upgrade. The document root must be `public_html/`. The web installer still requires an empty database and private source directory.
- **Existing installation**: do not overwrite `~/Sites/boilerplate` or apply this ZIP as an update. A dedicated updater with verification and rollback remains unfinished.

## Release gate

Source syntax/audits/package verification are useful but are **not** browser-level or production hosting certification. Full end-to-end installation and update/rollback require independent testing on the target PHP/MySQL host. Maintain the internal software version at `2.5.0` until official stable release approval.


## Follow-up QA1 implementation — 31 October 2026

A newer experimental **file-only** installer and Update now controller have since been added in `ONE-CLICK-UPDATE-FILE-ONLY-QA1-2026-10-31.md`. The prior statement that Update now was disabled documents the original cleanup snapshot. The new candidate remains unapproved for production or database-migration upgrades.
