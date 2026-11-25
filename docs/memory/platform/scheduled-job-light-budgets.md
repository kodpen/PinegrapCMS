---
name: scheduled-job-light-budgets
description: Which dispatched jobs cap their own time budget by pg_cron_time_left(), and why a light job that does not can shut the whole light lane for an hour
module: cron
---

## Which jobs cap their budget by the time left?
- Heavy lane: `seo_analyze_job`, `seo_a11y_job`, `update_search_index` (`SEO_ANALYZE_TIME_BUDGET`).
- Light lane: `erp_order_profit_job` and `erp_stock_plan_job` (60 s), `shipment_tracking_job` (`PG_SHIPMENT_TRACKING_SECONDS`, 90 s), `api_sync_job` (25 s), `translation_job` (40 s).
- The general job's own passes with a tick budget (customer rhythm, lead score, customer value, delivery tower, imports, supplier feeds) call it too.
- Run alone from the command line there is no limit and the budget is the job's own (`pg_cron_tick_budget()`).

## Why must a light job keep inside it?
- A host runner (`JOB_TIME_LIMIT`) kills `job.php` outright at its limit: no shutdown handler runs, so the dispatched job's lock in `cron_runs` stays until it expires (an hour by default). While any light job holds a lock the dispatcher hands out no light job (`pg_cron_pick()`), so every light job waited an hour, and the killed job, still due longest, was picked again and killed again.
- Fixed for tracking, marketplace sync and translation in the scheduled-tasks bug hunt (#11394): each works on from its place next run (tracking keeps it per parcel).

## Which light jobs still use a fixed budget?
- `email_campaign_job` (recipients claimed one by one; a cut run loses nothing), `workspace_mail_job` (inline in `job.php`, budget 20 s there). Not measured against a short runner limit.

Tests: `tests/cron_job_overlap_test.php`, `tests/cron_resume_test.php`.
