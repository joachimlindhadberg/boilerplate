# V2.4.1 — Authorization & Request Contracts

**Base:** V2.4.0 (release candidate). **Type:** core hardening and plugin development contract. **Database migration:** none.

### Changes

1. New `core/classes/CapabilityRegistry.php` centralizes Core roles and reads validated, opt-in namespaced module capabilities.
2. `Auth::roleCan()` delegates to that policy with unknown-capability default-deny.
3. `ModuleManifestValidator` validates optional `capabilities` declarations.
4. `ModuleManager` refuses module admin menus/routes without a valid, registered capability. Only GET/POST are recognized for admin module routes.
5. `public_html/admin/index.php` verifies session CSRF **before dispatching any plugin-admin POST**. Existing module-level validation remains.
6. `AdminRequest` provides typed, opt-in scalar field validation; `AdminEscaper` provides text, attribute and URL output contexts. `admin_e()` delegates to Core escaping.
7. Module Starter now uses a generated namespaced `*.manage` capability (explicitly assigned to admin and editor), requires CMS >=2.4.1, and documents the new contract.
8. Existing Blog, Forms, Gallery and Example are not rewritten; their explicit legacy route rights remain.

### Installation / local smoke checklist

1. Back up V2.4.0 and the database. Replace files, **preserving `.env`, uploads and database**.
2. **No SQL import / no migration** needed. Clear PHP OPcache or restart MAMP if needed.
3. Test login/logout as admin; then Blog, Forms (including submissions), Gallery, Modules and normal POST actions.
4. Test an editor account: existing content/media remains available, users/modules/settings remain inaccessible.
5. Run `php core/cli/module.php validate all`.
6. Generate a **temporary test module outside production use** with `php core/cli/module.php make example-news --name="Example News"`, validate it, check Module navigation as admin/editor, then remove the unused test module safely. Do not include generated modules in production ZIP.
7. In production, verify the PHP session/CSRF flow, POST handling, and restrictions with browser/devtools; a CLI-only check cannot establish full application security.

### QA boundaries

Static PHP lint, offline manifest tests, generated-module validation, malicious-input tests, and route-guard tests were run without MySQL. End-to-end browser/MAMP/MySQL tests and a penetration test were **not** performed. No UI/CSS or data migrations were changed.

Security notes: namespace manifest permissions are not per-user ACLs. Plugin PHP is trusted code. CSRF is only one layer; enforce authorization per operation when the route contains multiple actions. HTML-rich fields and object-specific permissions are out of scope.
