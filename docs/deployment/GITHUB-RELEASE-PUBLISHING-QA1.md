# Official GitHub release workflow — proposed (QA1; no Stable release authorized)

Project: `joachimlindhadberg/boilerplate`. This document describes the future
publisher workflow, **not** a request to publish the current QA1 code.

1. Develop changes locally, keep existing F1a/F1b/F1c/F2a/F2b validations green,
   run static/security integration QA and validate the *full* release candidate
   on an isolated install and isolated database (including restore).
2. Decide the official target version once the whole CMS is release-ready.
   Update every authoritative version constant/manifest and documentation;
   verify the runtime ZIP and its tag agree exactly. The internal `2.5.0`
   is **not** a mandatory first public stable version.
3. Build one clean runtime distribution ZIP `Boilerplate-CMS-vX.Y.Z.zip`
   with the `boilerplate/` root, regular files and Unix mode metadata.
   Never include `.env`, credentials, `storage/`, uploads, tests, development
   scripts, Git files, Finder metadata, or local logs. Preserve required
   `.htaccess` files. Do not modify migration history already shipped.
4. Inspect the final artifact via `UpdatePackageReview` and a separate
   publisher-controlled SHA-256 check. Perform a fresh-install smoke test,
   isolated staging upgrade, database restore and rollback drill using the
   **exact same bytes** that will be uploaded.
5. Push the reviewed source changes to GitHub. Create a draft/prerelease for
   nonstable candidate testing; never use a stable tag solely to test the
   updater. After explicit final release approval, create a Stable GitHub
   Release with tag `vX.Y.Z` and upload that exact runtime ZIP as an asset.
6. Verify GitHub's published uploaded-asset digest and version metadata,
   then run the opt-in real F2b probe from an **older isolated** CMS install.
   Only after completing F3/F4 restore/deployment QA should users be offered
   an `Update now` action.
7. Publish release notes: features, fixes, migration/hosting minimums,
   compatibility, known limitations and tested rollback procedure.

Regular repository pushes are not CMS releases. GitHub-generated `Source
code (zip)` and `Source code (tar.gz)` are not accepted by the updater.
GitHub-hosted release-asset checksums attest to uploaded bytes via GitHub;
they are **not an independent cryptographic publisher signature**.
A publisher-signing policy remains an open production go-live requirement.

The proposed semantic versioning family is 1.0.0 / 1.0.1 / 1.1.0 / 2.0.0
for the public product, subject to final approval. Upgrading an internal
2.5.0-QA1 installation to 1.0.0 cannot work with the current monotonic
`version_compare` logic and requires an explicit cutover plan or fresh install.
