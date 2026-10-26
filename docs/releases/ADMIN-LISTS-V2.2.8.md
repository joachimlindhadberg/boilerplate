# Boilerplate V2.2.8 — Admin Lists & Tables Standardization

Base: V2.2.7. No database migration. No changes to the public theme or frontend routes.

## Changes

- Global admin search fields are capped at 265px on desktop, with the search/action controls staying together. Toolbar groups wrap at narrower widths rather than collapsing a search input.
- Global `.admin-button--compact` horizontal padding: 17px. Global `.admin-filter-tabs a` horizontal padding: 13px.
- Pages remains the canonical list-toolbar reference; Media, Users, Blog, Forms and other list screens reuse the shared core CSS.
- Blog list search uses the same icon and compact primary button as Pages.
- Forms Submissions search uses the shared search icon/layout.
- Forms list now has working name search, a contextual empty state, and up to 10 forms per page via core pagination. Filter/query value is preserved when paging. Localization keys added for all five supported admin languages.
- No custom module CSS for global controls. Admin SCSS and compiled CSS are synchronized.

## Versions

Core: 2.2.8; Forms: 1.3.3; Blog: 1.1.5. Gallery and Example Module retain their existing versions.

## Upgrade

Replace CMS files using a backup of the existing installation; preserve `.env`, database, uploads and local server configuration. No SQL import. Hard-refresh admin after updating CSS.

## Recommended local tests

1. Check toolbar alignment on Pages, Media, Blog, Users and Forms in desktop/mobile browser sizes.
2. Search a form by name; verify clear/reset, empty results and pagination beyond ten forms.
3. Check Forms Submissions search with both select filters and verify filter results.
4. Check other admin lists and the frontend for regressions.

Static syntax/CSS/layout checks do not replace MySQL/browser verification in the user's local installation.
