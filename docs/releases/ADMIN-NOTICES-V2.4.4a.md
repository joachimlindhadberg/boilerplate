# Boilerplate V2.4.4a — Admin Notices & Localization

**Base:** V2.4.4. **Internal version:** `2.4.4.1`. No SQL migration. Existing database, `.env` and uploads must be kept.

## Changes

- Shared, accessible close (`×`) button on all `admin-notice` elements inside `#admin-main`, including notices created dynamically by `adminNotify`. Clicking closes only that notice; no backend settings or messages are deleted.
- All common admin flash notices are now translated at display time, not when stored in the PHP session. Pre-upgrade flash sessions remain readable.
- Additional translations in all five admin locales (`en`, `da`, `de`, `sv`, `nb`) for previously missing messages, upload errors and the site's backup error.
- Parameterized translations for media/gallery references with singular/plural counts, media upload counts, maximum upload size and import counts. Filenames are escaped at the output boundary.
- Page Builder's Legacy HTML notice is localized.
- Gallery reference protection, Media Library delete safety and SQL triggers are **unchanged**.

## Manual checks

1. Set admin language to English, then German/Swedish/Norwegian/Danish; upload a file and save a form; verify notice language.
2. Add a media item to Gallery; attempt to delete in Media; verify translated *in-use* notice and no deletion.
3. Verify notice close button via mouse and keyboard (Tab → Enter/Space). Confirm dismissing a notice does not delete or change anything.
4. Confirm notices created by inline validation also have the close button.
5. Confirm Media, Forms, Gallery, Pages and Tools still work after update.

## Implementation contract

Use `admin_set_notice($translationKey, $type, $parameters)` for redirect notices. Do not call `t()` before storing a flash message; the reader translates with the active user's locale. For notice markup use Core's `.admin-notice` classes. No module-specific notice CSS or JavaScript. The close button is presentation-only, not a security or authorization mechanism.
