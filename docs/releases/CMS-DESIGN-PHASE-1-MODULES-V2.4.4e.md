# V2.4.4e — CMS Design System / Phase 1: Modules Overview

**Stable base:** V2.4.4d1 (`2.4.4.5`). **Candidate version:** `2.4.4.6`.

## Changes

- Improved module list rhythm in Core: 18px vertical row padding, 20px columns and metadata at 11px for legibility.
- Activate, deactivate and update actions use Core's existing 36px compact button variant.
- Translated module page subtitle, installed heading, singular/plural result count, empty state and error labels in five locales.
- Dynamic module status text is HTML-escaped consistently.
- Added explicit design system phase plan. No behavior, database, frontend or module workflow changes.
- Source `admin.scss` and served `admin.css` remain byte-for-byte synchronized.

## Test on MAMP

1. Open Admin → Modules with English and Danish, then other admin languages as needed.
2. Verify 3 installed modules (Example Module, Forms, Gallery); compare row alignment desktop/mobile.
3. Test opening and cancelling the deactivation modal; do not deactivate production modules just to test visual layout.
4. Activate/deactivate Example Module, verify status and that it can be enabled again.
5. Confirm previous 3px radius and 11px standard select chevron spacing.

**SQL:** Not required. Keep existing `.env`, database and uploads.
