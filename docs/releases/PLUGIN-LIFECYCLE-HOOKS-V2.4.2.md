# Boilerplate V2.4.2 — Plugin Lifecycle & Hook Contracts

**Base:** V2.4.1 (user-confirmed stable). **CMS version:** 2.4.2.

## Delivered

1. Optional `ModuleLifecycleInterface` with `onActivate`, `onDeactivate`, `onUpdate` callbacks; legacy `ModuleInterface` preserved.
2. Explicit deployed-versus-installed plugin version tracking; registry sync no longer acknowledges plugin upgrades implicitly.
3. `ModuleManager::update()` confirms updates only after migration checks and optional callback success.
4. Modules UI displays pending updates and safe downgrade warnings, with a Core confirmation modal. Requests remain CSRF protected and permission-checked.
5. Core-owned `ModuleHookContracts` catalog documents and validates signatures for `modules.booted`, `modules.activated`, `modules.deactivated` and `modules.updated`.
6. Module Starter/docs updated. All five locales include new UI copy.

## Unchanged

All existing Blog/Forms/Gallery/Example module source files, frontend, admin CSS/SCSS, database structure and previous migrations. No automated ZIP plugin installation or rollback. An update confirmation does not deploy files.

## Local installation / acceptance

1. Back up code, database and uploads; copy the complete V2.4.2 CMS over V2.4.1 while preserving `.env`, data and uploads.
2. No SQL import or new migrations required for the CMS release.
3. Open **Admin → Modules**; existing plugins with unchanged versions should remain active/inactive exactly as before.
4. Test activating/deactivating Example and verify Blog, Forms and Gallery still work; confirm Module Starter `validate all`.
5. In a throwaway test installation, bump a plugin's `module.json` version (do not edit production plugin versions), visit Modules and confirm update appears without automatic database version changes. Run needed migrations and confirm via modal; verify old version was preserved if callback failed.
6. Run `php core/cli/qa.php` and `php core/cli/migrate.php --status` using MAMP PHP with MySQLi.

## Test limits

Static PHP/JS tests, CLI manifest validation and mock lifecycle tests do not substitute for complete browser/MySQL tests against the local installation. The optional callbacks can have side effects that Core cannot undo. Consider full code+database rollback tooling in a separate security-reviewed release.
