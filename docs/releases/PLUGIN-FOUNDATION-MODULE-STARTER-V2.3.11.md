# V2.3.11 — Plugin Foundation & Module Starter

Base: V2.3.10a. No SQL changes, no frontend or admin CSS changes, no rewrites of Blog, Forms or Gallery.

## Delivered

1. **Module Starter:** inert templates in `module-starter/`, with module entry, manifest, admin view, 5 translations and migration guidance.
2. **Developer CLI:** `php core/cli/module.php make <slug> [--name="..."]` generates a plugin in `modules/`. `validate <slug|all>` validates module metadata without PHP module execution or database access.
3. **Central static validator:** `ModuleManifestValidator` shared by the CLI and runtime ModuleManager to avoid drift; validates types, slugs, versions, class/entry names, entry containment and media references.
4. **Opt-in hooks:** `ModuleHooks` registry and `ModuleContext::onAction/onFilter`; `ModuleManager::doAction/applyFilters` and built-in `modules.booted` action. No existing modules have been refactored to hooks.
5. **Architecture contracts:** `docs/design/PLUGIN-MODULE-CONTRACT.md` and `module-starter/README.md`.
6. **CMS version:** updated to 2.3.11.

## QA acceptance

- All existing plugin manifests validate.
- Generator produces a correctly structured example and does not overwrite an existing plugin.
- Generated plugin can be PHP-linted, discovered, and its class implements `ModuleInterface`.
- Hooks respect owner, priority, registration order and exception isolation.
- No database migration, HTML/CSS redesign, frontend template changes or automatic plugin installation.

## Installation

Replace CMS files from V2.3.10a, preserve `.env`, database, uploads. No migrations needed. Visit Admin → Modules, and test existing plugins and an independently generated example on a development copy. Do not install untrusted third-party PHP packages.

## Scope limitation

No ZIP upload-and-install workflow or WordPress marketplace feature is included. A full interactive test against the user's MySQL/Apache setup remains necessary.
