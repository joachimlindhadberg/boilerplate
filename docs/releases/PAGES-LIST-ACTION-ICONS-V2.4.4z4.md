# Boilerplate CMS V2.4.4z4 – Pages List Action Icons

**Base:** Locally user-approved V2.4.4z3 – Pages Author Audit (`2.4.4.50`).
**Version:** `2.4.4.51`.
**Status:** Test candidate; requires explicit user approval after installation and local verification.

## Scope and behavior

- Pages list > Handlinger has precisely three icon-only actions, using the Core `AdminIcons` registry and its common hover/focus/danger states:
  1. Eye **Se side** navigates to the existing admin detail (`/admin/sider?mode=view&id=…`), including for unpublished drafts; it is **not** a public frontend preview.
  2. Pencil **Rediger** navigates to the existing edit page.
  3. Trash **Slet side** opens a single shared Core confirmation modal. Only submitting the modal's POST form can delete.
- All action buttons have localized Danish/English/German/Swedish/Norwegian `title`/`aria-label` values. The row action group includes the page title for screen-reader context.
- The delete control is disabled for system pages or pages with child pages. The list query supplies `has_children` through a read-only correlated `EXISTS`, without a schema change or extra N+1 count queries. The model's `deleteAdmin` *still* checks these conditions authoritatively on the server when processing POST, including any concurrent change after the list is rendered.
- Confirmation includes page title via `textContent`, never HTML injection. The modal uses the shared Core fixed header, scrollable body and fixed footer. Existing CSRF protection, explicit `confirm_delete=permanent` and the deletion transaction are unchanged.
- Failed list-initiated deletions return to `/admin/sider` through a strict `return_to` allowlist. Existing detail and edit failure redirects remain intact.
- Core `data-admin-open-delete` click handling uses event delegation so a Pages list refreshed with AJAX live search can still open its shared static modal. The modal is deliberately rendered *outside* the dynamically replaced `data-admin-live-list` section.
- Core table action groups retain one horizontal row of icons at mobile widths instead of the older mobile stacked text-button layout. The existing horizontal table scrolling is preserved. Global admin SCSS and compiled CSS are byte-identical. No changes to frontend, page data structures, version history, Page Builder, forms, or database schema.

## Installation

Deploy the complete `boilerplate/` ZIP on top of **V2.4.4z3** while preserving `.env`, database, uploads and runtime settings. **No new SQL migration** for this release. If upgrading from an older version, complete any *previous* pending migration (especially V2.4.4z3 Pages Author Audit) before accessing the updated admin.

## Manual verification before approval

1. On `/admin/sider`, verify eye → admin detail, pencil → editor, trash → named confirmation modal.
2. Cancel and press Escape: verify that neither deletes a page. Confirm deletion only on a disposable test page. Verify redirect to the list and success notice.
3. Verify the trash is disabled for the homepage/system page and a page with a child page. Do not attempt to delete production content for this test.
4. Use the live search field and filters, then open the trash for a newly loaded row. Ensure the correct page name and ID are used.
5. Check mobile and desktop, keyboard focus and screen-reader/hover labels. Check that the three icons remain on one row and that table horizontal scrolling behaves normally.

## Automated QA

- New isolated PHP view/security/regression tests: 37 PASS / 0 FAIL.
- Chromium fixture browser checks: 105 PASS / 0 FAIL at 375, 390, 768, 1280 and 1440 px; including modal interactions, dynamic live-search replacement and HTML injection hardening.
- Existing PHP tests, syntax lint, Code Audit, Core QA, JavaScript syntax check, SCSS/CSS synchronization and final ZIP extraction checks passed. The browser fixture is not a database-backed end-to-end deletion test; the real installation requires manual confirmation.
