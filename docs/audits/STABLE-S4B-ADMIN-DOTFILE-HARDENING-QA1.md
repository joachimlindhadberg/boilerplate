# S4b — Apache admin dotfile hardening QA1

**Approved base:** `stable-prep` `4c27e50c35a29538200a2af5b093b000b53e108f` (S3b). Original isolated S4a-approved ZIP SHA-256: `ed5db814cac3db65acd74897db89c37140103d8a0c6764a29a42d6e9d20ebde1`.

## Finding

The root `public_html/.htaccess` contains a dotfile rewrite deny, but the nested `public_html/admin/.htaccess` does not contain a separate dotfile deny. A minimal Apache configuration without a global `<Files ".ht*">` or equivalent deny can process the nested rewrite rules without inheriting the root rewrite rules. A disposable loopback-only Apache fixture exposed harmless sentinel files at `/admin/.htaccess` and `/admin/.env` with HTTP 200. This **does not** establish that Simply.com or an existing installation is currently vulnerable: real host defaults can deny these names.

## Scoped correction

Add one `<FilesMatch "^\\."> Require all denied </FilesMatch>` directive to `public_html/admin/.htaccess` with a comment documenting the independent admin dotfile boundary. No PHP, database, migrations, updates, UI, configuration secrets, or real hosting settings are changed. Add this private report under `docs/audits/`, which is excluded from the production ZIP.

## QA gates

- Exact approved S3b commit, GitHub branch, old ZIP checksum and `.htaccess` blob pin; clean local checkout.
- Negative control on the old `.htaccess` rules using isolated Apache 2.4 and harmless fixture files: the two admin dotfiles must be demonstrably unprotected in this minimal config.
- Positive control against the patched admin rule: both admin dotfiles and all other forbidden paths return HTTP 403; public static file routes return HTTP 200; relevant security headers retained.
- Production ZIP source-byte match, negative inventory regression, PHP syntax and recovery/Core/code audits.
- Produce a **new, unpublished** candidate under a separate S4b Downloads folder with its own SHA-256; preserve original S3b candidate.
- No commit, push, tag, draft, public release, DB access, existing Apache service changes or Simply.com changes.

## Boundaries

A loopback Apache test is **not** proof of actual hosting `AllowOverride`, host PHP-FPM handler wiring, HTTPS, webroot isolation, session security or recovery. The exact new candidate must be independently verified and, after user sign-off/GitHub, undergo S4a integrity check again. Target-host HTTPS and dotfile enforcement remain outstanding before Stable. Licensing provenance and Beta 9 to Stable database upgrade remain separate NO-GO gates.

## QA1a runner correction

QA1 reached the expected negative Apache-control findings, then its Git
porcelain status parser incorrectly removed the first status column with
`strip()`. QA1a trims only line endings and adds a disposable Git-fixture
regression to verify unstaged and untracked statuses. The source policy fix
and all acceptance criteria are unchanged.
