# V2.3.10 — Unified Scheduled Content

**Base:** V2.3.9. **Upgrade:** one new module migration; previously pending migrations must also be applied.

## Changes

- Core `ScheduledContent` combines upcoming/overdue actions from Pages and active module providers. Dates are UTC and sorted globally.
- Dashboard still lists **five** upcoming actions, as a **read-only** table, without Edit links or Actions column. Its scheduled metric counts outstanding actions across Pages and active modules. The overdue notice likewise includes provider actions.
- Full `/admin/scheduled-content` view (linked from Dashboard) has Upcoming/Overdue tabs, content-type/action filters, 10 results per page, and Edit links to the owning content editor. Filter parameters survive pagination. All markup is inside `admin-main` and uses global admin styles.
- New optional `ModuleScheduledContentProviderInterface` permits future modules to supply their own actions and execute jobs without introducing their tables into Core.
- Blog supports scheduled publication and unpublication. Its schedule lives in `blog_article_schedules` and is saved in the *same transaction* as the article and tags. Removing the times cancels the schedule. Editing/canceling the article preserves existing URLs, other fields, and images. Deleting an article cascades its schedule.
- The worker runs from site traffic or hosting cron. `core/cron/scheduled-content.php` is the canonical cron entry; the existing `core/cron/page-schedules.php` entry remains supported.
- English, Danish, German, Swedish and Norwegian Bokmål admin translations updated.

## Install

1. Back up the V2.3.9 installation, its database and uploads.
2. Deploy the **complete** V2.3.10 release, retaining `.env`, database and uploads.
3. Run from the `boilerplate/` directory using the PHP CLI binary that has mysqli and can connect to your CMS database:

   ```bash
   php core/cli/migrate.php --status
   php core/cli/migrate.php --apply
   php core/cli/migrate.php --status
   ```

4. Confirm `module:blog:20261013_110_blog_schedules` says `applied`. If older Core/Blog/Forms/Gallery migrations are still pending, the command will apply those as well. **Do not** manually reimport already applied SQL migrations.
5. If using scheduled cron jobs, the existing `core/cron/page-schedules.php` command continues to work. New installations should run `php core/cron/scheduled-content.php` approximately once per minute.

**No frontend CSS or database tables are created during normal requests.** Do not modify an existing applied migration file: migration checksums are enforced.

## Verification checklist

- In Blog create a **Draft**, set future `Scheduled publication`, and save. Verify that the schedule is visible on the Blog row, Dashboard and `/admin/scheduled-content`.
- Plan an unpublication for a published post and verify both dashboard aggregation and the read-only content summary.
- Use the full list filters and pagination, and verify Edit leads to the correct Blog or Page editor. Mobile tables must scroll inside the panel.
- Cancel/change the Blog schedule and save; check it disappears/updates on the overview.
- Execute `php core/cron/scheduled-content.php` at the due time; verify the status changes exactly once and activity is recorded. Run it again; there must be no duplicate action.
- Keep all five Dashboard sections/metric cards and the 5-row Recent Activity limit stable.
- Confirm that a scheduled **Page** continues working and the Pages editor still saves both dates.
- A future unpublication is preserved after publication, and a completely expired window never publishes content briefly.
- Temporarily deactivate the Blog module on a test installation to verify no Blog job runs. Reactivate to resume eligible schedules.

## Operational notes

- **Active modules only:** disabled modules do not run their scheduled actions or appear in dashboard aggregation. Their database schedules remain stored and resume on reactivation.
- **Overdue** means still waiting more than 10 minutes after its due time (not automatically evidence of a job failure). Due items in the first ten minutes are neither future nor overdue; the worker should process them.
- The full view loads at most **2,000** actions per scope for controlled memory/response time. When the underlying count is higher a visible warning appears; filter results are calculated from the displayed subset. This is an explicit guardrail, not an unbounded global database report.
- Full database integration, hosting-cron timing and a production-like migration must be tested on the actual MySQL server before designating V2.3.10 stable.
- Backup/download archives contain private data; protect them as described in earlier releases.
