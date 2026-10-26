# Boilerplate CMS V2.4.4y1 — Page Builder Admin Overflow Hotfix

**Base:** V2.4.4y (test candidate). **Internal version:** 2.4.4.35.

## Symptom and cause

`/admin/sider?mode=edit` could have horizontal page scrolling when an existing Page Builder row contained grid columns. Each column's legacy, accessibility-only `<select class="sr-only" data-grid-child-type>` inherited `.admin-grid-column__add select { width: 100%; height: 36px; }` instead of maintaining the 1×1 pixel visually hidden geometry. As an absolutely positioned control, it could extend the document width far beyond the viewport. The issue existed in the Page Builder UI stylesheet and became visible during V2.4.4y local testing; V2.4.4y's frontend-only changes did not introduce the CSS rule.

## Scope

- Core admin SCSS and compiled CSS: narrow specificity fix for `body.nf-admin .admin-grid-column__add select.sr-only` restores 1px width/height and removes visual padding and border.
- No broad `overflow-x: hidden` workaround.
- No Page Builder data, controller, frontend HTML, frontend Theme CSS, database, Forms, or legacy grid changes.
- Dedicated regression test `tests/page-builder-v2-admin-overflow-v2_4_4y1.php`.

## Verification

- Chromium DOM regression across 375, 390, 620, 768, 900, 1024, 1280 and 1440px including opening Row and Grid field editors: no document-level horizontal overflow.
- PHP source check confirms compiled Core admin CSS equals SCSS and hidden controls remain accessible in the DOM.
- Re-run existing V2.4.4w/v2.4.4x/v2.4.4y Page Builder tests, code audit and Core QA.

## Local test

1. Install full V2.4.4y1 ZIP over installed V2.4.4y. Preserve `.env`, database and uploads. No SQL import.
2. Reload `/admin/sider?mode=edit&id=...` without browser cache.
3. Inspect a Page Builder page with 50/50 rows and, if available, a legacy 3+ column grid. Open/close editor cards and check there is no horizontal page scrolling.
4. Save and reopen the page and check its frontend presentation, section IDs and existing content.

**Approval:** V2.4.4y1 remains a test candidate until locally approved. V2.4.4x remains last locally approved stable base.
