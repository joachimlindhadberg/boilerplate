# Boilerplate CMS V2.4.4d1 — Admin Select Chevron Spacing Fix

**Base:** V2.4.4d (2.4.4.4), locally approved.  
**Release:** 2.4.4.5.  
**Database:** Unchanged; no SQL import required.

## Change

- Updated the global admin select chevron fallback position from `right var(--admin-select-chevron-offset, 15px) center` to `right var(--admin-select-chevron-offset, 11px) center`.
- Both `public_html/admin/assets/scss/admin.scss` and the compiled `public_html/admin/assets/css/admin.css` are updated identically.
- Existing component-specific `--admin-select-chevron-offset` overrides (compact, filtered lists and builder) remain intact. These component variants will continue to use their explicit values.
- Core version increased for release identification; otherwise the CMS remains identical to V2.4.4d.

## Installation

1. Keep a backup of the current installation.
2. Update the CMS files from this package, preserving your existing `.env`, database and uploads.
3. Hard-refresh the browser (`Cmd + Shift + R`) to refresh the admin stylesheet.
4. Check the standard admin dropdowns and confirm the chevron alignment.
