---
name: sales-watch-returns
description: Sales watch return spike (W6, SW4): the nightly pass over order_returns, the rate a product is held to, the binomial upper tail, the one bell row return_spike
module: sales_watch_returns
---

## Where is it?
- `includes/fn/sales_watch_returns.php`, its own module beside `sales_watch.php` (the norm and flow alerts of SW1), loaded from `functions.php`. Called from `pg_ops_alerts_run()` as the source `returns` (`pg_sales_watch_check_returns()`). Tests: `tests/sales_watch_returns_test.php`. Built in the PR of plan step SW4 (`plans/sales-watch-steps.md`).

## When does it run?
- Once a night: the first operations alert pass from 03:00 on, once a calendar day (`pg_sales_watch_returns_due()`); a night missed runs at the next pass that day. The day is claimed in the operations alert state file under `returns` before the read, so two processes do not both run it.
- Off when commerce is off, the returns tables are not installed (`pg_returns_ready()`), or the Sales watch setting `return_spike` is false; until Settings › Alerts stores it, on.

## What is counted?
- Units asked back: `order_return_items.quantity` of requests made (`requested_at`) in the last 30 days, any status but `rejected`, by `order_items.product_id`.
- Set against units sold in the 30 days before (`orders.order_date`, status `complete` or `exported`, not `local`). Units back above units sold count as tries too (goods bought earlier).
- The rate: the product's own over the 26 weeks before (returns of those weeks against sales 30 days earlier), or the shop's when it sold under 20 units there; one return and two sales are added, so a rate is never 0.

## When is a product told?
- At least 5 units back and the binomial upper tail (`pg_sales_watch_binomial_upper()`, log scale) below the sensitivity (`pg_sales_watch_settings()['sensitivity']`, 1% by default) divided by the products with 5 units back or more.

## How is it told?
- One `return_spike` alert per night (type warning, group `sales`, link `view_returns.php`): the products counted, the first three named by short description with units back, units sold, the expected units and the reason given most (`pg_return_reasons()` label).
- The night's claim is the once-a-day rule; the alert's own 24-hour rule is kept under subject `returns_0` / `returns_1` by the day's parity, so a pass a few minutes earlier than last night's is not held back.
