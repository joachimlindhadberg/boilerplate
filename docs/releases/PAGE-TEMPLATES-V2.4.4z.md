# Boilerplate CMS V2.4.4z – Page Templates

**Base:** locally approved V2.4.4y5a3 (`2.4.4.46`). **New version:** `2.4.4.47`.

## Scope

Five read-only Core JSON presets: Forside, Om os, Ydelser, Kontakt, Landing Page. They insert *normal Page Builder* top-level sections and V2 equal-width Rows, all editable through the existing editor. No new frontend template engine, database tables, module style overrides, or changes to page metadata.

The Templates modal uses the common Core modal shell, with a compact selector left and a CSS wireframe preview right (stacked responsively). Header/footer remain fixed; the body scrolls. Keyboard and cancel paths do not alter content. Existing Page Builder sections, **including empty Rows**, require explicit replacement confirmation; the initial HTML/Legacy mode is never implicitly switched. The user must save the page afterward. The system-generated new `section-######` IDs are checked for uniqueness both in the editor and on save.

The Contact preset contains an empty form column; it does not reference an unknown Form record. Images similarly have no fake media IDs.

## QA / upgrade

No SQL change. Update all files using the complete ZIP, keeping local `.env`, database, and uploads; ZIP root `boilerplate/`, 0644 files and 0755 directories, current modification times for OPcache. Test insertion, edit, sort, save/reopen, template cancellation, replacement confirmation, mobile list/preview, and existing Pages/Forms/Gallery regressions.
