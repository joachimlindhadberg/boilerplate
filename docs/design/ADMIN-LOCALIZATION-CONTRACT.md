# Administration Localization Contract — V2.4.4d

## Responsibilities and scope

- English (`en`) is the default backend language, distinct from the public site's `language`/locale. Never translate public content, routes, database values or stored statuses as a side effect of a backend language change.
- Core supplies one private `core/lang/translations.json`; each module owns one private `modules/<slug>/lang/translations.json`. These paths are outside `public_html`.
- JSON format is **translation key → language code → translation**. Supported languages are `en`, `da`, `de`, `sv` and `nb` (Norwegian Bokmål). Every shipped key must have a string value for all five languages. The text for a key must use identical placeholders (`{count}`, `{name}` etc.) in every language.
- Prefer stable keys such as `t('common.save')` or `t('forms:fields.required')` for all new strings. Historic source-text lookup keys remain supported for compatibility; migrate them to stable identifiers gradually as their views are changed for other reasons.
- `t()` delegates to `Translator::get()`. Prefixes like `forms:` resolve to that module's private catalog; without a prefix, lookup uses Core. Do not let a user submit paths, raw locale names outside the fixed allowlist or arbitrary module directories.
- Missing/invalid individual translation values fall back to English and then the lookup key. Malformed JSON is logged privately and missing values return their keys, rather than executing language PHP files or crashing a page. **Production deployment must pass localization QA**, since a broken catalog can leave untranslated labels.
- Translator caches each JSON catalog once per PHP request; changing locale does not require reading another file. The default locale is the global setting, overridden by a supported per-user preference.
- `Translator::get()` interpolates placeholders; templates must still HTML-escape values at render time. JavaScript continues to use the existing `window.cmsTranslate(key)` dictionary generated from `t()` in the shared admin layout. Never place user-generated text in the dictionary.

## JSON example

```json
{
  "common.save": {
    "en": "Save",
    "da": "Gem",
    "de": "Speichern",
    "sv": "Spara",
    "nb": "Lagre"
  }
}
```

## Module workflow

1. Add `modules/<slug>/lang/translations.json` with all five locales for every key.
2. Call `t('slug:key')` in admin views and escape at output (`admin_e(t(...))`). Core UI strings belong in Core only.
3. Check the catalog before deployment with `php core/cli/translations.php`, plus the broader `php core/cli/qa.php` (both read-only).
4. Use Core admin styling and theme-owned frontend styling. Do not use admin-language selection to translate frontend content.

## Locale precedence

Supported user `users.admin_language` → supported global `settings.admin_language` → English.

The user's selector is in the account dropdown; the global default is under Settings → Language & Region.

## Validator contract

`php core/cli/translations.php` validates Core and every installed module's JSON catalog, checks all five string translations per key and reports differences in placeholders. Non-zero exit code means the package is not ready for deployment. `php core/cli/qa.php` integrates the same checks. These checks do not require MySQL or write anything.

Legacy `.php` language files are no longer loaded or packaged. Update custom modules to JSON before deploying this version.
