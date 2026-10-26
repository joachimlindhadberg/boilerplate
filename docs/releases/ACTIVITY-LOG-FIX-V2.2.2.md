# Boilerplate V2.2.2 – Activity Log pagination fix

## Fixed

- The Activity Log list used `$paging['per_page']` (a nonexistent key) instead of the shared `admin_crud_pagination()` key `$paging['perPage']`. This warning could also produce a caught TypeError that falsely suggested a missing migration even when `activity_log` already existed.
- Non-schema failures now show a generic error and log the underlying exception on the server. A missing-table message is shown only for MySQL error 1146.
- CMS core version updated to 2.2.2; module dependency minimums remain unchanged.

## Installation

Replace CMS files with V2.2.2; retain your existing database, `.env` and uploads. **No SQL migration is required if V2.2.1 has already been imported.**

Test Tools → Activity Log (all filters, search, pagination), Dashboard → Recent Activity, and adding an activity by editing a page.

## QA boundary

PHP syntax and isolated pagination checks have been run. Complete browser/MySQL regression tests must still be confirmed on the target installation.
