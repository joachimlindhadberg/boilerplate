# Boilerplate V2.4.4s1 – Gallery controller/view compatibility hotfix

**Base:** V2.4.4s (candidate), built on last approved V2.4.4r1. **CMS internal version:** `2.4.4.28`.

## Reproduced regression

After updating to V2.4.4s, clicking "Nyt galleri" threw `Module admin view not found` at `GalleryModule.php:148`. Exact comparison against the previous release shows that **line 148 in the V2.4.4r1 GalleryModule.php renders `admin/views/form.php`**. The V2.4.4s file no longer has that call; its line 148 is just the closing class brace. The error means the PHP request ran the **old handler**, possibly because a file was not overwritten or because PHP OPcache had not reloaded the new class; it does **not** prove which of those happened.

The V2.4.4s ZIP removed `admin/views/form.php` and thus broke when older handler code was still running. The audit only examined the ZIP, not the user's live runtime. This hotfix prevents that specific mismatch.

## Changes

- Restored `modules/gallery/admin/views/form.php` as a **thin compatibility adapter** to `partials/gallery-editor.php`; does not duplicate gallery markup or styling. The new `new.php` and `edit.php` still render the same editor.
- Added audit assertions for the compatibility adapter and for a stale `GalleryModule.php` which still calls `admin/views/form.php`. Audit emits actionable text when outdated source is present.
- Bumped core version from `2.4.4.27` to `2.4.4.28`. No module manifest changes, schema updates, frontend styling, or JS changes.

## Local installation

Merge the **complete ZIP contents**, including overwriting existing files, into the existing `~/Sites/boilerplate`, preserving `.env`, uploads and database. Check the entrypoint was replaced:

```bash
cd ~/Sites/boilerplate
grep -n 'use GalleryAdminController;' modules/gallery/GalleryModule.php
php core/cli/code-audit.php
php core/cli/qa.php
```

The grep should show `use GalleryAdminController;` and the audit should pass. If the code is current but the old handler still runs, restart the local Apache/PHP service to clear relevant opcode caches. Do not delete `modules/gallery/admin/views/form.php` from this release: it is an intentional compatibility adapter.

Verify **Nyt galleri**, **Rediger galleri**, gallery saving, media selection, sort order, delete confirmation and frontend display. No SQL import needed. Treat as a candidate until local approval.
