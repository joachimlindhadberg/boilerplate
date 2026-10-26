# Boilerplate CMS V2.4.4d — Translation System Refactor

**Base:** V2.4.4c (2.4.4.3) with permanent Blog removal.  
**Release:** 2.4.4.4.  
**Database:** No database changes. No SQL import required. No PHP migration included.

## Changes

- Consolidated 20 locale-specific PHP files into four JSON catalogs: Core, Example Module, Forms and Gallery. Every catalog groups all five locale values under each key.
- Preserved all 1,161 effective translation keys and their values across English, Danish, German, Swedish and Norwegian Bokmål, including the current source-text compatibility keys.
- `Translator` now loads and caches one JSON catalog per Core/module per request. Locale selection, `slug:key` namespacing, English fallback, placeholder interpolation and HTML escaping at the view boundary are unchanged.
- JSON catalogs are private and cannot execute PHP. Invalid catalogs fail safely with private log output, and the new read-only validator rejects invalid data before deployment.
- Added `php core/cli/translations.php` and integrated localization checks in `php core/cli/qa.php` (missing languages/keys, unexpected locales, invalid JSON, placeholder differences).
- Updated Module Starter to generate `lang/translations.json` rather than PHP language files, and documented module-owned JSON conventions.
- Did not change the frontend, UI stylesheet, page content, JavaScript translation API, existing CMS database, modules' business logic or permissions.

## Update / local acceptance

1. Back up the files and database. Keep the existing `.env`, uploads and database. Replace CMS code with this ZIP.
2. Remove the old `core/lang/{en,da,de,sv,nb}.php` and `modules/{example,forms,gallery}/lang/{en,da,de,sv,nb}.php` from the installation (or deploy a clean copy of the packaged code around the preserved private data). They are obsolete and should not linger in production.
3. From the project root run `php core/cli/translations.php` and `php core/cli/qa.php` (using a compatible PHP 8 CLI). Both commands are read-only.
4. Test admin UI in all five languages: dashboard, Media, Pages, Modules and in particular Forms/Gallery labels, notices, confirmations and placeholders. Switch languages using the user menu and verify flash notices after redirects.
5. Test frontend routes/Forms/Gallery to confirm that admin localization has not leaked into site content.

The three remaining modules are Example Module, Forms and Gallery. Keep V2.4.4c as a rollback backup until this release is locally approved.

## Compatibility note

Custom modules that still supply only `lang/<locale>.php` must be converted to `lang/translations.json`. Unknown keys continue to render as keys; English is used if another locale is missing at runtime, but the validator deliberately marks incomplete catalogs as invalid.

## Verification

- Source catalog counts: Core 1,017, Example 5, Forms 107, Gallery 32; 5 supported locales each, identical key sets/placeholders.
- For release preparation, output parity should be checked against the previous PHP catalogs for all keys/locales and parameter interpolation, in addition to PHP/JS syntax, module generator validation, manifest checks and ZIP integrity.
- MySQL integration, site-specific editor customizations and full browser acceptance require local testing.
