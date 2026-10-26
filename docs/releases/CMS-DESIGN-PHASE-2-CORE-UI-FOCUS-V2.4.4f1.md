# V2.4.4f1 — Core Form Focus Refinement

Based on V2.4.4f (`2.4.4.7`); candidate version `2.4.4.8`.

## Scope

- Removed the doubled outer outline/shadow on focused Core inputs, selects, textareas and search fields.
- Pointer focus uses a single 1px teal border. Keyboard focus adds a 1px **inset** edge for a distinct 2px focus affordance without an external halo.
- Covers standard/compact selects, admin forms, list search, media picker search and language selector.
- Native radio/checkbox and keyboard outlines on buttons/links remain available.
- Preserves the 11px standard chevron offset and existing 3px global radius.
- Synced Core SCSS and CSS. No module, frontend, database, or workflow changes.

## Manual acceptance checks

Test pointer and Tab-focus states on a standard form input, select, textarea, list search, media picker search, compact filter select and user-menu language selector. Buttons/links must retain clear keyboard focus.

No SQL import required.
