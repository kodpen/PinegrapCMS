---
name: erp-stock-plan-forecast
description: Stock plan forecast (erp_stock_plan, 11.0.251, #10654): units a day, season, supplier, lead time, days left, order-by day, quantity, states, dead stock, the nightly rebuild, erp_accounts.lead_days
---

## What does `erp_stock_plan` hold? (SP2 of plan stock-planning, PR #10654)
- One row per tracked product (`inventory = 1`, not a bundle), a cache rebuilt every night by `erp_stock_plan_run()` after the day's row and once the first fill is done. Columns and types: PR #10654 body; step 11.0.251 `upgrade_2026_11_0_erp_stock_plan()`.
- `notice_at` is the bell's stamp (erp-stock-plan-bell-api.md): the rebuild's upsert never names it.
- Dead stock and its value have no column: `erp_stock_plan_dead()` works them out when read, from `last_sale`, the stock and `erp_product_costs.avg_cost`.

## How is the speed read? (P3, `erp_stock_plan_speed()`)
- The 56 days before today (today's sales are not complete). A day with stock 0 is out of stock and left out with its units; a day with no row was in stock and sold nothing; stock unknown (filled days, a missed night) counts as in stock.
- Days before the record holds anything (`erp_stock_plan_history_from()`) are not counted. Under 14 in-stock days or no sale: null, state `no_history`, quantity 0.

## How is the season read? (P4, `erp_stock_plan_season()`)
- Last year = 364 days back (52 weeks, weekdays line up). Units of last year's coming 56 days over the 56 before them, capped 0.5 to 2, rounded to 2 places.
- 1 when either window sold under 10 units or the earlier window starts before the record. That is why the fill reads 420 days and rows are kept 430.

## Supplier and lead time? (P5, P6)
- Supplier: account of the latest issued purchase invoice with the product (not draft, not cancelled), else of the latest non-cancelled purchase order whose `line_data` has it (the newest 5000 orders are read).
- Lead time: median days from order date to its first purchase invoice over the supplier's last five such orders (even count: the two middle ones averaged with `round()`); else `erp_accounts.lead_days` when above 0; else unknown, `lead_source = 'none'`, no order-by day.

## Days left, quantity and states? (P7 to P9, `erp_stock_plan_forecast()`)
- Daily = units a day × season. Days left = floor(stock / daily); run-out = today + days left; order-by = run-out − lead − safety days.
- Quantity = ceil(daily × (lead + safety + cover) − stock − open), never below 0. Open = what open, sent and partial purchase orders still wait for (`erp_purchase_order_quantities()` → `erp_purchase_order_progress()['open']`).
- An unknown lead time counts as 0 days for the quantity and the state, but no order-by day is stored.
- States in order: `runs_out` (days left under a known lead), `order_now` (order-by today or past), `soon` (within 14 days), `fine`; `no_history` without a speed.

## Where are safety, cover and dead-stock days?
- `erp_stock_plan_settings()`: the Stock Planning settings card's config_text row `erp_stock_plan_settings` (JSON), else 7, 30, 90 (`erp-stock-plan-screen.md`).

## How does the rebuild run?
- Batches of 500 products by id; the cursor is the `erp_stock_plan_cursor` mark in `cron_runs`, `erp_stock_plan_planned` the last night planned. Out of time: `'more'`, the job comes back in a minute. A batch deletes the rows of ids in its range that are no longer tracked; the last batch deletes those above.
- With Accounting off nothing is read or written.

## How is `erp_accounts.lead_days` saved?
- `erp_account_save()` writes it on every save (clamped 0 to 365), like `payment_days`; every full-row builder passes the stored value (API PATCH, e-document sync, CSV merge, Workspace change). The account card's Lead time field changes it from the panel.

- Not verified on a database: no double run of the step, no rebuild on real data.

Tests: `tests/erp_stock_plan_forecast_test.php`.

Related: docs/memory/erp/erp-stock-days.md, docs/memory/plans/stock-planning.md, docs/memory/erp/erp-account-save-partial-writes.md
