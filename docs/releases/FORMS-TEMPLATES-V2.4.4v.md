# V2.4.4v – Forms Templates

**Approved base**: V2.4.4u (Core 2.4.4.30; Forms 1.3.11). **Test candidate**: V2.4.4v (Core 2.4.4.31; Forms 1.3.12).

## Scope
- Four built-in, JSON-backed Forms templates: Kontakt, Tilmelding, Feedback og Forespørgsel.
- Templates instantiate ordinary 100% or 50/50 Form Rows with existing field cards and per-field settings. First/last name are in one 50/50 row.
- Preview descriptions and choose a template in the existing modal. Apply replaces only the builder rows/fields, never form name, email settings, submissions or other form configuration.
- Explicit Core confirmation required whenever rows exist, **including completely empty rows**. Cancel, Escape and closing a modal retain all builder data.
- Replacement is constructed in memory before confirmation, with field keys unique within the form and distinct from the keys being replaced. Reindexing keeps mail field selects and form control names correct.
- Module PHP class validates/loads JSON and localizes text in the controller. View only presents UI and client interactions. Form POST still uses existing server-side validation, CSRF and transaction-based repository saving.
- Existing frontend semantic HTML, Theme frontend CSS, Forms schema and submissions are unchanged. No SQL needed.
- New admin-specific template choice CSS is identical in Core admin.scss and admin.css. Five languages: da/en/de/sv/nb.

## Local acceptance checklist
1. Existing form with fields: open Templates, choose Contact; cancel confirmation. Ensure all original rows, keys and settings are unchanged.
2. Confirm replacement: verify 50/50 first and last name; email and message in 100% rows. Edit labels, options and required switches, save and reopen.
3. Apply Registration, Feedback and Inquiry to an empty form, save/reopen, and verify editable rows and types, including select/radio options.
4. Apply to form with **only empty rows**; confirmation must still be required. Cancel preserves the rows.
5. Replace twice and confirm no duplicate field keys, broken row order or invalid email selectors. Previously saved submission records must remain.
6. Confirm notification/reply-to/confirmation settings are not silently changed, and adjust email-field selection where relevant.
7. Test Escape, Cancel, mobile layout, keyboard navigation and each admin language.
8. Check frontend rendering, valid/invalid submissions, saved submissions, notification and confirmation mail, Code Audit and Core QA.

## Packaging
Complete root `boilerplate/`, files 0644 / dirs 0755, current ZIP timestamps (OPcache), excludes local `.env`, private uploads, dumps and credentials.
