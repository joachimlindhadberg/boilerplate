# Scheduled Content — Core + Module Contract (V2.3.10)

**Aktuel pakningsregel:** Udviklings-/test-ZIP må indeholde tests; **produktions-/deployment-ZIP** udelader tests/fixtures. Se `DATABASE-AND-RELEASE-POLICY-V1.0.md`.

## Ownership

1. **Core** owns the schedule aggregation, Dashboard, general admin list/filter UI, authorization, timezone display and pagination. The Dashboard is *read-only* and shows up to five entries. Core must never directly query module-owned schedule tables.
2. **Modules** own their own scheduling schema, form inputs, save validation, background job and public visibility rules. Modules must not produce HTML/CSS for the Core schedule list.
3. Modules opt into `ModuleScheduledContentProviderInterface`, which is detected on active module instances by `ModuleManager::scheduledContentProviders()`.

## Provider interface

- `scheduledContent(string $scope, int $limit): array` returns arrays with `type` (lowercase slug), `title`, `action_type` (`publish` or `unpublish`), `due_at_utc` (`YYYY-MM-DD HH:MM:SS` in UTC), and `edit_url` (root-relative `/admin/…` URL). Limit the results at the provider and order them by `due_at_utc` (ascending upcoming, descending overdue).
- `scheduledContentCount(string $scope): int` returns the number of **actions**, not distinct content entities.
- `runScheduledContent(int $limit): int` processes due actions, idempotently and under a lock/transaction that prevents duplicate execution. A job that is already past *both* publish and unpublish timestamps must never expose expired content.
- `$scope='upcoming'` means future UTC. `$scope='overdue'` means before UTC now minus 10 minutes. An active job is always checked for **due** (`<= now`) regardless of reporting scopes.

## Runtime and failure rules

- Explicit CLI migrations only. Do not `CREATE/ALTER` any table from request-time module `boot()` or worker code.
- A failing registry/provider must be logged and the admin overview must show **unavailable**, never silently present an empty/safe state.
- Cron executes Pages and each active module provider, and must be safe to repeat. The original Page cron path remains a supported compatibility wrapper.
- Content scheduling does not grant publishing permission to unauthorized visitors; admin GET views use Core capability checks and only owners' admin edit routes.
- Only the shared admin Core stylesheet may style schedule lists. No module-specific overrides or inline CSS.
- Limit the full list to 10 per page, and dashboard to 5 entries. The currently capped 2,000-action fetch must signal truncation; implementing scalable cursor-based aggregation is a later optimization.
- Use the CMS native confirmation modal for any destructive action; schedule overview itself must not mutate data.

## Regression requirements

- Unit-test merge/sort/count, invalid provider items, cancel/update, race-safe worker execution, expired window, and translation coverage.
- Verify desktop and mobile layout and global table/actions styles.
- Verify the Pages editor, scheduled cron, frontend visibility and Sitemap. Future modules implementing the provider interface require their own tests.
- Preserve existing CRUD, database history, activity log, module activation, backup and migrations. No test folders in the **production** distribution ZIP.
