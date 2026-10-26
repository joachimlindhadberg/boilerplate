# Plugin Lifecycle & Hook Contracts — V2.4.2

## Scope and compatibility

This is an additive, backward-compatible contract built on V2.4.1. `ModuleInterface` remains unchanged. Existing modules (Forms, Gallery and Example) are **not** required to implement lifecycle callbacks. No database schema migration, ZIP installer, automatic plugin update, rollback, marketplace or automatic uninstall is included.

## Installed vs. deployed versions

- `module.json.version` is the **deployed file version**.
- The existing `modules.installed_version` is the **last acknowledged activated/updated version**, not an automatically synchronized filesystem version.
- Opening **Admin → Modules** may sync metadata for newly discovered plugins and rename existing plugins, but must NEVER silently move `installed_version` forward.
- A higher `module.json.version` appears as **Update available**; admins must first run the migration CLI, then explicitly confirm **Complete update**.
- A lower deployed version is flagged as a mismatch; no downgrade is performed automatically.
- New plugins are registered inactive at their initial version; activation is explicit. Existing plugins with unchanged files are unaffected.

### Manual deployment/update order

1. Back up the CMS code, database and uploads independently.
2. Deploy reviewed module files manually, preserving existing plugin data.
3. Run `php core/cli/module.php validate <slug>` to validate metadata, and lint the new PHP.
4. Run `php core/cli/migrate.php --status` then `--apply` if required.
5. Open **Admin → Modules**; use **Complete update** to acknowledge the deployed version.
6. Test the plugin routes and frontend after confirmation. If needed, restore the **files AND database from a compatible backup** (Core does not implement rollback).

**Important:** When deployed and confirmed plugin versions differ, Core **skips booting** that plugin until the update is confirmed or the matching files are restored. This temporarily disables that plugin's routes and features, including on the public website. The Update button only validates migration status, invokes the optional callback and records the version; it does not stage, verify checksums or atomically replace files. Untrusted plugin PHP remains fully privileged. Do not treat this as a WordPress-style plugin updater.

## Optional lifecycle callbacks

A plugin may additionally `implements ModuleLifecycleInterface` and implement all three methods:

```php
public function onActivate(ModuleContext $context): void;
public function onDeactivate(ModuleContext $context): void;
public function onUpdate(ModuleContext $context, string $fromVersion): void;
```

These run **only** during administrator-initiated actions, not in `boot()` and not on ordinary page requests. Activation and update require all pending Core/module migrations to have been applied. Lifecycle callbacks receive a context **without request-hook registration**; use `boot()` for registering request-local hooks. No automatic DDL or data deletion is allowed in web callbacks.

Exceptions from optional callbacks prevent the corresponding registration change (`is_active` / `installed_version`). **Callbacks may have already produced other side effects:** use idempotent operations and your own transactions where appropriate. Core does not claim distributed rollback or atomic code deploy. If the entry PHP is missing or invalid, deactivation remains possible as a recovery action.

`onDeactivate()` does **not** delete the plugin's data or files. `onUpdate()` is not a database migration mechanism.

## Hook names and signatures

| Hook | Type | Arguments | Timing |
|---|---|---|---|
| `modules.booted` | action | none | after active modules boot, once per manager/request |
| `modules.activated` | action | `string $slug, string $version` | after successful activation |
| `modules.deactivated` | action | `string $slug, string $version` | after successful deactivation |
| `modules.updated` | action | `string $slug, string $fromVersion, string $toVersion` | after version acknowledged |

`ModuleHookContracts` publishes the Core event catalogue and checks argument count/types on dispatch. Custom plugin hook names are supported and their signatures are the responsibility of their publisher. Only known Core event signatures are enforced; this is not a full schema/type system for arbitrary plugin events.

Hook registration stays **request-local** and **owned by the module**: callbacks use priorities (-1000…1000; smaller first), stable registration order when equal, and are forgotten on deactivation/boot failure. A callback exception is logged and isolated; in filters the previously accepted value is kept. Lifecycle notifications are **best-effort**; they happen after the state transition and never roll back a successful transition. They only reach listeners booted in the current request, not a persistent event bus or queue.

The design intentionally forbids a module shipping global Core admin control styles or frontend CSS. Modules use Core components, and the active theme owns all frontend CSS. SCSS/CSS must always stay synchronized when styling does change.

## Regression checks

- All 4 original manifests must validate unmodified.
- Initial registration, re-open/sync, activation, failed activation, deactivation and failed deactivation.
- Deployed higher/lower versions, pending migrations, failed/successful update callbacks, idempotent confirmation.
- Hook priority, owned callback cleanup, invalid built-in event signatures, listener exceptions.
- POST+CSRF on all state transitions, confirmation modal for update/deactivation.
- No SQL schema changes or frontend changes required by this release.
