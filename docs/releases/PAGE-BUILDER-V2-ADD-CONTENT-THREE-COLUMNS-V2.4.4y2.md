# Boilerplate CMS V2.4.4y2 — Page Builder Add Content & Three Columns

Base: approved V2.4.4y1. Isolated release before Page Templates; no database migration.

- Add-content modal grouped into **Sektioner** (Hero, Intro, Tekstsektion, Tekst + billede, CTA, Dynamisk komponent) and **Layout** (100%, 50/50, 33/33/33).
- Standalone sections use the existing top-level PageBlocks model. 100% layout retains its established content-picker workflow and thus creates a full-width top-level content section.
- New 33/33/33 V2 grid is stored as `grid` with `column_count=3`, `preset=equal` and `layout_version=v2`. Empty columns survive save/reopen. 50/50 behavior remains unchanged.
- A 3-column V2 row uses one `section.block-section` with three `site-grid--item` wrappers under `site-grid.site-grid--3`. Theme owns breakpoint and layout styling.
- Old grids (including 3-column equal grids not tagged `layout_version=v2`) retain legacy rendering. No auto-conversion of existing pages.
- Theme SCSS/CSS and Core-admin SCSS/CSS are kept synchronized. V2.4.4y1 overflow fix is preserved.
- Translation keys for all five languages. Page Templates placeholder unchanged.
- Release ZIP: `boilerplate/` root, chmod 0644/0755, fresh timestamps, no local env/uploads/exported data.

Local verification: add Hero directly; add 100%, 50/50 and 33/33/33; fill three columns independently; cancel modals without changes; save/reopen; mobile responsive columns; legacy grid; no sideways scrolling.
