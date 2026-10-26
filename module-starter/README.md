# Boilerplate Module Starter (V2.4.4d)

The source template lives outside `modules/`, so it does not appear as an installed module.
The official generator creates a *real*, inactive module with all the mandatory files.

From the project root:

```sh
php core/cli/module.php make announcements --name="Announcements"
php core/cli/module.php validate announcements
php core/cli/module.php validate all
```

After generation, go to **Admin → Modules** (this synchronizes the registry) and activate the module. The initial starter has no database changes. For future database changes, supply a separate `.sql` file designed for direct import in phpMyAdmin, take a backup, and import it before activating or updating the module. Do not add new PHP migrations.

The validator checks *static metadata and required entry file*, without executing plugin PHP or accessing MySQL. A PHP lint (`php -l`) and full functionality tests should also be run before installing third-party code.

## Structure

- `module.json` — name, slug, version, Core/PHP requirements, entry class, optional media references and namespaced capabilities
- `<Name>Module.php` — `ModuleInterface` implementation, `boot`, admin menu/routes
- `admin/views/` — presentation using existing core admin classes
- `lang/translations.json` — five-language module-owned admin translations (stable keys)
- `database/` — optional phpMyAdmin-importable SQL scripts for schema changes
- `README.md` — documentation
- Optional module-owned business logic classes under `src/`, added only when needed

Templates in `module-starter/template` are the source of truth. The CLI does not overwrite an existing module. Names and module slugs are validated; temporary folders are removed if generation fails.

## Plugin constraints

Modules may only execute after Core has loaded them as **active**. They do not modify Core files, define global backend styles, or ship frontend CSS. Admin presentation comes from Core; frontend presentation comes from the active theme. Hooks (`ModuleContext::onAction/onFilter`) are request-local and accessible only after module boot. See `docs/design/PLUGIN-MODULE-CONTRACT.md`.

## WordPress comparison

This is an initial *development foundation*, not yet a WordPress-style marketplace or a ZIP upload-and-install system. Deployment is by copying a vetted module folder into `modules/`. Module versions and database changes are managed separately and there is no automatic rollback or dependency resolver.

## Authorization and request validation (V2.4.2)

Starter modules declare a module-owned `<slug>.manage` capability in `module.json` and use it on their admin menu and route. The generated starter grants that capability to admin and editor, equivalent to the earlier content editor access. Restrict it to `admin` or declare separate capabilities for sensitive actions as the module develops. **Routes missing capabilities fail closed.** Any plugin admin POST needs the session's `csrf_token` (validated by Core and, where appropriate, the module itself).

Validate typed request fields with `AdminRequest::form()` / `AdminRequest::query()` and escape text/attributes/URLs via `AdminEscaper`. Core does not infer authorization from the form content or the CSRF token. See `docs/design/AUTHORIZATION-REQUEST-CONTRACT.md`.

## Plugin lifecycle and hooks (V2.4.2)

For plugins requiring activation/deactivation/update callbacks, implement optional `ModuleLifecycleInterface` in addition to `ModuleInterface`. Keep these callbacks idempotent and do not create tables in web requests. Use the CLI migrations before enabling/updating a plugin. See `docs/design/PLUGIN-LIFECYCLE-HOOK-CONTRACT.md` for hook signatures, update confirmation and limitations.
