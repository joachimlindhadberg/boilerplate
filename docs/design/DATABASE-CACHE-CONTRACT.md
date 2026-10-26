# Database & Request Cache Contract — V2.4.3

This contract applies to CMS Core and every installed plugin. It supplements the Performance, Plugin, Migration, and Security contracts.

1. **One MySQL connection per PHP request (not a persistent pool).** Ordinary `new Db()` instances in one execution share one mysqli handle. Do **not** create separate connections in individual services. SiteBackup deliberately uses a separate mysqli connection to protect its consistent-snapshot transaction. Web requests end naturally, so no manual close is needed.
2. **Transaction ownership.** A transaction belongs to the shared request connection, and nested `beginTransaction()` calls are rejected instead of silently committing an outer transaction. Use the same `Db` instance (via injection) for multi-step operations that must be atomic. Call `commit()`/`rollback()` on every path; never run independently managed transactions inside an outer transaction. `Db::close()` releases only that wrapper; other live wrappers keep the shared handle open. Ordinary web requests need no explicit close.
3. **Insert ID isolation.** `Db::getInsertId()` returns the ID captured by that wrapper's last successful write, not the globally shared mysqli handle's most recent operation. Get the ID after INSERT as usual.
4. **Request-only memoization.** `RequestCache` stores data in PHP memory for the current request. Nothing is persisted to APCu, disk, cookies, or Redis. Do not cache authorization decisions, rate limits, sessions, personal form submissions, or content that must be revalidated for each security decision.
5. **Namespace invalidation.** Settings changes invalidate the `settings` namespace; changes to `navigation_menus`, `navigation_items`, and `pages` invalidate `navigation`. Invalidation occurs only after a successful prepared write. COMMIT/ROLLBACK both clear affected snapshots. Do not bypass `Db::query()` for writes to these tables without explicitly invalidating the corresponding namespace.
6. **No transaction cache leaks.** Read-only memoization is bypassed when the shared connection is in a transaction. Cached snapshots are refreshed after writes, commits and rollbacks. Never publish uncommitted data into a request cache.
7. **Safe caching scope.** Global site settings (`autoload=1`) and frontend navigation data may be cached in-request. `Settings::all()` remains a live DB read. Other cache candidates require an explicit list of dependencies and invalidation rules before being enabled.
8. **Test and measure.** Benchmarks must distinguish isolated mocked connection/query counts from real MySQL wall-clock performance. Never claim a latency improvement without measurements on representative data and the target deployment.

## Existing interfaces

- `new Db()`, `fetchArray()`, `fetchValue()`, `query()`, `beginTransaction()`, `commit()`, `rollback()`, `getInsertId()` — preserved.
- `new Settings()` — preserved, with optional `Db` injection for internal consistency/testing.
- `RequestCache::remember($namespace, $key, $factory)` — memoizes even null/false; exceptions are not cached.
- `RequestCache::invalidate($namespace)` — drops values and increments generation so existing Settings instances refresh their snapshots.
- `RequestCache::generation($namespace)` and `RequestCache::clear()` — internal invalidation and test/worker teardown.

## Explicit exclusions

No new SQL indexes, schema changes, object-cache backends, persistent MySQL connections, query-result cache for arbitrary SQL, or plugin-owned global caching layers are introduced by V2.4.3.
