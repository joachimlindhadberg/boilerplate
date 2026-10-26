# Boilerplate CMS V2.4.4z4a1 — Single live-search clear control

Base: user-approved V2.4.4z4a (2.4.4.52). Internal version 2.4.4.53.

## Scope
- Fix the two X controls shown in the Pages search input in Chromium.
- Mark only JS-enhanced Core live-search forms with `.admin-list-search--enhanced` after attaching their existing Core clear button.
- In enhanced forms, hide only the browser-native `::-webkit-search-cancel-button`. The existing Core clear button and live-search event flow are unchanged.
- With JavaScript disabled, the Core clear button is not added and the native search cancel affordance remains available.
- Applies consistently to other Core live-search lists using the same shared component.
- No changes to Pages data, routing, SQL, frontend, or confirmed V2.4.4z4a actions. SCSS and CSS updated together.

## Local verification
1. Open `/admin/sider`, type a query, and confirm that there is **one** visible clear X (Core reset).
2. Click it and verify results + URL reset, while keeping focus in the input.
3. Search again after changing tabs and using Back/Forward.
4. Test empty query, keyboard navigation, and (where practical) search with JavaScript disabled.
