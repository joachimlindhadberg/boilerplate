# Boilerplate V2.4.4b — Notice Close Alignment & Admin Radius

**Base:** V2.4.4a. **Internal version:** `2.4.4.2`. No SQL migration.

## Scope
- Replace the typographic × with a fixed 14px inline SVG icon in the Core admin notice dismiss button for precise visual centering. Keep the 28px clickable button, localized accessible labels, keyboard support and dynamic notice handling.
- Change global `--admin-radius` from `2px` to `3px` across the admin.
- Keep Core SCSS and compiled CSS synchronized. All previous V2.4.4a translations and notice logic are preserved. No module or database logic changes.

## Manual verification
1. Open Admin notices at desktop/mobile widths. Confirm icon is centered in close button and works using mouse and keyboard.
2. Change admin language; confirm localized accessibility labels and notice translations.
3. Review buttons, fields, panels and modals for the global 3px radius.
4. Preserve `.env`, database and uploads; hard-refresh CSS/JS after deploying.
