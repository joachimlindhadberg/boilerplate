# Boilerplate V2.2.9 — Global Live Search

Base: V2.2.8. English remains the default admin language.

## Changes
- Shared `public_html/admin/assets/js/admin-live-search.js` (300 ms debounce, abortable HTML fetch, server-side filtering, URL state, history, accessible status, reset control).
- Live results in Pages, Media, Users, Redirects, Activity Log, Blog posts, Forms, Forms submissions, Gallery listing.
- No visible Search/Filter submit buttons when JavaScript is enabled; functional GET form fallback without JavaScript.
- Core admin styling owns clear controls, search input and busy/error presentation. SCSS and CSS are synchronized.
- Media-card modal and Forms submission delete events remain usable after AJAX updates through delegated events. Shared confirmation forms also use delegated handling.
- Forms submissions now use database-backed count and pagination (10 results per page) rather than a hard-coded maximum of 100.
- Gallery lists now have database-backed search and pagination (10 per page). Gallery media picker retains its existing specialized search.
- All five admin languages include the shared live-search loading and error messages.

## Installation
No SQL migration. Back up the working CMS and database; update PHP, JS, CSS and module files. Preserve `.env` and uploads, then hard-refresh your browser.

## Manual regression checklist
1. Search in Pages, Media, Users, Forms, Blog, Gallery, Redirects and Activity Log. Search also inside Forms submissions.
2. Confirm the results match the entire filtered database (not only the first 10 items); try matches on a later page.
3. Check tabs, multi-select filters, pagination and back/forward navigation. The URL must update.
4. After searching, open a Media item and delete a Forms submission/Redirect using the CMS modal.
5. Confirm Enter and filter submission work with JavaScript disabled.
6. Verify desktop input max 265 px and mobile filter wrapping; check English, Danish, German, Swedish and Norwegian.

## Verification note
Static PHP/JS/translation integrity checks were performed. Browser navigation is blocked in this build environment, so integrated browser/MySQL behavior must be verified in the user's installation before locking this release.
