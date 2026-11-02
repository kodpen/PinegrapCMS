---
name: dashboard-v2-shop-cards
description: The new dashboard's shop cards (shop, salesmap, orders, offers, fx): where each reads its data, the widget queries they share, money as plain text, the map drawing
---

## Where does each shop card read from? (#4881)
- `shop` (Ecommerce summary): one query sums the period, the window it is compared with (the period before or the same days a year ago) and the last seven days (`pg_dashboard_v2_shop_windows()`), over orders `complete` or `exported` as widget 3 counts; best sellers are widget 3's `pg_dashboard_best_sellers()` since the period start.
- `salesmap`: `pg_sales_map_collect()` (widget 1's one query), the `d30` period only, so the card has no period pill. The home country's regions shaded in the quantile steps of `pg_sales_map_steps()` when a map of it ships (`assets/maps/`), its countries otherwise (`dashboard-sales-map-data.md`).
- `orders`: `pg_dashboard_recent_orders(3)`, moved out of widget 8 (which calls it with 50). Cancelled, shipped, not shipped are written out beside their tone.
- `offers`: `pg_dashboard_offer_lists()`, moved out of widget 15: running offers, the ones ending within a week first.
- `fx`: `pg_dashboard_currencies()`, moved out of widget 16, plus the `currency_rates` history for the seven-day line; a day without a rate takes the last earlier one, as an invoice would.

## Why is money decoded?
- `BASE_CURRENCY_SYMBOL` is an HTML entity (`&#8378;`); card texts are written with `textContent`, so `pg_dashboard_v2_money()` (`run.php`) decodes it. The first browser run printed `&#8378;14.112,97`.

## How is the map drawn?
- `pg_dash_cards_shop.js` fetches the map file once a page and copies only path ids and shapes into a fresh SVG (`role="img"`, labelled); a region with sales gets `data-step` 1-4, coloured from the card colour; the legend repeats the steps. Region figures are also in the rows, so the colours never carry them alone.

## What is left?
- `ops` (Operations desk) is step N9b: widget 9's lanes build HTML beside their queries; their counts are already helpers (`pg_dashboard_ops_*_summary()`, `pg_dashboard_ops_stock_counts()`, `ui/dashboard-v2-queue-card.md`), the row lists still need a data part.
