# Forms Builder V2 UI — V2.4.4u

**Approved base:** V2.4.4t (`BOILERPLATE_VERSION=2.4.4.29`; Forms 1.3.10)
**Candidate:** V2.4.4u (`BOILERPLATE_VERSION=2.4.4.30`; Forms 1.3.11)

## Scope
- Header `Form Builder` with description and Templates button. The button opens an informational dialog; template insertion belongs to V2.4.4v.
- Persistent bottom Add box opens a two-option modal: full-width field or empty 50/50 Form Row.
- Modal field-type selection uses the seven existing field types. Empty columns use the same modal.
- Existing cards can collapse/expand without removing input controls or changing their keys; new cards open for immediate editing.
- Preserve drag handles, up/down ordering, individual deletion, required/options controls, mail selectors, validation and frontend submission.
- Same first-class empty Form Rows from V2.4.4t. No new SQL in this release.
- Core-owned admin.scss and admin.css kept identical; frontend CSS unchanged.
- UI strings translated in Forms JSON for da/en/de/sv/nb.

## Manual regression
1. Start a new form: the add box must be the last builder element; Escape/Cancel from either dialog changes nothing.
2. Choose Form field > Email; its 100% row is inserted, and its editor is expanded. Save and reopen, verify key/type/settings.
3. Choose Form Row; save without fields and reopen (both empty slots preserved). Add first and last name independently, remove one, and verify the other remains.
4. Verify row drag and up/down order, delete confirmations, fields in dropdown/radio retain options.
5. Verify existing form mail selectors/reply-to and frontend forms/submissions.
6. Verify mobile stacking, keyboard focus and escape handling, and the five languages.
7. Verify templates button shows upcoming availability without modifying the form.

## Distribution
Full `boilerplate/` archive; no `.env`, private uploads, DB dumps or macOS artifacts.
ZIP preserves standard file/dir modes (0644/0755) and current file timestamps to avoid stale OPcache.
