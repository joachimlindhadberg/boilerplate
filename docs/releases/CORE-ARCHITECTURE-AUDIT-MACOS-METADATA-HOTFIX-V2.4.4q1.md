# Boilerplate V2.4.4q1 – macOS Metadata Audit Hotfix

## Base and scope

Built exclusively on V2.4.4q. This is an audit-only correction: no changes to application controllers, views, workflows, routes, permissions, database, themes, CSS, SCSS, or JavaScript. Internal version: `2.4.4.23` → `2.4.4.24`.

## Reported issue

Running `php core/cli/code-audit.php` on an installed CMS on macOS failed the check `No macOS metadata` when Finder created `.DS_Store`. This is not evidence that the CMS codebase or controller/view architecture is broken.

## Fix

- **Installation audit** (`php core/cli/code-audit.php`): ignores `.DS_Store`, AppleDouble `._*` and `__MACOSX` content at any nested path; never hashes or reads those files. An existing `.env` continues to be accepted without reading it.
- **Release audit** (`php core/cli/code-audit.php --release`): continues to reject these metadata files in a distribution and rejects `.env`.
- Detection uses exact filename/path components to avoid rejecting unrelated source files whose names merely contain `.DS_Store` or `__MACOSX`.
- The check label now reflects the selected audit mode.
- Both JSON and text audit outputs report `2.4.4q1`.

## Installation

Unpack the complete `boilerplate/` tree over an installed V2.4.4q project while preserving your `.env`, database and uploads. **No SQL changes.** There is no need to delete `.DS_Store` files locally.

Run `php core/cli/code-audit.php` in the project root. The regular installation audit should pass with or without Finder metadata. `--release` is for inspecting clean release copies, not an ordinary local installation.

## Regression scope

Check normal installation mode with root/nested `.DS_Store`, AppleDouble and `__MACOSX` metadata; strict release mode with each unexpected artifact; `.env` handling; PHP syntax; existing codebase audit, core QA, translations, and full-package paths/file permissions.

The live Apache/MySQL workflows must still be verified locally before treating V2.4.4q1 as stable.
