# Boilerplate CMS V2.4.4z3 – Pages Author Audit

**Base:** V2.4.4z2 Pages Detail View Redesign (`2.4.4.49`), which includes V2.4.4z1 Pages Workflow and derives from the locally approved V2.4.4z Page Templates.
**Version:** `2.4.4.50`.
**Status:** Test candidate, pending user's local approval. Pages action icon redesign is reserved for a separate follow-up release.

## Scope

The Pages detail sidebar now displays **Oprettet**, **Oprettet af**, **Opdateret**, and **Opdateret af** in a compact two-column audit layout. Dates use `dd.mm.yyyy – hh:mm` using the CMS's existing server-side timezone treatment. Names come from real users (first + last name), not arbitrary strings in page data. Unknown or deleted users display `Ukendt` rather than a guessed identity. Historical pages have NULL creator/editor IDs until they are genuinely edited by an authenticated user; edits do **not** fabricate the original creator.

- Core `Pages::createAdmin` stores the authenticated user as both initial creator and last editor; `updateAdmin` changes *only* the last editor. Same transaction as page content and page blocks.
- Revision restore updates the last editor to the person who restored; the immutable revision snapshot does not restore old audit author IDs.
- Scheduled publication/unpublication resets `updated_by` to NULL because automation is not a user; it still updates `updated` as before. The UI then shows `Ukendt`, not a false user name.
- Two nullable foreign keys (`pages.created_by`, `pages.updated_by`) reference `users.id` with `ON DELETE SET NULL`, so user removal does not remove the Page. Backward-compatible method signatures retain previous arguments and support unattributed non-interactive API writes.
- The read-only details page keeps its current information, revisions, SEO, section list and links, and does **not** regain the Slet button. Page Builder, frontend CSS, and block serialization remain untouched. Core admin SCSS/CSS updated identically and five locale entries added.

## Required explicit migration

**A real database change is required.** Back up the existing database before deploying. Never import `boilerplate.sql` or any baseline dump over an existing site.

1. Deploy the full ZIP on the target instance while preserving `.env`, database and existing uploads.
2. **Before opening the updated admin**, from the `boilerplate/` root run:

   ```bash
   php core/cli/migrate.php --status
   php core/cli/migrate.php --apply
   php core/cli/migrate.php --status
   ```

3. Confirm `core:20261016_000_pages_author_audit` is `applied` in the final status output. MigrationRunner only executes the new migration when not already registered. Its DDL is idempotent for recovery from a partial ALTER. It does not backfill unsupported names or alter timestamps.
4. Create a page as User A. Verify timestamps/creator/editor all correct. Edit it as User B; verify the original creator remains A and latest editor becomes B. Test revision restore and a legacy page (without recorded author), which should show `Ukendt`.

## Verification performed

- New isolated PHP author/migration/markup/security tests: **36 PASS / 0 FAIL**, including repeat and partial migration simulations, prepared CREATE/UPDATE bindings, creator immutability, attribution of restores, automatic scheduling, escaped names and missing historical authors.
- Chromium layout at 375, 390, 768, 1280 and 1440 px: **30 PASS / 0 FAIL**, audit grid order, column layout, no horizontal scrolling; screenshots inspected.
- All existing PHP regression tests and static audits PASS, plus all packaged PHP syntax files and synchronized admin SCSS/CSS.
- No database-backed integration run in this execution environment; local installation with MySQL is still required before approving the release.
