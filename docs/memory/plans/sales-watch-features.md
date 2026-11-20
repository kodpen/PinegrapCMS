---
name: sales-watch-features
description: Plan sales-watch, features W1 to W12: the norm per channel, orders stopped, payment broken, the price error shield and taking a product off sale, the return spike, delivery, settings, API
---

## How is the norm learnt?
- W1 For each channel (the site; each marketplace account), the orders placed per hour of the week over the last 8 weeks, by `order_date`, cancelled ones included (they were placed). The median of the 8 values for the hour, its two neighbours blended in, scaled by the last 7 days against the 8 weeks (clamped 0.5 to 2). Counter sales stay out.
- W10 A channel with fewer than 20 orders in the 8 weeks has no norm and no alert. The test below is quiet by itself where little is expected: with under about 4.6 orders expected, even none is not below 1%.

## When do orders count as stopped?
- W2 Every pass looks back over the last 1, 2, 3, 6 and 12 hours: the orders that came against the norm's sum, Poisson lower tail. Below the sensitivity (normal 1%, divided across the five windows) the alert is sent with the channel's name and figures: "Trendyol: 0 orders in 5 hours, about 9 expected". All channels stopped together is one alert, "the store".

## When does payment count as broken?
- W3 The site only: carts that reached payment (`billing_complete = 1`, `status = 'incomplete'`, changed in the window) against completed site orders in the same window, with the 8 weeks' share as the norm, binomial lower tail. At least 5 carts reached payment. The alert names the payment methods those carts chose, when they chose one.

## What is the price error shield?
- W4 A product sold on the site or a marketplace in the last 6 hours whose price (its newest `product_price_history` row for that channel) is under half its 30-day median, or under `erp_product_costs.avg_cost` with Accounting on; and whose units in those hours are at least 3 times its own norm and at least 5. The alert names the price, the usual price and the units; the cost only to ERP readers.
- W5 With the setting on (off by default) the product is taken off sale (`enabled = 0`) through the API's product writer; stock is not touched. Recorded in `sales_watch_closed` (product, time, price, reason). The bell row says so and links the product; enabling it again (any way) clears the entry, and the shield then leaves that product alone for 24 hours: the person's yes wins.

## When is a return spike told?
- W6 Nightly: a product's units asked back in the last 30 days against units sold in the 30 days before, compared with its own rate over 26 weeks (the shop's rate when it has too little history), binomial upper tail under the sensitivity and at least 5 units asked back. Named with the return reasons given most.

## How is it told?
- W7 New codes in `pg_ops_alert_codes()`: `orders_stopped`, `payment_broken`, `price_error`, `return_spike`, type `sales`, each with its screen. Bell, e-mail, device banner and the Slack, Teams and Telegram targets that choose them; once a day per code and subject.
- W8 Settings › Alerts gets a Sales watch part: each alert on or off, the sensitivity (low 0.1%, normal 1%, high 5%), taking a product off sale, the price drop share (half by default). `config_text` rows.
- W9 Pause: "Pause the watch until" a date (a holiday, a planned shutdown), per channel or the store; the pass then says nothing for it.

## Who can read it from outside?
- W11 `GET /reports/sales-watch` (scope `orders:read`): per channel the norm for the next 12 hours and the last 12 hours' orders, the last pass, the alerts of the last 7 days, the products the watch closed. An MCP tool by the route rule. No new write route: putting a product back on sale is the product update route.
- W12 The pass costs at most a few indexed reads per channel; measured on a 200k-order copy in SW1, as `commerce/customer-value-summary.md` was.
