# Boilerplate V2.2.0 — Admin Localization (English default)

## Upgrade (existing installations)

1. Back up the files and database.
2. Replace CMS code with this release **without overwriting local configuration or uploads**.
3. Import **only** `database/migrations/20261012_admin_localization_v2_2_0.sql` once in phpMyAdmin. Do **not** re-import `boilerplate.sql` into an existing database.
4. Hard-refresh the admin UI and log in.
5. Check **Settings → Language & Region** (English is the system default). In the top-right account dropdown, select **My admin language** → English / Dansk / Use system default, then Apply.
6. Check Login, Pages, Media, Navigation, Blog, Forms, Gallery, validation errors, modals, and existing frontend pages. No module reactivation required.

## Changes

- New core `Translator` service and English/Danish admin language catalogs.
- English defaults for all new and existing accounts unless a personal preference overrides it.
- Individual user preference `users.admin_language`, nullable (inherit). This is the only schema change.
- Global admin language stored in `settings.admin_language`; migration inserts it as `en` if missing.
- Core and module admin views moved to translated display strings. Each installed module contains its own English/Danish language catalogs.
- Shared admin notices and central JS confirmations translated; common server-validation messages have English equivalents.
- Existing public content, frontend locale setting (`language`), page URLs, slugs, role keys, module slugs, and statuses are unchanged.
- No frontend style modifications; shared admin SCSS and CSS remain synchronized.
- No bundled test directory.

## Limitations and QA

The supplied code passes PHP/JS syntax and isolated localization/validation tests. **It has not been run against the user's local MySQL server or tested end-to-end in a live browser.** Some uncommon legacy or third-party module messages may still use fallback text until a translation is added. Module authors should use namespaced stable translation keys for all new UI. This release is **not yet user-validated as stable**.

No commerce functionality was added. The language architecture is suitable for future larger modules such as E-commerce.
