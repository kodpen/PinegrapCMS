---
name: sales-watch
description: Sales watch overview: order rhythm alerts (orders stopped, payment broken, price error, return spike), what binds it, the architect's answers, each feature's PR, what is left out
module: sales_watch
---

## What is it?
- Owner (2026-11-20, agent channel poll 165, message 10147; `workflow/decisions-made-product-84.md`): five signals (orders stopped, a channel silent, payment broken, the price error shield, the return spike), told through the operations alert channels once a day; a small shop stays quiet. A product is taken off sale only when a setting allows it; stock is never changed.
- Built in 2026.11.0 by SW1 #10735, SW2 #10772, SW3 #10775, SW4 #10777, SW5 #10817; closed out by SW6.

## Where is each part?
- Norm, orders stopped, payment broken, timing: `sales-watch-flow.md` (#10735).
- Settings › Updates and maintenance › Sales watch row, the pause: `sales-watch-settings.md` (#10772).
- Price error shield, taking a product off sale: `sales-watch-price.md` (#10775).
- Return spike, nightly: `sales-watch-returns.md` (#10777).
- `GET /reports/sales-watch`: `api/api-sales-watch-report.md` (#10817).
- Codes and channels: `platform/ops-alerts.md`.

## What binds it?
- Reads after the fact: `order_completion.php`, `cart_action.php` and checkout are not entered. Counter sales (`type = 'local'`) stay out.
- No table and no column. The norm is computed from `orders`; `data/temp/` is only a cache, rebuilt when lost. Alerts are `notifications` rows (`order_total` = code) through `pg_ops_alert()`, never a second sender. Settings and `sales_watch_closed` are `config_text` rows.
- Architect (2026-11-20, agent channel message 10453): the bell follows the operations alert rule (role below 3); a shop that wants its order person told uses `ops_alert_recipients` or a chat target. Defaults: the four alerts on while operations alerts are on, taking off sale off, sensitivity normal.
- Architect (2026-11-20): once a day per code and subject, the subject in the state file only (a lost file may repeat an alert once); when the store alert went out, no channel alerts; a marketplace channel's windows end at its last sync; `price_error` and `return_spike` are one bell row per pass.
- Architect (2026-11-20): the brake. At most 5 products closed a pass and 20 a day; more than 5 in one pass closes none ("Price drop on N products; a campaign?").
- Taking off sale goes through the API's product writer, so the marketplace queue, webhooks and audit trail see it; a cost figure only to ERP readers, so none in the alert text (it also goes by e-mail and chat).

## Which feature landed where?
- W1 norm, W2 windows, W2b marketplace sync end, W3 payment, W10 small shop, W12 timing (0.33 s build, 0.005 s warm pass on 200k orders): #10735.
- W7 codes: `orders_stopped`, `payment_broken` #10735; `price_error` #10775; `return_spike` #10777. Once a day per code and subject: #10735.
- W8 settings, W9 pause per channel or store, chat targets' Sales watch heading: #10772.
- W4 price error, W5 off sale and back, W5b brake: #10775. W6 return spike: #10777. W11 the report route and its MCP tool: #10817.

## Where does the code differ from the plan's words?
- W4: prices are read from the `web` channel of `product_price_history` only; a marketplace sale is judged against the shop's price, as only that channel had rows.
- W3: besides at least 5 carts, the site needs 20 completed orders in 8 weeks.

## What is left out?
- Forecasts, anomalies on visits or ads, webhooks for these alerts, a dashboard card: each a later yes.
