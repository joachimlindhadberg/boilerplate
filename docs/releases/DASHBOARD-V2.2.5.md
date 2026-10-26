# Boilerplate V2.2.5 – Dashboard Improvements

## Base and scope

Based exclusively on the user-approved V2.2.4 ZIP with corrected Unix permissions. Dashboard-only enhancement; **no schema change, no SQL migration**. Forms, Gallery, Blog, frontend and core navigation remain unchanged.

## Dashboard layout

1. **Needs Attention** is the first section, and is hidden when no concrete overdue jobs are found. The initial implementation detects pending `page_schedules` records whose planned UTC time is more than 10 minutes in the past; it **does not claim a background job failed**. Up to five affected pages link directly to their detail view. This is not general System Health monitoring.
2. Four linked metric cards: **Published Pages**, **Drafts**, **Scheduled**, **Media**. Scheduled counts pages with at least one future scheduled publish/unpublish operation (one page is counted once even if it has both). Drafts include pages that are also scheduled.
3. Two dashboard widgets displayed **50/50 from 1100px upwards**, stacked at smaller widths: **Recent Activity** (latest 10 completed actions) and **Scheduled Content** (next five future actions ordered by UTC timestamp). The previous Recently Updated Pages panel is removed.

All labels have English and Danish translations. Event timestamps remain stored in UTC and are displayed in the CMS's configured timezone. The widget uses the existing core panel/table components; no modules or frontend styles were modified.

## Failure behavior

- No scheduled jobs: the Scheduled Content widget displays a neutral empty state, and no alert is shown.
- Schedules unavailable due to a database issue: the Scheduled metric shows `—` where appropriate and the widget displays an unavailable message instead of claiming that there are no scheduled jobs. Diagnostics are written to the PHP error log.
- After a schedule has been processed, it disappears from the upcoming and overdue lists automatically. This is a read-only dashboard; it does not run the scheduler itself.

## Installation

Upgrade directly from V2.2.4. Back up the existing application, keep `.env`, uploads and your database; replace the application code. **Do not import `boilerplate.sql`.** No migration required. Hard-refresh the browser (`Cmd + Shift + R`) if CSS is cached.

## Local acceptance tests

1. Check Dashboard layout at desktop (>1100px) and mobile sizes.
2. Create a future scheduled publication and confirm it appears in the metric and Scheduled Content widget; open its linked page.
3. Let an actual scheduled publication/unpublication run and verify that the action disappears from the future list and appears in Recent Activity.
4. If a job stays pending for more than ten minutes past its due time, confirm Needs Attention appears, linking to the affected page.
5. Change admin language between English and Danish. Confirm headings, timestamps, and empty states.
6. Verify Forms, Gallery, Blog, Pages, Navigation and Activity Log still function.

## Verification limits

PHP/JS parsing, styling parity, static SQL construction and isolated data/render tests were performed in development. Full MySQL integration and a logged-in CMS browser test must be performed on the user's installation.
