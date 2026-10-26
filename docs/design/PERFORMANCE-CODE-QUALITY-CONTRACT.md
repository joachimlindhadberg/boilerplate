# Performance & Maintainability Contract — V2.3.9

Applies to CMS Core and every module. Does not supersede the Admin Lists, Backend Styling, Frontend, or Migrations contracts.

1. **Read-only request startup.** Constructors and ordinary public requests must not synchronize module metadata or execute schema/data migrations. Register new modules via authorized admin/CLI processes only.
2. **Request-scoped caching.** Cache stable metadata within a request only, and invalidate after writes. Never cache security decisions or media reference failures beyond their verified lifetime.
3. **No hidden state mutation for speed.** Preserve transactional boundaries, versioned migrations, scheduled publishing accuracy, CSRF/auth checks, and module activation behavior. As of V2.4.3, standard `Db` wrappers explicitly reuse one non-persistent mysqli handle per PHP request; nested transactions are prohibited, and dedicated backup snapshots remain isolated. See `DATABASE-CACHE-CONTRACT.md`.
4. **Measure actual performance.** Favor removal of verifiably duplicate queries and needless manifest/metadata reads. Benchmark under representative MySQL data before introducing query indexes or JSON usage indexes.
5. **Extract code without rewriting behavior.** Extract pure/shared helper functions only after byte-level parity and smoke tests; defer broad refactors of complex stateful controllers.
6. **Admin strings are translated.** Shared controls (including pagination), module labels, status and button copy must use the appropriate Core/module translation keys with HTML escaping at output; keep five locale key sets synchronized.
7. **Respect UI contracts.** Dashboard remains read-only (five activity and five schedule entries), while actual CRUD lists use the shared rightmost Actions column. Core owns all shared admin styles, with matching SCSS/CSS.
8. **Release quality gate.** Check PHP/JS syntax, stable routes, modules, admin CRUD, language consistency, ZIP permissions, dashboard rules and frontend behavior. Keep automated test scripts outside distributed ZIP files.
