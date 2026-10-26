# Boilerplate CMS V2.4.4z5 – Forms Builder Core SVG Actions

Internal version: **2.4.4.54**. Base: user's approved **V2.4.4z4a1** (2.4.4.53).

## Scope

This is an isolated Forms Builder interaction and appearance release, **not** a Forms schema/data-model or other-module list/table redesign.

- Row toolbar uses Core-owned, label-safe SVG buttons: move up, move down, delete.
- Field toolbar uses Core chevron-down/rotating-up toggle and delete, retaining `aria-expanded` and translating `aria-label`/`title` to match the current state. The SVG element remains in place on expansion/collapse.
- Form Builder main "Add" and each empty column's "Choose field type" controls use the Core SVG plus without a surrounding square.
- Core SVG registry adds `arrow-up` and `arrow-down` for reuse in later releases.
- All shared button states (neutral, danger, focus, hover, disabled), sizing and borders continue to be Core-owned. Forms adds only layout rules and the `aria-expanded` chevron rotation.
- Existing keyboard row reordering, drag-and-drop, form field validation, deletion confirmations, templates, CSRF/saving, and form layout behavior retained.
- All five Forms languages have the new "Åbn redigering" accessibility label.

## No changes to

Forms field keys, templates, save/submit endpoints, stored rows and fields, Page Builder, frontend CSS/markup, forms/submissions admin lists, database or SQL migrations.

## Tests

- `php tests/forms-builder-core-svg-v2_4_4z5.php`: **35 PASS, 0 FAIL**.
- `php tests/generate-forms-svg-fixture-v2_4_4z5.php`: generates a real PHP view fixture for Chromium; generated HTML is **not** shipped in the ZIP.
- Chromium: **215 PASS, 0 FAIL** across 375/390/768/1280/1440 px, testing editor toggle and retention of SVG, aria labels, row movement/indexing/disabled controls, cancel/confirm remove field and row, dynamic creation of rows/fields and no horizontal overflow. Browser fixture simulates `adminConfirm`, does not write DB.
- All **27 PHP test files** pass; legacy fixture-only stubs updated to load Core icons and historical version test made forward-compatible.
- Core Code Audit: **165 PASS / 0 FAIL**; Core QA: **37 PASS / 2 known CLI environment warnings / 0 FAIL**; **198 PHP files** syntax check passed; Core SCSS and compiled CSS match byte-for-byte.

## Installation

Install complete ZIP over V2.4.4z4a1, preserving `.env`, database and private uploads. No SQL migration required. Verify expansion/collapse, field/row deletion cancellation and completion, keyboard-based row move, form save/reopen and templates in local deployment. Do not treat as an approved stable release until user explicitly confirms local tests.

## Next independent release

Roll out the same Core SVG action standards to admin **tables and lists**, starting with Forms/other module lists only after V2.4.4z5 approval. Keep existing authorization and destructive POST/CSRF flows.
