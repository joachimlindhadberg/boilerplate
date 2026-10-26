# Plugin Foundation & Module Starter — Core Contract (V2.3.11)

**Aktuel politik (V2.4.4z7):** `CORE-UI-STANDARD-V1.0.md` er bindende for modul-admin; `DATABASE-AND-RELEASE-POLICY-V1.0.md` beskriver nye manuelle SQL-opdateringer, eksisterende CLI-PHP-migrationer og forskel på udviklings-/produktionspakker.

## Ownership and boundaries

- **CMS Core:** owns authentication, permissions, routing/dispatch, admin components, theme independence, migrations, common integrations and translation infrastructure.
- **`module-starter/`:** inert development template. Never discovered or activated.
- **`modules/<slug>/`:** installable runtime plugin folder, discovered from `module.json`.
- **Theme:** owns **100% of frontend CSS/presentation**. Modules may render semantic frontend markup and behavior, but never frontend styles or inline `<style>` tags.
- **Core stylesheet:** owns all shared admin buttons, inputs, modals, panels, tables, tabs, toolbars, pagination, spacing and typography. A module may add minimal structural-only rules for its own unique editor, but must not override Core component styling. SCSS and compiled CSS must stay synchronized.

## Required module manifest

Example:

```json
{
  "name": "Announcements",
  "slug": "announcements",
  "version": "1.0.0",
  "description": "Simple announcements",
  "author": "Vendor",
  "requires": "2.3.11",
  "requires_php": "8.3.0",
  "entry": "AnnouncementsModule.php",
  "class": "AnnouncementsModule"
}
```

The manifest validator rejects missing/wrong field types, invalid slugs/versions, entry paths outside the module root, entry symlinks and malformed `media_references`. Validation reads metadata **without executing module PHP or touching the database**. The entry class must implement `ModuleInterface` and is checked when activated.

Optional `media_references` are declared with `{ "table": "table_name", "column": "media_id", "label": "Assets" }`, and are used even for deactivated modules. A database FK with `ON DELETE RESTRICT` should also be used wherever possible.

## Module lifecycle

1. Generate with `php core/cli/module.php make <slug> [--name="Display Name"]`.
2. Validate metadata with `php core/cli/module.php validate <slug|all>`.
3. Manually deploy the vetted module folder and visit **Admin → Modules**, which explicitly synchronizes the registry. Ordinary requests do not write module metadata.
4. Check `php core/cli/migrate.php --status` read-only; apply **existing pending PHP migrations only after a planned backup/approved upgrade**. For **new** schema requirements, deliver/test/import a separate manual `.sql` script first, and document verification **before activation**. Never assume the runner tracks manually imported SQL.
5. Activate with the existing authenticated, CSRF-protected Modules administration. Only active modules load runtime PHP and call `boot()`.
6. Deactivation stops normal routes, menu entries, components and hooks. **It does not delete content or database tables.**

Modules never run DDL in `boot()`, constructors, web controllers or frontend requests. Migrations remain idempotent and are run only using the CLI. No automatic rollback.

## Runtime interface

`ModuleInterface` remains deliberately small:

- `boot(ModuleContext $context): void`
- `adminMenu(): array`
- `adminRoutes(): array`

`ModuleContext::renderAdmin()` wraps module views inside the global `<main class="admin-main" id="admin-main">` layout. Modules should supply a titlebar within the view and reuse Core components (including the secondary workspace sidebar when appropriate). Never render an extra `<main>`.

Optional interfaces already supported: `ModuleComponentProviderInterface`, `ModuleFrontendActionInterface`, `ModuleFrontendScriptProviderInterface`, `ModulePublicPageInterface`, `ModuleSitemapProviderInterface`, `ModuleScheduledContentProviderInterface`. Keep optional contracts out of simple modules until needed.

## Actions & filters (new, opt-in)

During `boot()`, a module may register callbacks with:

```php
$context->onAction('modules.booted', static function (): void {
    // Called after all active modules have booted.
});
$context->onFilter('custom.label', static fn(string $text): string => strtoupper($text));
```

Core/integrations can dispatch through the shared `ModuleManager`:

```php
$manager->doAction('custom.saved', $itemId);
$label = $manager->applyFilters('custom.label', $label);
```

Hooks are **request-local**, are owned by an active module, are sorted by priority then registration order, and are discarded on deactivation or boot failure. Exceptions are logged and isolated (for filters, the preceding value is retained). Hook names are validated. Do **not** assume generic Core lifecycle hooks exist: `modules.booted` is the only built-in event emitted in V2.3.11. Future domains must explicitly define their event names/argument contracts.

Hooks are **not a security sandbox**: administrators should only install trusted PHP plugins.

## Compatibility and testing

- The remaining Forms, Gallery and Example modules must pass manifest validation unchanged.
- Generating an existing slug must fail without altering installed files.
- `validate` must work without MySQL and never execute a module class.
- Invalid manifests and symlinks must be rejected safely.
- Test module discovery, activation, navigation, permissions, routing, translations and migration status separately.
- **Production** ZIPs exclude tests/fixtures, transient generated modules and local credentials. **Development/local-test** ZIPs include test suites but never secrets, production data or private uploads.

## Explicitly out of scope

No web ZIP upload/installer, plugin marketplace, auto-updates, dependency resolution, auto-migrations or automatic uninstall/data deletion. Do not claim feature parity with WordPress plugins. These can be added later after a dedicated security and compatibility review.

## V2.4.1 authorization and request rules

New module routes and admin menu registrations **must** provide an explicit known `capability`. Unknown or missing rights are denied. `module.json` may declare module-owned capabilities, e.g. `"announcements.manage": ["admin", "editor"]`. See [`AUTHORIZATION-REQUEST-CONTRACT.md`](AUTHORIZATION-REQUEST-CONTRACT.md) for naming, role rules, POST CSRF and typed validation/escaping. Existing legacy module routes retain their explicit Core capability permissions.

## V2.4.2 lifecycle extension

`ModuleInterface` remains backward compatible. Optional `ModuleLifecycleInterface` adds explicit `onActivate`, `onDeactivate` and `onUpdate`. `modules.installed_version` is now a confirmed version, not automatically overwritten on registry sync. Deployed version changes require manual migration checks and update confirmation. See [`PLUGIN-LIFECYCLE-HOOK-CONTRACT.md`](PLUGIN-LIFECYCLE-HOOK-CONTRACT.md).
