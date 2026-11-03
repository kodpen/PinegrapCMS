---
name: dashboard-v2-shop-cards
description: The new dashboard's shop cards (shop, salesmap, ops, orders, offers, fx): where each reads its data, the widget queries they share, money as plain text, the map drawing
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

## How does the Operations Desk card read widget 9? (#5031)
- `ops` draws three lanes, To ship, To refund, Out of stock, each its count, a line under it and at most three rows (`PG_DASHBOARD_V2_OPS_ROWS`), with a "more" link to the filtered list when the count is larger.
- Counts are widget 9's helpers (`pg_dashboard_ops_shipping_summary()`, `pg_dashboard_ops_refund_summary()`, `pg_dashboard_ops_stock_counts()`), shared with the Next up card. Rows are the lane queries moved out of widget 9: `pg_dashboard_ops_shipments($limit)`, `pg_dashboard_ops_refund_list($limit)`, `pg_dashboard_ops_out_of_stock_list($limit)`; the widget calls them with 11, 10 and 8, its HTML unchanged.
- The lanes follow the widget's gates: no ship lane without shipping, no refund lane before `orders.refund_status` exists. A row or a count waiting more than two days is `bad`, as the widget marks it red.
