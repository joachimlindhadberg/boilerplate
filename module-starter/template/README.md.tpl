# __MODULE_NAME_TEXT__

Generated from the Boilerplate V2.4.4d Module Starter.

- Slug: `__MODULE_SLUG__`
- PHP class: `__MODULE_CLASS__`
- Admin route: `/admin/__MODULE_SLUG__`

## Workflow

1. Run `php core/cli/module.php validate __MODULE_SLUG__`.
2. In **Modules**, synchronize the registry and activate this module.
3. For future database changes, supply a separate `.sql` file for phpMyAdmin and import it after a backup and before activation.
4. Implement business logic in module-owned files. Use core admin components in views.
5. Keep global backend styling in Core and frontend presentation in the theme.

No SQL import is required for this starter module. Future schema changes must be supplied as new, individually documented, phpMyAdmin-importable SQL files; do not overwrite a previously applied script.

Module admin translations live in `lang/translations.json` with all five language values per key. Run `php core/cli/translations.php` for validation.

## Permissions and requests

This module declares `__MODULE_SLUG__.manage` for admin and editor in `module.json`. Review this grant for sensitive features. Every admin route needs its own explicit capability; a missing capability is denied. Admin POST requests must carry `csrf_token` and be authorized for the operation. New handlers should validate with `AdminRequest::form()` or `AdminRequest::query()` and use `AdminEscaper` to safely render output. See the Core `docs/design/AUTHORIZATION-REQUEST-CONTRACT.md`.

## Optional lifecycle callbacks

To run logic only during explicit activation, deactivation or manual update confirmation, implement `ModuleLifecycleInterface` alongside `ModuleInterface`. Add `onActivate(ModuleContext $context)`, `onDeactivate(ModuleContext $context)` and `onUpdate(ModuleContext $context, string $fromVersion)` methods. Do not run DDL or remove data from these methods. Install migrations with the Core CLI before confirming an update. See `docs/design/PLUGIN-LIFECYCLE-HOOK-CONTRACT.md` in the parent CMS.
