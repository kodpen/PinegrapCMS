---
name: bug-hunt-areas-scheduled-tasks
description: The bug hunt in the scheduled tasks (area 19, #11394): the cron entry and its jobs, what was fixed, what was read clean, what is left
---

## Which area was it?
- Area 19 "scheduled-tasks": `job.php` (the general job), the dispatcher and its locks (`includes/fn/cron.php`) and every `*_job.php` script. To look at: two runs overlapping, a job dying mid-way and blocking the next, the lock's release, a failure reported once, time limits, a table read without a LIMIT. PR #11394.

## What was fixed?
- The general job's abandoned-order pass made two `order_abandoned` campaigns for one order when two ticks overlapped: the customer got the mail twice. It now runs under its own lock (`platform/job-abandoned-orders-lock.md`).
- `erp_stock_alert_job.php` from a crontab line beside the dispatcher, or by hand, rang the low stock and quote bell twice for the same rows: it takes the job's lock like the other ERP scripts (`platform/job-script-claims.md`).
- `shipment_tracking_job` (90 s), `api_sync_job` (25 s) and `translation_job` (40 s) worked their own budget past a host runner's limit; killed outright, the lock shut the light lane for an hour. They cap it by `pg_cron_time_left()` (`platform/scheduled-job-light-budgets.md`).

## What was read and found clean?
- Dispatcher: the claim in one statement, the lane re-check after the claim, the release by stamp only, the stamp before the release, the due-longest pick, the output handler's once-a-day log line.
- Row claims in the queues the general job works: webhooks, ad conversions, the device push queue (a second wake is collapsed by the banner's tag), Workspace reminders, recurrences and the overdue mark, scheduled comments, recurring invoices and expenses, return reminders.
- File locks (`ops_alerts.lock`, geo, share card): `flock`, freed when the process dies.

## What is left?
- The abandoned-order read has no LIMIT and reads skipped orders on every tick for their week: a cursor or window would change who is mailed (`platform/job-abandoned-orders-lock.md`).
- `unpublish_events()` reads every published event with unpublish days, once a day, and stamps its day at the end: two overlapping runs log the same unpublish line twice. Harmless, not changed.
- Not read for overlap: `seo_search_job`, `iys_job`, `google_analytics_job`.
