# Boilerplate V2.2.4 — Activity Log Search & Dashboard Layout

## Scope

Focused, styling-only UI fix based on V2.2.3. No database migration or module updates.

## Changes

- **Tools → Activity Log:** Keep the shared multi-filter search form at full toolbar width on desktop instead of inheriting the ordinary compact search form's 48% width. Give the search input a usable flex basis/minimum and let selects wrap below when the viewport is narrow.
- **Dashboard:** Wrap **Recently Updated Pages** and **Recent Activity** in the existing core-owned dashboard grid with an equal-width modifier. At 1100 px and wider, both panels share a 50/50 row; at narrower widths they stack naturally.
- Keep all styling in the CMS's global admin stylesheet. `admin.scss` and `admin.css` stay byte-identical; no module-specific CSS was added.

## Installation

Replace the V2.2.3 application files with this ZIP. Keep your database, `.env` and uploads. **No SQL import needed.** Hard-refresh the browser (`Cmd + Shift + R`) if an old stylesheet remains cached.

## Verification

- Static PHP/JS checks, stylesheet parsing, stylesheet sync and ZIP integrity.
- Browser layout fixture tested in Chromium at viewport widths of 390, 768, 1024, 1100, 1280 and 1600 px. The search field stays usable, the two Dashboard panels are equal-width at >=1100 px, and no horizontal page overflow was detected.
- The full PHP/MySQL application was not exercised in a browser here; verify the two pages on the local installation before marking this release stable.
