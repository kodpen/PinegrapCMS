---
name: supplier-feed-cost-api
description: Supplier feeds (SF6, F11 and F14): the feed's buy price as an order line's cost, the stock plan's supplier from the feed's ERP account, and the three API routes
---

## When does an order line take the feed's buy price? (F11)
- `includes/erp/order_profit.php`: a plain line whose product has no `erp_product_costs.avg_cost` takes the feed's buy price × quantity, one row `item:<order_item_id>` with source `supplier`. A bundle's components do not.
- The row is kept like an `average` row (`erp_order_profit_kept_sources()`): a later average or buy price never rewrites it. A `supplier` row makes the order `estimate`.
- `erp_order_profit_supplier_costs()` reads `supplier_feed_items` only: `buy_price > 0`, not dismissed, the item in use (`active`) first, else the newest `changed_at`. Nothing in the feed's or ERP tables is written.
- The item's buy price is net of VAT in the feed's currency; `erp_order_profit_supplier_unit()` (pure) converts it with `erp_to_base()` at 1 / `currencies.exchange_rate`. No rate: no feed cost, the line stays incomplete.

## Why the 11.0.271 step?
- `erp_order_costs.source` was an ENUM without `supplier`; the non-strict session would store '' in silence. Step 11.0.271 `upgrade_2026_11_0_order_cost_supplier()` widens it only when the value is missing.
- Until it has run, `erp_order_profit_supplier_ready()` is false and no feed cost is read.

## Which supplier does the stock plan name? (F11)
- `erp_stock_plan_suppliers()` (pure) takes a third list: product id => the feed's `erp_account_id`. The latest purchase invoice wins, then the latest purchase order, then the feed (source `feed`).
- `erp_stock_plan_feed_accounts()` reads it per batch: feeds that name an account, the item in use first, then the newest item.

## Which API routes are there? (F14)
- `includes/api/resources/supplier_feeds.php`, routes in `api_schema()`, tag `Supplier_feeds`, MCP tools by the route rule. The scopes are the gate: `products:*` is given to the same people as the screen (role under 3 or manage_ecommerce).
- `GET /supplier-feeds` (`products:read`): one page by name, `SupplierFeed` with host, format (`auto` when told from the bytes), status `on`/`off`/`paused`, `pulling`, matched products and `last_run`. Address, user, password and key are never returned.
- `GET /supplier-feeds/{id}/runs` (`products:read`): `SupplierFeedRun`, newest first by default (`sort=oldest`), cursor on the run id; kind `scheduled`/`by_hand`, status `running`/`ok`/`stopped`/`failed`, the counts, `reason`, `failed_lines`.
- `POST /supplier-feeds/{id}/run` (`products:write`, dry run): `pg_supplier_feed_pull_now()` with an 8-second budget (`API_SUPPLIER_FEED_RUN_BUDGET`), the job goes on with the rest. 409 `feed_off`, `not_mapped`, `already_pulling`.

## What is not verified?
- No run of step 11.0.271 on a database, no pull through the API on a real feed, no order computed with a feed cost on real data: unit tests only (`tests/erp_order_profit_test.php`, `tests/erp_stock_plan_forecast_test.php`).
