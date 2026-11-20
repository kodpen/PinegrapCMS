---
name: sales-watch
description: Plan (owner 2026-11-20, poll 165): the store learns its order rhythm and alerts on orders stopped, a silent channel, broken payment, a price error, a return spike; SW1 to SW6
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 165, message 10147): "Evet, plana girsin": all five signals; on a price error the product is taken off sale only when a setting allows it, and one click puts it back. Stock is never changed.

## What is there today?
- Operations alerts (`platform/ops-alerts.md`) catch technical failures only (backup, jobs, PHP errors, disk, mail, webhooks, certificate, a payment callback's log line): bell, e-mail, device banner, Slack, Teams and Telegram targets (`includes/fn/notify_channels.php`); once per code per 24 h.
- The dashboard brief (`includes/dashboard/brief.php`) compares days only when the dashboard is opened.
- Orders: `order_date`, `status` (`incomplete`, `complete`, `exported`, `cancelled`), `type` (`online`, `local`, `marketplace`), `billing_complete` (the cart reached the payment step); a marketplace order's account in `marketplace_order_map.account_id`. No record of a payment attempt or a refusal.
- Prices: `product_price_history` per channel, 13 months. Cost: `erp_product_costs.avg_cost` (Accounting on). Returns: `order_returns`, `order_return_items`.
- Nothing learns a normal hour; a broken checkout, a dropped marketplace key or a price typed 100 for 1,000 is noticed late, by a person.

## What binds it?
- Reads after the fact: `order_completion.php`, `cart_action.php` and checkout are not entered.
- Counter sales (`type = 'local'`) stay out of the norm.
- Settings in `config_text` rows (`data/config-table-no-varchar.md`); the alerts go through `pg_ops_alert()` and its channels, never a second sender. No table and no column (architect, below).
- A cost figure in an alert only for a reader with ERP access (`validate_erp_access()`), as in `plans/order-profit.md`.
- Taking a product off sale goes through the writer the API's product update uses, so the marketplace queue, webhooks and audit trail see it as any other change; stock is never written.

## What did the architect decide? (2026-11-20, agent channel message 10453)
- No table. The norm is computed from `orders`; `data/temp/` is only a cache, rebuilt when lost, never relied on. Alerts are `notifications` rows; no column is added there, so readers of `order_total` = code keep working.
- `sales_watch_closed` is written with `pg_config_text_claim()` and holds at most 200 entries. The alert's subject sits in the state file; a lost file may repeat an alert once.
- The bell follows the operations alert rule (role below 3): one sender, one rule. A shop that wants its order person told uses `ops_alert_recipients` or a chat target. The architect's reading; the owner may turn it.
- Defaults: the four alerts on while operations alerts are on; taking a product off sale off; sensitivity normal.
- Once a day per code and subject. When the "store" alert went out, the pass sends no channel alerts.
- Marketplace delay: those orders arrive by polling, so a channel's windows end at its last successful sync and a window shorter than its sync interval is not counted; else the last hours look short and a false "silent" goes out.
- Brake (W5): a planned big discount also looks like half price and a spike. At most 5 products closed a pass and 20 a day; more than 5 qualifying in one pass closes none and sends one alert ("Price drop on N products; a campaign?").
- `price_error` and `return_spike` are one bell row per pass, the products counted and the first few named, never a row per product.

## What is built?
- Features: `sales-watch-features.md` (W1 to W12). Steps: `sales-watch-steps.md`. Claim as `[plan:sales-watch] <step> — <topic>`.

## What is left out?
- Forecasts, anomalies on visits or ads, webhooks for these alerts, a dashboard card: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the settings part and bell rows are seen on dev after SW2 and SW3.
