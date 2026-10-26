# Boilerplate CMS V2.4.4z1 – Pages Workflow Improvements

**Stable development base:** user-approved V2.4.4z (intern 2.4.4.47).
**Release version:** 2.4.4.48.
**Scope:** Pages admin routes/editor workflow only. No database schema migration, frontend/theme or Page Builder data changes.
**Status:** test candidate pending local user approval.

## Changes

1. A successful new Page creation redirects to `/admin/sider?mode=edit&id={createdId}` rather than the detail page. The existing server-side transaction, revisions, form validation and flash notice remain unchanged.
2. A successful edit redirects to `/admin/sider?mode=edit&id={id}` rather than the detail page. The editor now consumes and shows the existing success/error flash notice.
3. The edit titlebar offers **Se side** to open the saved public URL in a separate tab (`target="_blank"`, `rel="noopener noreferrer"`), alongside revisions. This is the actual frontend URL, **not** a new draft-preview endpoint. Draft/unpublished pages might not be available publicly until published.
4. Both the form's **Annuller** action and the back link go directly to the Pages list; the existing admin detail view is still accessible from the list.
5. The approved **Slet side** danger panel/modal is included on the edit view, below the sidebar settings and separated from Save. It uses the *same* POST/CSRF/`confirm_delete=permanent` handler and system-page/children protection as the detail view. The modal sits outside the main save form. Successful deletion returns to the list. If deletion fails, the handler returns to the initiating edit view (or detail view for the original modal), using a strict two-value `edit/view` whitelist.
6. The Pages-specific `admin-page-workflow.js` stores scrollY in per-page sessionStorage only when submitting an existing page for save. It restores after load, consumes the stored value once and expires it after five minutes. Browser storage errors never block submission. The new-page create screen opens the newly created editor at the top. A confirmed delete clears stale scroll state and opts out of a redundant unsaved-changes unload prompt.
7. The shared draft tracker only gains a small opt-in `admin:discard-draft` event handler; no global change in form behavior.

## QA to perform locally

- Create a new page, press **Opret side**, verify URL is `?mode=edit&id=<new-id>` and `Siden er oprettet.` appears.
- Save an existing Page Builder/Legacy page, verify the editor remains open with `Siden er opdateret.`, content persists and the scroll position remains near the saved area.
- Navigate away and reopen the editor normally; it must not unexpectedly restore an old scroll position.
- Click **Se side**, verify the frontend opens in a separate tab and the editor remains available. For drafts, published frontend access still follows existing route permissions.
- Click **Slet side**, cancel once, then test the confirm/POST workflow on an expendable page. System pages and pages with children must not be deletable. Verify deleted pages return to the index and no accidental Save occurs.
- Test both on mobile/narrow screens and desktop.

## Automated checks

- `php tests/pages-workflow-v2_4_4z1.php` – routing, modal protection and CSRF reuse, scroll integration and SCSS/CSS unchanged.
- Existing `tests/*.php` regression suite, PHP lints, JS syntax, `php core/cli/code-audit.php --release`, `php core/cli/qa.php`.
- Browser DOM fixture on 375/390/768/1280 px covers scroll save/restore/one-time consumption, delete open/cancel, submit cleanup and console JS errors. Full database-backed Pages transactions still require local integration testing.
