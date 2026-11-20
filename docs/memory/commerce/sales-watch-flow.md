---
name: sales-watch-flow
description: Sales watch, orders stopped and payment broken (SW1, #10735): channels, the hourly norm and its cache, the windows, the store alert, the payment share, the timing
module: sales_watch
---

## What does it watch?
- `includes/fn/sales_watch.php`, the `sales` source of `pg_ops_alerts_run()` (at most every 10 min). Codes `orders_stopped` and `payment_broken` (`group => 'sales'` in `pg_ops_alert_codes()`; `type` stays `error` for the bell colour). Only with `ECOMMERCE`.
- Channels: `site` (`orders.type = 'online'`, `status <> 'incomplete'`, cancelled included); `mp_<id>` per marketplace account with `status = 'active'`, `pull_orders = 1`, `last_order_pull > 0` (orders through `marketplace_order_map`). Counter sales (`local`) never count.

## How is the norm computed?
- An hour's norm: median over the 8 hours exactly 168, 336 … 1344 hours before, each blended ¼ ½ ¼ with its neighbours, times the last 7 days against the 8 weeks' weekly mean (0.5 to 2). Whole weeks back, so no time zone. Under 20 orders in 8 weeks: no norm, no alert.
- Hourly counts cached in `data/temp/sales_watch_norm.json` for 6 hours (`pg_sales_watch_norm_data()`); lost = rebuilt.

## When are orders stopped?
- Windows 1, 2, 3, 6, 12 h; Poisson lower tail at sensitivity / 5 (`pg_sales_watch_alpha()`); the window with the lowest chance is named ("Trendyol: 0 orders in 6 hours, about 9 expected").
- A marketplace channel ends its windows at `last_order_pull`; windows shorter than `api_sync_job`'s interval are dropped. A cursor over a week old: the channel is skipped.
- Two or more judged channels, all stopped: one alert, subject `store`, and no channel alerts (`pg_sales_watch_flow_alerts()`). A single channel is named by its own name.

## When is payment broken?
- Site only: carts `billing_complete = 1`, `status = 'incomplete'` by `last_modified_timestamp` against site orders completed (`order_date` is rewritten at completion) in the same windows; the 8 weeks' share, smoothed (+1/+2), binomial lower tail; at least 5 carts and 20 completed orders in 8 weeks. Names the `payment_method` values chosen ("not chosen" for empty).

## Once a day per what?
- Per code and subject (`site`, `mp_<id>`, `store`) through `pg_ops_alert($code, $detail, $subject)`; the subject's send time lives only in `ops_alerts_state.json` (no column on `notifications`), so a lost file may repeat an alert once (architect, `plans/sales-watch.md`).

## Which settings?
- The `sales_watch` `config_text` row: each alert on or off, sensitivity, the pause per channel or store (`sales-watch-settings.md`, #10772).

## How fast is it?
- 200,000 orders over a year (two marketplace accounts) plus 60,000 abandoned carts, MariaDB: norm build 0.33 s, pass with a warm cache 0.005 s (#10735).

## How is it tested?
- `tests/sales_watch_test.php`: tails, windows, holiday week, quiet small shop, silent channel and store, marketplace between syncs, broken checkout, subject key.

Related: docs/memory/platform/ops-alerts.md, docs/memory/plans/sales-watch.md
