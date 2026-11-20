---
name: sales-watch-features
description: Plan sales-watch, features W1 to W12: the norm per channel, orders stopped, payment broken, the price error shield, taking a product off sale, the return spike, delivery, settings, API
---

## How is the norm learnt?
- (W1) Per channel (the site; each marketplace account; counter sales out), orders placed per hour of the week over 8 weeks by `order_date`, cancelled ones included. The median of the 8 values, the two neighbouring hours blended in, scaled by the last 7 days against the 8 weeks (0.5 to 2).
- (W10) A channel with fewer than 20 orders in the 8 weeks has no norm and no alert. The test is quiet where little is expected: under about 4.6 orders expected, even none is not below 1%.

## When do orders count as stopped?
- (W2) Every pass looks back over the last 1, 2, 3, 6 and 12 hours: orders against the norm's sum, Poisson lower tail; below the sensitivity (normal 1%, split over the five windows) the alert names the channel and figures: "Trendyol: 0 orders in 5 hours, about 9 expected". All channels stopped together is one alert, "the store", and that pass sends no channel alert.
- (W2b) A marketplace channel's windows end at its last successful order sync; a window shorter than its sync interval is not counted for it, so polling delay never reads as silence.

## When does payment count as broken?
- (W3) The site only: carts that reached payment (`billing_complete = 1`, `status = 'incomplete'`, changed in the window) against completed site orders, the 8 weeks' share as the norm, binomial lower tail; at least 5 carts. The alert names the payment methods chosen.

## What is the price error shield?
- (W4) A product sold on the site or a marketplace in the last 6 hours whose price (its newest `product_price_history` row for that channel) is under half its 30-day median, or under `erp_product_costs.avg_cost` with Accounting on; and units in those hours at least 3 times its norm and at least 5. The alert names price, usual price and units; the cost only to ERP readers.
- (W5) With the setting on (off by default) the product is taken off sale (`enabled = 0`) through the API's product writer; stock is not touched. Recorded in `sales_watch_closed` (product, time, price, reason; `pg_config_text_claim()`, at most 200 entries). The bell row links the product; enabling it again (any way) clears the entry and the shield leaves it alone for 24 hours: the person's yes wins.
- (W5b) Brake: at most 5 products closed a pass and 20 a day; when more than 5 qualify in one pass none is closed and one alert asks "Price drop on N products; a campaign?".

## When is a return spike told?
- (W6) Nightly: a product's units asked back in 30 days against units sold the 30 days before, against its own 26-week rate (the shop's with too little history), binomial upper tail, at least 5 units. Named with the most given reasons.

## How is it told?
- (W7) New codes in `pg_ops_alert_codes()`: `orders_stopped`, `payment_broken`, `price_error`, `return_spike`, type `sales`, each with its screen. Bell (role below 3, the operations alert rule), e-mail, device banner and the Slack, Teams and Telegram targets that choose them; once a day per code and subject, the subject kept in the state file. `price_error` and `return_spike` are one row per pass, products counted and the first few named.
- (W8) Settings › Alerts gets a Sales watch part: each alert on or off (on by default while operations alerts are on), the sensitivity (low 0.1%, normal 1%, high 5%), taking a product off sale, the price drop share (half by default). `config_text` rows.
- (W9) "Pause the watch until" a date (a holiday, a shutdown), per channel or the store.

## Who can read it from outside?
- (W11) `GET /reports/sales-watch` (scope `orders:read`): per channel the norm for the next 12 hours and the last 12 hours' orders, the last pass, 7 days of alerts, the products closed. An MCP tool by the route rule; putting a product back is the product update route.
- (W12) A few indexed reads per channel a pass; timed on a 200k-order copy in SW1.
