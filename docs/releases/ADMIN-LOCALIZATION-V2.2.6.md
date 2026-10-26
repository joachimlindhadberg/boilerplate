# Boilerplate V2.2.6 – Admin Language Menu & Translation QA

## Base and scope

Based exclusively on **Boilerplate V2.2.5**. This is a non-database UI/localization bugfix. English remains the default language. All public website content, URL routes, scheduled publishing data, and existing configuration are unchanged.

## Fixes

- The profile language dropdown **does not list the current system language twice**. It displays `English (system default)` or `Dansk (systemstandard)`, depending on the configured system default and current interface language. Other supported languages remain options. The stored system-inheritance value remains an empty preference (`NULL` in the user record). This works even when the system default is changed from English to Danish.
- The account dropdown closes when the user clicks outside of it or presses Escape. Interaction with its own select/form does not close it; Escape restores keyboard focus to the account summary.
- Settings: `Website URL *` is translated to `Hjemmeside URL *` in Danish, and the Tagline label becomes `Undertitel`. APP_URL explanatory text also follows the admin language.
- Additional admin localization cleanup in core form labels, help text, media upload/error labels, navigation filters and placeholders, Page Builder/editor labels, Blog and Forms admin, accessibility text and password reset view. This release does **not** translate visitor-supplied content or the public site's HTML.
- Both core language catalogs now contain identical translation key sets (previously several English-only JavaScript messages would fall back to English on Danish installations). All static `t(...)` calls in admin PHP templates have corresponding English and Danish keys.

## Other languages: research/recommendation (not implemented)

For a broad international audience, prioritize **German (`de`), Spanish (`es`) and French (`fr`)** in that order or based on actual user demand. W3Techs' September 2026 website-content breakdown lists German about 5.9%, Spanish about 6.0%, and French about 4.5% compared with Swedish about 0.5% and Norwegian Bokmål about 0.2%. These percentages concern **website content languages**, not CMS administrator preferences; they are only a directional signal. For a Denmark/Nordics-focused user base, consider **German, Swedish and Norwegian Bokmål** instead.

Sources: https://w3techs.com/technologies/history_overview/content_language and European Commission 2024 Eurobarometer: https://ec.europa.eu/commission/presscorner/api/files/document/print/fen/ip_24_2686/IP_24_2686_EN.pdf

Do **not** add a new language to the dropdown until the core and all shipped module UI strings have been translated and checked, including modal/JavaScript messages and accessibility labels. Current supported languages are only **English and Danish**.

## Upgrade

No SQL migration. Back up the project, replace the code with V2.2.6, keeping the existing database, `.env` configuration and media uploads. The supplied archive preserves regular file mode `0644` and directory mode `0755`, including `.htaccess`.

## Checks completed

- PHP lint across all application PHP files; JS syntax checks.
- English and Danish settings screen render checked for the Website URL label.
- Profile dropdown render checked for inherited and explicit locale preferences with both EN/DA system defaults; exactly one selected option and no duplicate default language.
- Chromium tested opening the profile menu, using an internal select, outside click closing, Escape closing and returning focus.
- Static checks of admin PHP `t(...)` translation keys against shipped catalogs.
- Admin SCSS and compiled CSS kept byte-for-byte unchanged/synchronized.
- ZIP integrity and Unix permission inspection.

## Follow-up

The UI render and interaction checks were isolated tests. Run a final local logged-in browser regression: Settings, Pages/Page Builder, Media, Blog, Forms, Gallery and account language preferences. Full MySQL/end-to-end testing in the user's environment remains outside these isolated checks.
