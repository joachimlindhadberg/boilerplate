# Boilerplate CMS V2.4.4z6 – Forms Builder / Page Builder Design Parity

Internal version: **2.4.4.55**. Base: **user-approved V2.4.4z5 (2.4.4.54)**.

## Scope

Isolated Forms Builder admin UI/UX release. Forms Rows now visually follow the approved Page Builder hierarchy:

- Compact light-grey Row header, neutral 100% or 50/50 layout badge, "Grid / kolonner" subtitle, existing drag handle and Core delete SVG.
- **No visible Up/Down action icons**. Reorder with Row grip via mouse drag; keyboard `Alt+ArrowUp` / `Alt+ArrowDown` on the focused grip (accessible hint/label).
- Column cards with "Kolonne 1/2" title and live "0 felter"/"1 felt" summary, thin borders and consistent spacing.
- Field cards preserve Core Chevron Down/Up and delete SVG, matching Page Builder's compact card presentation and typography.
- Empty columns use the same centered, dashed "Tilføj felt" pattern as Page Builder, without nested excess whitespace or a box around the plus SVG.
- Main Form Builder add card retains functionality, now uses the Page Builder white/dashed visual style.
- Dynamically inserted or form-template-created fields are appended **inside** the new column content wrapper; never outside the visible card.
- Only layout CSS lives in the Core stylesheet; Core SCSS source and compiled CSS are byte-identical. All shared buttons and controls retain global Core styles.
- Five translations for newly exposed labels and keyboard instruction.

## Deliberately unchanged

Existing Forms data model, 100%/50-50 row types, form templates, field types and keys, form POST/validation, server-side persistence, email/notification options, frontend rendering, Pages, other modules' tables/lists, existing delete confirmation, and database schema. No new SQL migration. The Core arrow-up/down icons remain registered for other modules but are no longer rendered in Forms Builder.

## QA

- `php tests/forms-builder-page-parity-v2_4_4z6.php`: **60 PASS / 0 FAIL**.
- Chromium real Forms PHP-view browser regression: **175 PASS / 0 FAIL** at 375/390/768/1280/1440 px; covers inline editor, chevron rotation, keyboard Row reorder, dynamic field, summaries, cancelled/confirmed field deletion, add empty Row, field keys and serialized form names, layout and overflow. Mouse drag-and-drop also verified separately.
- All **28** PHP regression test files PASS (historic SVG test updated for the approved UI change); **199** PHP files lint PASS.
- Code Audit: **165 PASS / 0 FAIL**. Core QA: **37 PASS / 2 known CLI environment warnings / 0 FAIL**.
- Complete ZIP extracted, audited, and retested. Browser fixture files and local-only files are excluded.

## Installation and user test

Install the full ZIP over approved V2.4.4z5, preserving `.env`, database, runtime data, and private uploads. No SQL change. Locally verify an existing 50/50 form, creation of empty Row and field, keyboard and drag Row movement, form template insertion, save/reload, error validation, and removing a field or Row.

Do not use this release as the new stable base until the user explicitly approves it locally. Only then proceed separately with Forms table icons and other modules' lists.
