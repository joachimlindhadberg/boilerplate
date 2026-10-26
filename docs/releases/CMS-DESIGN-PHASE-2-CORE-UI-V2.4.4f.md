# V2.4.4f — CMS Design System / Phase 2: Core UI Components

**Approved base:** V2.4.4e (`2.4.4.6`). **Test candidate:** V2.4.4f (`2.4.4.7`).

## Changes (shared Core stylesheet only)

1. Added shared Core tokens for standard and compact control heights, button minimum height, horizontal control padding, hover-border color and focus shadow. Existing dimensions (43px standard, 36px compact, 40px button) remain unchanged.
2. Kept default select chevron at **11px**. Existing compact select overrides stay intact.
3. Moved status-label radius to the global 3px token.
4. Kept hover colors of disabled secondary and destructive action variants stable; aligned disabled input/textarea styling with selects. Native form semantics remain unchanged.
5. Added a clear 2px keyboard focus outline to Core text inputs, selects, textareas and list searches. Pointer focus appearance stays unchanged.
6. Made Core dialogs more resilient to narrow screens and long translated labels: `dvh` with `vh` fallback, wrapping actions, safe heading wrapping, and compact narrow-screen padding.
7. Added `docs/design/CORE-ADMIN-COMPONENTS-CONTRACT.md`; updated the phase plan. No module markup/business logic or frontend theme changes.

## Validation performed

- `php core/cli/qa.php`: **37 passes, 2 environment warnings, 0 failures** (CLI extensions `mysqli`, `mbstring` absent here).
- `php core/cli/translations.php`: 4 JSON catalogs, 1,170 keys across five locales, all pass.
- PHP lint: **125 files passed**; Node syntax: **3 JavaScript files passed**.
- Admin CSS parser: no stylesheet-level errors; admin.scss and admin.css byte-identical.
- Isolated Chromium component harness at **390, 760, 1024 and 1600 px**: expected input/select/button heights, disabled button resting/hover colors, status/select 3px radius, dialog within viewport and no page horizontal overflow, all passed.
- Not a full signed-in browser test against the user's actual local MAMP/MySQL installation. Real CMS screens and workflows still need user verification.

## Installation / testing

1. Back up V2.4.4e; update project files from the full release ZIP. Preserve existing `.env`, uploads and database.
2. Hard-refresh the admin browser to clear cached CSS (Cmd+Shift+R on Mac).
3. Check Admin → Modules (Example, Forms, Gallery) and a Core edit form. Verify buttons, badges, select arrow placement, disabled state and focus via keyboard.
4. Open/close a shared confirmation modal and inspect layout on mobile and desktop.
5. Check Gallery and Forms screens for regressions.

**Database:** No new schema/data changes; no SQL import required. No new PHP migrations.
**Status:** Release candidate until local approval.
