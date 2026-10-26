# CMS Design System — Phase Plan (from V2.4.4d1)

**Gældende fra V2.4.4z7:** `CORE-UI-STANDARD-V1.0.md` er overordnet for delt admin-UI; denne fil supplerer kun, hvor den ikke er i modstrid. Historiske releaseeksempler har ikke højere prioritet.

Scope: **Core admin** and existing **Example Module, Forms, Gallery**. No Blog, no new modules, no frontend-theme changes. Core owns shared UI.

## Phase 1 — Modules overview (V2.4.4e)

- Keep current card-list structure and existing activate/deactivate/update workflows.
- Apply core compact buttons for row actions, readable metadata, consistent row rhythm.
- Localize heading, count, empty state, error prefixes and subtitle on all five languages.
- Keep confirmations in shared Core modals. No schema changes.
- Regression: roles/capabilities, CSRF, valid/invalid manifests, active/inactive/update states, responsive layout.

## Phase 2 — Core component inventory / tokens (V2.4.4f)

Reviewed existing buttons, inputs/selects, fields, notices, badges, tables, toolbars, pagination and modals. Shared common geometry/interaction tokens; standardized keyboard focus, disabled variants, status-label radius, and compact modal overflow. Existing lists, tables, notices and pagination remain Core-owned with their validated markup/styling. No broad redesign or duplication of module rules. Detailed component rules: `CORE-ADMIN-COMPONENTS-CONTRACT.md`.

**Locally approved:** V2.4.4f6 (Core component refinements and Tools export). This is the stable baseline for Phase 3.

## Phase 3 — Core screen workflows

Audit Dashboard, Pages, Media, Users, Navigation, Settings, Tools, and Scheduled Content one screen at a time. Preserve existing routes and business logic.

**V2.4.4g — Dashboard (local approval pending):** correctly route upcoming/overdue actions, provide direct edit links for overdue records, distinguish an unavailable activity feed from an empty feed, and keep dashboard panels/metrics visually unchanged. No database changes.

## Phase 4 — Module screens

Review Example, Forms and Gallery one module at a time against the Core design rules: list summaries, workspaces, forms, delete modals, empty states and responsive layout. No module CSS that restyles core controls.

## Phase 5 — Final visual / accessibility QA

Check Danish, English, German, Swedish, Norwegian; keyboard/focus/dialogs; 390/760/1024/1600px viewports; darkening/hover/disabled states; SCSS/CSS sync, syntax and regressions. Sign off before V2.4.5 operations work.

**Release policy:** One narrowly scoped version at a time. Next release only after user approval. New database changes, if required, default to a separate phpMyAdmin-importable `.sql` file; existing CLI PHP migrations remain supported for historical upgrades. See `DATABASE-AND-RELEASE-POLICY-V1.0.md`.
