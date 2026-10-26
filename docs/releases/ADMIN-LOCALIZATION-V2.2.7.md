# Boilerplate V2.2.7 — German, Swedish and Norwegian Bokmål

## Base

Based on the existing V2.2.6 admin-localization release. English (`en`) remains the **default backend language**. Previously selected global or personal admin languages are preserved. No frontend translation or content changes are made.

## Implemented

- Three additional administrator languages: German (`de`), Swedish (`sv`) and Norwegian Bokmål (`nb`).
- New complete translation catalogs for CMS Core and the shipped Blog, Forms, Gallery and Example modules, maintaining the same translation keys as English.
- Global default selector under Settings → Language & Region lists all five languages.
- Personal language dropdown lists the system default only once and the other available languages.
- `Translator::supported()` and `Translator::locales()` enforce a defined allowlist; English is retained as fallback for missing/invalid localized values.
- Existing English and Danish catalog texts, user preferences, public URLs and database values remain intact.

## Upgrade

1. Back up files and database.
2. Replace the application code while preserving your own `.env`, database, uploads and configuration.
3. No database migration or SQL import is needed: `users.admin_language` already supports the two-letter codes.
4. Reload admin, select the desired language in the account dropdown, and verify CMS Core, Blog, Forms, Gallery, validation errors and confirmation dialogs.
5. To change the default for all users, use Settings → Language & Region.

## Validation

Static verification checks key-set parity across all five locales in core and modules, placeholder identity, PHP lint, module manifests, and ZIP modes. Core rendering/selection smoke checks are isolated; perform a full local logged-in browser and MySQL regression test before marking the release stable.

No `/tests` directory is shipped. Documentation is under `docs/`; ZIP entry file modes use `0644` and directory modes `0755`.
