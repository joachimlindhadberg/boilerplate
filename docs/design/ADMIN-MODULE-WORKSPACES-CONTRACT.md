# Admin Module Workspace Contract

**Gældende fra V2.4.4z7:** `CORE-UI-STANDARD-V1.0.md` er overordnet for delt admin-UI; denne fil supplerer kun, hvor den ikke er i modstrid. Historiske releaseeksempler har ikke højere prioritet.

Larger modules may use a **Core-owned secondary navigation** inside `<main class="admin-main" id="admin-main">`.

- The page title and subtitle occupy the same top-aligned, full-width titlebar as all other CMS pages.
- The secondary navigation begins **below** the titlebar, inside `.admin-section-layout`.
- Desktop: fixed 190px sidebar + fluid content; under 840px the nav becomes a compact horizontal/wrapping menu above the content.
- Core owns markup, classes, spacing, colors, focus states and responsive rules (`section-workspace.php`, global admin.scss/admin.css).
- Modules supply only translated nav labels, routes, active item, heading, subtitle and an optional create action using `ModuleContext::renderAdmin(..., ['sectionWorkspace'=>...])`.
- Navigating between module areas (Posts, Categories, Settings) belongs in the sidebar; tabs within lists are **filters only** (All/Published/Drafts).
- Live search, pagination, table actions and global modals continue to reuse CMS Core contracts.
- Do not duplicate the global CMS sidebar, produce a second `<main>`, or inject module CSS overriding global components.

## Core full-width navigation variation (V2.4.4z20c)

A secondary sidebar is **optional**. Core `section-workspace.php` supports an explicit `sectionWorkspace.navigationMode` of `sidebar` (unchanged default), `inline` (full-width content and a small horizontal navigation above it), or `none` (full-width content without secondary navigation). Core owns all markup, responsive styling and focus states. Modules provide only route/label data and choose a variant; modules must not implement their own CSS for the shared navigation.

Forms uses `none` for its new/edit editor, where its own accessible editor tabs and back link provide navigation, and `inline` for its overview, submissions and global settings to preserve access to real routes. No `.admin-section-layout__sidebar` is rendered for any Forms view. Other modules using the default `sidebar` retain the original 190px desktop navigation.
