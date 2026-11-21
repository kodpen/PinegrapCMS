---
name: customer-rhythm-store
description: Table customer_rhythm and its nightly pass (customer_rhythm_pass.php): a row, when a stage moves, the product summary, export and erase, the 200k-order timing
module: customer_rhythm_pass
---

## What is stored?
- Table `customer_rhythm` (step 11.0.262), one row per Customers Report key (`pg_customer_value_key()`); columns as decided in `workflow/decisions-made-schema-40.md`. No name, e-mail or address; a buyer with only marketplace orders gets no row.
- Stage codes: `pg_customer_rhythm_stage_codes()` (0 none, 1 Regular, 2 Due, 3 Late, 4 Slipping away, 5 Lost, 6 Won back). `interval_days` is whole days (0 = none), `chance` a per cent, amounts minor units of the base currency.
- Product summary: config_text `customer_rhythm` (`pg_customer_rhythm_summary()`): `at`, `median`, `curve`, `customers`, `stages`, `products_all`, `products`, `reminders`, `returns` (id => days per unit, pairs, customers, 1 when from its group). Over 60,000 bytes keeps the products with most pairs.

## How does the pass run?
- `job.php` calls `pg_customer_rhythm_job_run(pg_cron_time_left(20))` on a commerce site; due once a day after the summary's `at`. Claim, lease and progress as the Customers Report: config_text `customer_rhythm_pass`.
- Walk: `pg_customer_value_page_query()` pages (2,000 orders) with their active `order_items` lines; only counting orders of a placed customer are kept, packed per key in `data/temp/customer_rhythm.state` between runs.
- Write: store median, curve and product rhythms at the pass start (`pg_customer_rhythm_pass_store()`), then rows in batches of 500 by `INSERT … ON DUPLICATE KEY UPDATE`; `reminded_at`, `held_back`, `came_back_order_id` are never in its write statement; the tell step writes the first two (`customer-rhythm-events.md`), the came-back statement the third (`customer-rhythm-report.md`). At the end rows the pass did not write are deleted and the summary saved.

## When does a stage move?
- `pg_customer_rhythm_pass_row()`: a move is an existing row whose stage changed; `stage_before` and `stage_at` keep it. A new row, or a row going from no stage to one, is not a move.
- H7: between orders, if yesterday's stage worked out under today's curve already equals today's, the curve alone changed it and the stored stage stays. Tested.
- The pass file announces nothing itself (tested): each written batch goes to `pg_customer_rhythm_tell()` (`customer-rhythm-events.md`).

## What do export and erase do?
- `pg_privacy_customer_keys()` gives a person's keys from their addresses and contacts. Export section `customer_rhythm` (after `stock_notify`); erase deletes the rows by key or `contact_id`; contact merge moves `contact_id`. Inventory line: retention `orders`.

## What did the timing give? (2026-11-20, sandbox MariaDB, PHP 8.3)
- 200,090 orders (400,355 lines, 500 products), 53,597 customers: whole pass 4.8 to 6.5 s in one run, peak about 49 MB above loading; state file 11 MB; summary 14 KB. Run in 1-second budgets: 5 to 7 runs, same rows.
