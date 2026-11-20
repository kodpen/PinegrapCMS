---
name: sales-watch
description: Plan (owner 2026-11-20, poll 165): the store learns its order rhythm and alerts on orders stopped, a silent channel, broken payment, a price error, a return spike; SW1 to SW6
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 165, message 10147): "Evet, plana girsin": all five signals; on a price error the product is taken off sale only when a setting allows it, and one click puts it back. Stock is never changed.

## What is there today?
- Operations alerts (`platform/ops-alerts.md`, `includes/fn/ops_alerts.php`) catch technical failures only: backup, scheduled jobs, PHP errors, disk, mail, webhooks, certificate, a payment callback's log line. Bell (the record), e-mail, device banner; Slack, Teams and Telegram targets (`includes/fn/notify_channels.php`); once per code per 24 h.
- The dashboard brief (`includes/dashboard/brief.php`) compares today with yesterday only when someone opens the dashboard.
- Orders: `order_date`, `status` (`incomplete`, `complete`, `exported`, `cancelled`), `type` (`online`, `local`, `marketplace`), `billing_complete` (the cart reached the payment step); a marketplace order's account in `marketplace_order_map.account_id`. No record of a payment attempt or a refusal.
- Prices: `product_price_history` per channel (`commerce/product-price-history.md`), 13 months. Cost: `erp_product_costs.avg_cost` with Accounting on. Returns: `order_returns` and `order_return_items`.
- Nothing learns what a normal hour looks like; a broken checkout, a dropped marketplace key or a price typed as 100 instead of 1,000 is noticed by a person, late.

## What binds it?
- Reads after the fact: `includes/fn/order_completion.php`, `cart_action.php` and checkout are not entered.
- Counter sales (`type = 'local'`) stay out of the norm.
- Settings in `config_text` rows (`data/config-table-no-varchar.md`); the alerts go through `pg_ops_alert()` and its channels, never a second sender.
- A cost figure in an alert only for a reader with ERP access (`validate_erp_access()`), as in `plans/order-profit.md`.
- Taking a product off sale goes through the writer the API's product update uses, so the marketplace queue, webhooks and audit trail see it as any other change; stock is never written.

## What does the architect decide?
- No table: the norm is computed by each pass from `orders` (indexed `order_date`) and cached under `data/temp/`; alerts are `notifications` rows with `action = 'ops'`; the products the watch closed sit in the `config_text` row `sales_watch_closed`. Is that right, or does he want an `ops_*` table?
- Who gets the bell: the ops alert rule (role below 3), or also a person with order rights who gets the new order bell?
- Defaults after the upgrade: the four alerts on (a small shop stays quiet by the statistics, W10), taking a product off sale off, sensitivity normal.
- The once-a-day rule keyed by code and subject (the channel or the product), so "Trendyol silent" does not hide "site silent".

## What is built?
- Features: `sales-watch-features.md` (W1 to W12). Steps: `sales-watch-steps.md`. Claim as `[plan:sales-watch] <step> — <topic>`.

## What is left out?
- Forecasting sales, stock or cash; anomaly detection on visits or ads; webhooks for these alerts; a dashboard card. Each is a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the settings part and the bell rows are seen on dev after SW2 and SW3 land.
