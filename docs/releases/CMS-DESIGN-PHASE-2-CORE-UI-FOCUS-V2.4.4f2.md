# V2.4.4f2 — Subtle Focus Borders (CMS Design System, Phase 2)

Based on locally functional V2.4.4f1 (`2.4.4.8`); candidate version `2.4.4.9`.

## Scope

- Replaced the dark `--admin-accent` border on focused text inputs, selects, textareas and Core search fields with a softer muted teal (`#829fa0`).
- For `:focus-visible` on those same controls, use a slightly clearer `#66898b` single 1px border, rather than the previous 2px combined border/inset line.
- No exterior outline, interior double line or shadow for standard controls. In forced-color mode, visible 2px `Highlight` outline remains for keyboard access.
- Preserved 3px global radius, standard 11px chevron offset and compact select variants.
- All standard shared admin styling remains Core-owned. No module, frontend, database or workflow changes.

## Test after replacing CMS files

Open Forms, Gallery and a Core form, then click/Tab through text inputs, selects, textareas and list searches. Keyboard focus on links, buttons and checkboxes must remain clear. Verify browser focus in high-contrast mode when relevant.

No SQL import required. Retain existing `.env`, database and uploads.
