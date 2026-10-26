# Boilerplate V2.3.9 — Performance, Code Quality & UI/UX Consistency

**Base:** V2.3.8b (`BOILERPLATE_VERSION=2.3.8.2`). **Version:** 2.3.9.  
**Scope:** Request-level performance, a narrowly scoped extraction of large helper functions, and localization of shared admin controls.  
**Database migrations:** None. **CSS / SCSS:** Unchanged and still identical. **Frontend theme:** Unchanged.

## Changes

### 1. Modules registry is read-only on normal requests

- `ModuleManager::__construct()` no longer synchronizes discovered manifests into the database. An ordinary public/admin request therefore does not write to `modules` simply by constructing the manager.
- `ModuleManager::syncRegistry()` performs registration explicitly in **Admin → Modules** (which requires `modules.manage`). `activate()` also calls it to support existing programmatic activation paths.
- The registry data is cached for one manager instance/request and invalidated after activation, deactivation, or registration. This removes repeated reads within the same request, without cross-request cache staleness.
- Active modules retain **slug-sorted boot order**, and only active modules execute their PHP entrypoint. Inactive/missing modules remain visible on the Modules overview. New module files become registrable when that overview is visited.
- **No changes to module manifests, permissions, routes or migration semantics.**

### 2. Other low-risk request optimizations

- Public/bootstrap settings now use the existing per-request `model('settings')` instance. Frontend helpers reuse that same instance rather than initializing a redundant Settings instance.
- The dashboard calculates published and draft page totals in one SQL aggregate rather than two separate `COUNT(*)` queries. Semantics and the maximum **five recent activities / five scheduled items** are unchanged.
- Declarative module-media reference manifests are parsed once per request per directory rather than on every lookup. Failures are never cached: media deletion still fails closed if a manifest cannot be safely parsed.

### 3. Code quality

- Extracted the **original, byte-for-byte identical** admin Page Builder helper functions from `public_html/admin/incl/functions.php` into `public_html/admin/incl/page-builder-functions.php` via `require_once`.
- All existing helper names, output markup, and callers are preserved. The file extraction is a maintenance change only; no Page Builder form structure, JavaScript, database logic or frontend behavior changes.

### 4. UI/UX and localization

- Shared admin pagination now translates **Previous / Page X of Y / Next** on all five locales (`en`, `da`, `de`, `sv`, `nb`) without changing URLs, pagination, buttons or layout.
- Removed selected hardcoded Danish strings from the Navigation overview / editor and Forms submissions. Translation keys and escaped markup now cover overview descriptions, unassigned menu placement, move-button tooltips, menu item types, unpublished-page labels, and read/unread submission actions.
- The admin styling contract and theme ownership are unchanged. No module CSS overrides, no new UI controls, no dashboard Edit buttons.

## Installation

1. Back up your **working V2.3.8b** code and database first.
2. Update the CMS files to V2.3.9, preserving local `.env`, database and upload files.
3. No SQL import, CLI migration or CSS rebuild is required for this release.
4. Hard refresh the admin, then visit **Modules** once to synchronize available module manifests.
5. Smoke-test **Pages and Page Builder**, **Blog**, **Forms → Submissions**, **Gallery**, **Navigation**, **Users**, **Modules**, **Dashboard**, and public site. Verify pagination and labels in at least Danish and English.

## Tests performed in packaging environment

- PHP syntax checked for every PHP file; JavaScript syntax checked via `node --check`.
- Fake-DB module registry tests verify startup performs no database queries or writes, that registration is explicit/idempotent, rows are cached and invalidated, and boot does not write.
- Byte comparison against V2.3.8b verifies the moved Page Builder function bodies are unchanged.
- Static regression checks verify unchanged database/migration files, module implementations, scheduler, dashboard view, and compiled/core SCSS, plus a right-aligned readonly Dashboard contract.
- Translation catalogs have identical keys on each of the five languages; pagination strings and Forms/Navigation strings have been reviewed and smoke-tested.
- ZIP integrity and Unix `0644` file / `0755` directory permissions are checked before delivery. Test harnesses remain **outside** the release ZIP.

## Constraints and follow-up

- Tests above are **unit/static smoke tests**. No live Apache/MySQL integration test or production-scale benchmark has been run in this environment. Performance gains have been verified as fewer code-path queries, **not** as measured milliseconds at load.
- The scheduled-content fallback still checks due work on public traffic; its behavior is deliberately unchanged to avoid publication timing regressions. Keep the documented minute-based cron in production.
- Media usage's full scan of Page Builder JSON has **not** been replaced: a safe relational dependency index needs a separately tested migration/backfill and is out of scope for this version.
- The large Forms module is **not** mechanically split in this release, because its controller and submissions logic recently suffered regressions. A future split should follow integration tests across create/edit/delete and submissions.
- V2.3.9 is ready for local validation; do not promote it to production-stable until the live flows have been tested on the user's installation.
