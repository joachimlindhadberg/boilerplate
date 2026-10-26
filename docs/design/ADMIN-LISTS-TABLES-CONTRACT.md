# Admin Lists & Tables Contract — V1.2

**Gældende fra V2.4.4z7:** `CORE-UI-STANDARD-V1.0.md` er overordnet for delt admin-UI; denne fil supplerer kun, hvor den ikke er i modstrid. Historiske releaseeksempler har ikke højere prioritet.

**Reference:** `public_html/admin/views/pages/index.php` (Pages, godkendt Core SVG action-mønster). Applies to CMS Core and all modules when displaying an admin list, table or media grid. The shared visual design is owned exclusively by `public_html/admin/assets/scss/admin.scss` and compiled `admin.css`. Modules never override core buttons, inputs, tabs, tables or toolbars.

## Implementeret Core-primitiv (V2.4.4z9, lokal testkandidat)

`core/classes/AdminListUI.php` leverer `filterTabs()` og `actionLinks()` som genbrugelig HTML-kontrakt, på toppen af eksisterende Core SVG og Core CSS. **Kun Brugere-oversigten er migreret i z9/z9a**; øvrige lister migreres én skærm/modul ad gangen efter eksplicit lokal godkendelse. Dette ændrer ikke de gældende regler for server-side søgning, pagination, POST/CSRF eller frontend ownership. Se `docs/releases/CORE-ADMIN-TABLES-LISTS-V2.4.4z9.md`.

## Layout

1. Use one `.admin-panel` for the list. Start with `.admin-list-toolbar`; place applicable `.admin-filter-tabs` on the left and the working search/filter form `.admin-list-search` on the right. A panel with no tabs or no search may omit that component; never add inert inputs or duplicated navigation.
2. Keep `.admin-list-summary`, `.admin-table-wrap`/grid, empty state and pagination inside the same panel.
3. Desktop (from 840px): toolbar may wrap complete elements, but must not squeeze search fields. Live-search lists have no visible Search button. Mobile: toolbar stacks and search expands to available width.
4. The desktop search input has `max-width: 265px` (current fixed target 265px). For shared list toolbars, never use page/module-specific widths. Filter groups wrap as a group when needed.
5. `.admin-button--compact` has `padding: 0 17px` and a 36px height, inherited across all admin lists.
6. Tab link buttons (`.admin-filter-tabs a`) have `padding: 0 13px`. **The padding belongs on the links, not the `.admin-filter-tabs` container**, to avoid shifting the whole tab bar.

## Behavior

- Tabs are navigation/filter shortcuts, not redundant copies of actions already available elsewhere.
- Search/filter forms use the shared `admin-live-search.js` controller (`data-admin-live-list` on the panel and `data-admin-live-search` on the GET form). No custom per-module search JavaScript or visible submit buttons. Use a `<noscript>` submit fallback.
- Query text triggers a server-side search after a 300ms pause. Results and pagination refresh without replacing the focused input. Select filters update immediately. A clear icon resets query text; filter values stay selected.
- Search/filter forms must search the full permitted dataset, not the current page only; updating text/filters resets pagination to page 1. Keep selected filters, show meaningful empty/error states, and synchronize URL/back-forward history.
- Tab and pagination links remain real GET links and are enhanced only when they point to the same list view; non-list tabs navigate normally. Lazy/dynamic list actions must use delegated event handlers or be rebound after result updates.
- When JavaScript is disabled, GET search works with Enter or the noscript submit button.
- Lists with pagination should use the core pagination component and preserve active search/filter parameters. Default is up to 10 rows per page for CRUD lists.
- Use the global `.admin-list-table` table formatting. **Editable entity lists must always use a final `Actions` column**, left aligned with `admin-list-table__actions` on the `<th>` and `<td>` and an `.admin-table-actions` wrapper. Use **Core SVG action links/buttons** (`admin_icon_action_link()`/`admin_icon_action_button()`) for actual View/Edit/Delete actions when applicable, with translated labels and the shared icon-action class; do not invent local SVG markup or page-specific styles. Text-labelled Core actions remain valid if more appropriate to the context. Show only actions genuinely supported for the row.
- Non-editable data (e.g. Activity Log and revision comparisons) must **never** offer a fake Edit action. A legitimate View/Compare action may appear in a final Actions column. Dashboard overview tables are read-only: do not render Actions columns, Edit controls, or row links to edit views. Navigation to the corresponding module belongs in the panel header.
- If there are no results, show a meaningful empty state *inside* the list panel, with a contextual hint when search is active.
- Create/New actions must have a readable text label; a Core SVG **plus** next to the label is permitted and already used by Pages, but is not mandatory everywhere. Destructive actions require the shared CMS confirmation modal and server-side POST/CSRF/authorization.
- Search labels must remain accessible and keyboard focus visible.

## Reference consumers

Pages, Media, Users, Forms (forms and submissions), Redirects, Activity Log, Gallery and Navigation where the list pattern is applicable. Modules can display specialized editors without implementing redundant list search, tabs or filters.

## Regression checks before a release

- Verify desktop widths (e.g. 1024, 1280, 1600) and narrow screens (e.g. 390, 560, 760).
- Verify form/filter wrapping, accessible labels, empty state, pagination URLs, and role-restricted data when applicable.
- Run PHP and JS syntax checks, validate CSS parsing, compare compiled CSS with SCSS, and inspect ZIP Unix permissions (`644` files / `755` directories).

- The user dropdown (`.admin-user-menu__dropdown`) has a white (`#fff`) background, independent of the soft-gray hover color of its items.

## Unified Scheduled Content (V2.3.10)

The Dashboard scheduled list remains **read-only** with five globally ordered actions (Pages and active modules), with no row-level Edit. `/admin/scheduled-content` is a full administration list with Actions → Edit on each editable record, Type/Action filters and 10 records per page. The common scheduler and module contracts live in `SCHEDULED-CONTENT-MODULE-CONTRACT.md`.


**V2.4.4z9a:** Brugere har nu Vis/Rediger/Slet med Core SVG, fælles CSRF-beskyttet slettebekræftelse og rollefilter før søgning. `AdminListUI::actionGroup()` understøtter en blanding af sikre GET-links og non-submit Core-knapper; `actionLinks()` er fortsat link-only. Se `docs/releases/CORE-ADMIN-TABLES-LISTS-V2.4.4z9a.md`.
