---
name: erp-stock-days
description: Daily stock record behind Stock Planning (erp_stock_days, 11.0.249): which sales count, bundles as components, sparse rows, night pass, 14-day recount, 420-day fill
---

## What is recorded? (PR #10612)
- `erp_stock_days` (`product_id`, `day` DATE, `sold`, `stock` NULL = unknown; PRIMARY (`product_id`, `day`), `idx_day`), step 11.0.249 `upgrade_2026_11_0_erp_stock_days()`.
- Per stock-tracked product (`inventory = 1`, not a bundle) and site day, a row only when it sold or its stock was 0 at the night pass. A day with no row after the record began was in stock and sold nothing.
- Units: `orders` at `status IN ('complete','exported')` of every `type` by `order_date`, and sales invoices (`direction = 'sales'`, `doc_type = 'invoice'`, issued/partially paid/paid) with `order_id = 0` by `issue_date`. An order's own invoice is the order. Carts, cancelled orders, drafts and cancelled invoices never count; a return is not taken off.
- A bundle line counts as its components: the snapshot in `order_item_components` / `erp_invoice_item_components` (units per bundle), else the bundle's contents today. The bundle has no row.
- The day is the site's (PHP's zone, set by `init.php`); `erp_stock_plan_day_bounds()` takes a 23- or 25-hour day across a clock change.

## What does the night pass do?
- `erp_stock_plan_job.php` (catalogue `erp_stock_plan_job`, hourly, light lane; the script decides): the first tick of a day records yesterday with the stock read now, counts the 14 days before today again, deletes rows older than 430 days, then marks the day; then the forecast is rebuilt (`erp-stock-plan-forecast.md`). Later ticks that day are one read.
- The night's write fills only an unknown stock (`IF(stock IS NULL, …)`); the recount and the fill write `stock = stock`: a past day's stock is never rewritten. A recounted day that sold nothing keeps its row only for a stock of 0.
- First runs: the sold units of the 420 days before the record began (365 until #10654, which widened it with the 430-day keep so the season's last-year windows, 420 days back, are held), stock unknown, in 30-day slices going back; `pg_cron_time_left(60)` ends a tick and `pg_cron_due_again()` brings it back in a minute. `pg_cron_ran()` only at a finished run.
- With the Accounting module off nothing is written (`erp_stock_plan_run()` reason `off`); the run is still stamped.
- A night the job did not run has no stock: the recount gives its units with stock unknown.

## Where is its bookkeeping?
- Marks in `cron_runs` (no config column), a day as YYYYMMDD in `last_run_at`: `erp_stock_plan_began` (the day the record began: the first night recorded, read by `erp_stock_plan_began()`), `erp_stock_plan_fill` (the earliest day filled), `erp_stock_plan_filled` (1 when the fill is done), `erp_stock_plan_day` (the last night recorded).

## How does the job get scheduled?
- The step adds `erp_stock_plan_job` to `config.job_dispatch` when the general job dispatches, so the history starts the night after the upgrade; saving Accounting with the module on does the same (`erp_stock_plan_job_switch_on()`).

## What is not verified?
- No database in the session that wrote it: the step's double run and the job against real orders were not run there.

Tests: `tests/erp_stock_plan_test.php`.

Related: docs/memory/erp/erp-stock-planning.md, docs/memory/erp/erp-stock-moves-and-cost.md, docs/memory/commerce/product-bundles.md, docs/memory/platform/scheduled-job-resume.md
