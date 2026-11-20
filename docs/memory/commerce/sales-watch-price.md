---
name: sales-watch-price
description: Sales watch price error shield (#10775): which sales look like a wrong price, the usual price, taking a product off sale, sales_watch_closed, putting it back, the brake
module: sales_watch
---

## Where is it?
- `pg_sales_watch_price_check()` in `includes/fn/sales_watch.php`, source `sales_price` of `pg_ops_alerts_run()` (at most every 10 min). Only with `ECOMMERCE`, the `price_error` setting on and the store not paused. Tests: `tests/sales_watch_price_test.php`.

## Which sales look like a price error?
- Products with at least 5 units sold on the site or a marketplace (`type` `online` or `marketplace`, not `incomplete`) in the last 6 hours (`PG_SALES_WATCH_PRICE_HOURS`), still enabled, at most 200.
- Units at least 3 times the product's norm (its units of the 30 days before, per 6 hours) and the newest price under the price drop share (50% by default, `price_drop` setting) of the usual price, or, with Accounting on, under `erp_product_costs.avg_cost` (`pg_sales_watch_price_reason()`: `half` or `cost`).
- The usual price (`pg_sales_watch_price_usual()`): the median over 30 days of `product_price_history` prices weighted by how long each applied, so a few hours of a typo do not move it. Only the `web` channel is read; a product with no row is not judged.

## How is it told?
- One `price_error` row a pass: the products counted, the first three named with units, price and usual price ("under its cost" for `cost`); no cost figure, as the text also goes by e-mail and chat. The bell row links the first product closed, else the first named (`product_id`).
- Once a day per product: the subject `p<id>` in the state file; every product told gets its key after a send.

## When is a product taken off sale?
- Only with the setting `close_products` on (off by default). `api_products_set_enabled($id, false, 'Sales watch')` in `includes/api/resources/products.php`: the steps of the product update route (search index, audit trail, activity log, `product.updated`, marketplace queue); stock is never written. Loaded outside the API through `pg_sales_watch_product_writer()`.
- Brake (`pg_sales_watch_price_plan()`): more than 5 products found in one pass closes none and the text asks "Price drop on N products; a campaign?"; otherwise at most 5 a pass and 20 in 24 hours.

## How does a person put it back?
- By enabling the product any way. Each pass first reconciles the `sales_watch_closed` `config_text` row (`closed` id => at, price, reason; `back`; `times`): an enabled product leaves `closed` and goes to `back`, and the shield leaves it alone for 24 hours. A deleted product leaves the record.
- Written with `pg_config_text_claim()`, at most 200 entries, oldest dropped. `GET /reports/sales-watch` lists the ones still off sale.

Related: docs/memory/commerce/sales-watch.md, docs/memory/commerce/sales-watch-settings.md
