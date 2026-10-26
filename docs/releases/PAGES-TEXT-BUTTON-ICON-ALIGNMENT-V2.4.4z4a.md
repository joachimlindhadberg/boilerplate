# Boilerplate CMS V2.4.4z4a – Pages Text Button Icon Alignment

**Base:** User-approved V2.4.4z4 (internal 2.4.4.51).  
**Release:** V2.4.4z4a (internal 2.4.4.52).

## Scope

- Add the existing Core `plus` SVG to the **Ny side** button in the Pages list titlebar and the equivalent unfiltered empty-state CTA; leave **Ryd filtre** without a misleading plus icon.
- Fix the **Se side** button on the Pages editor, which rendered the `external` SVG without an explicit sizing class. It now uses the shared Core `.admin-button__icon` styling, preserving its public URL, new-tab behavior, and button variant.
- Use the same shared Core icon class on the existing **Se side** and **Rediger** actions in the Pages detail view, replacing the redundant view-only CSS rule.
- Add one shared **Core-owned** 16 × 16 px icon-with-text button rule, with 7 px spacing, while preserving the established button, focus, and hover styles. Both `admin.scss` and compiled `admin.css` are updated together.

## Unchanged

- No database migrations, backend routes, data model changes, or frontend theme changes.
- Existing Pages list eye/edit/delete icon actions, dynamic search and deletion protections preserved.
- Pages editor workflow, revisions, modal behavior, author auditing, and Page Builder unchanged.

## Installation

Extract complete `boilerplate/` package over previous release, preserve the existing `.env`, database and uploads. No new migration required when upgrading from V2.4.4z4.

## Suggested local checks

1. Verify **Ny side** in list titlebar shows a small plus inline with its label. Verify unfiltered empty state, and ensure *Ryd filtre* does not show a plus.
2. On any existing page editor, check **Se side** has an appropriately-sized external-link icon and aligns with **Versionshistorik**.
3. Check **Se side** still opens the public URL in a new tab; note unpublished drafts may be unavailable on the frontend.
4. Verify detail view **Se side** and **Rediger** still navigate properly, including mobile view.
5. Spot-check Pages list eye/edit/delete actions and existing Page Builder save.
