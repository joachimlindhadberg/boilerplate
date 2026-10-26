# Boilerplate CMS V2.4.4y — Page Builder V2 Frontend HTML Contract

**Approved development base:** V2.4.4x (`BOILERPLATE_VERSION=2.4.4.33`).  
**Candidate:** V2.4.4y (`BOILERPLATE_VERSION=2.4.4.34`).  
**Status:** Test candidate until approved by the user on the local installation.

## Scope

This release updates only Page Builder public rendering and its **Theme-owned** frontend styles. Admin Page Builder V2, Forms Builder V2, Forms Templates, Gallery, revisions, publication workflow, existing storage schema and module CSS contracts stay unchanged. No SQL is required.

### Public HTML contract

Every normal single-content Page Builder section (Hero, Intro, Text, Text + Image, CTA or component) renders one 100% Row:

```html
<section id="section-123456" class="block-section" data-theme="light" data-content="text">
  <div class="container">
    <div class="site-grid site-grid--1">
      <div class="site-grid--item">...</div>
    </div>
  </div>
</section>
```

New two-column V2 Rows (`grid` with `layout_version=v2`, `column_count=2`, `preset=equal`) render the same outer structure with `site-grid--2` and **exactly two** `site-grid--item` wrappers. An empty V2 Row still renders both columns. Child components reuse the existing content renderer. Site-specific styling, spacing, breakpoints and theme colors come **only** from `public_html/assets/scss` / `public_html/assets/css/style.css`. Additional `data-theme`, `data-content` and `data-gap` attributes are semantic presentation hooks, not inline CSS.

Existing persisted `section-######` identifiers from V2.4.4w are included in the section. Invalid/absent IDs are *omitted* at render time, not regenerated: editing and saving a legacy section with the current editor will persist a valid ID. All saved IDs survive re-render unchanged.

**Legacy compatibility:** Existing unmarked grids (2–6 columns, including asymmetric two-column presets) keep their existing responsive `.section > .container > .grid` markup and settings and now expose their persisted section ID when valid. A V2 grid deliberately changed to an asymmetric preset also retains the prior grid renderer rather than silently losing its selected proportions. All other top-level Page Builder section types use the new V2 wrapper; their inner content uses the existing component classes. Hero behavior is preserved through Theme styling, not added module CSS.

## Changed files

- `public_html/incl/helpers/page_builder_renderer.php` — public dispatch and legacy-grid section anchors
- `public_html/incl/helpers/page_builder_v2_renderer.php` — new controlled public structure + inner content
- `public_html/assets/scss/style.scss` — Theme import
- `public_html/assets/scss/layout/_layout.site-grid.scss` — Theme grid and legacy visual compatibility
- `public_html/assets/css/style.css` — compiled Theme CSS in sync with SCSS source
- `public_html/incl/init.php` — CMS version 2.4.4.34
- `tests/page-builder-v2-frontend-v2_4_4y.php` — new no-DB frontend contract checks
- Existing V2.4.4w/V2.4.4x tests: updated historical assertions to reflect the new release, without removing behavioral checks

## Local acceptance checklist

1. Back up the approved V2.4.4x site and database; copy ZIP contents into the matching project root and keep local `.env` / uploads. No SQL import.
2. Edit and save a sample Page Builder page containing a normal 100% Text section and a 50/50 V2 Row. Reopen it and confirm unchanged content and stable `section_id` values.
3. View HTML source: one `section.block-section` per normal/V2 Row, exactly one `site-grid--item` for 100%, exactly two for 50/50. Check `section-######` anchor navigation.
4. Verify the second 50/50 column can remain empty; check desktop columns and mobile stacking.
5. Open older pages containing Hero, Intro, Text + Image, CTA, dynamic components, existing images and a 3–6-column or asymmetric 2-column legacy Grid. Confirm no broken content, layouts, buttons or CSS.
6. Verify Forms/Gallery components on frontend, public form submissions and revision restore where available.
7. After local test, approve or report deviations. V2.4.4x remains the approved baseline until this release is accepted.

## QA and distribution

- Read-only Core Code Audit, PHP lint, UI and section-ID regression tests, new public HTML contract tests, headless Chromium desktop/mobile layout checks, CSS/SCSS source synchronization, final ZIP permissions/structure/timestamps.
- Complete archive with `boilerplate/` root, mode 0644 files / 0755 directories, current per-file timestamps; no `.env`, local database exports, runtime content, uploads or personal secrets.
- These automated checks do **not** replace integration testing against the user's own local database, active modules or production theme.

**Next separate release:** Page Templates. Do not implement them in V2.4.4y.
