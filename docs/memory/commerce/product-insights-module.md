---
name: product-insights-module
description: Readers of Edit Product's Performance card (includes/fn/product_insights.php): periods, the grouped sales read, views, margin, carts, the product_insights action, ten-minute keep
module: product_insights
---

## Where is it?
- `includes/fn/product_insights.php`, loaded only by the panel action `product_insights` (`pg_panel_product_insights()`, `includes/panel/commerce.php`), not by `functions.php`. Read only. Tests: `tests/product_insights_test.php`. What each figure counts: `product-insights.md`.

## What does the action take and answer?
- JSON body `product_id`, `period` (`30d`, `12m`, `all`; anything else is `30d`). Exempt row, no token, no write: the handler answers an ended session with `panel_login_refusal()`, then Edit Product's own gate (role below 3 or `manage_ecommerce`, `ECOMMERCE` on) in JSON, then "Product not found.".
- `data`: `period`, `unit` (day, month, year), `read_at`, `keep` (600), `no_sales`, `figures` (units, revenue in minor units, orders, views, views_since, conversion, returns, returns_share, last_sale, change of the first four, margin and margin_state only for a person who may see them), `chart` (start, units per bucket), `carts`, `interest`. A figure whose feature is not installed or not held is `null`.
- A failed sales read answers "The figures could not be read. Try again.".

## How are the periods cut?
- `pg_product_insights_window()`, PHP's time zone: 30 days from midnight 29 days ago, by day; 12 months from the first of the month 11 months ago, by month; All with no start, the chart over the last ten years (older sales count in the figures, in no bar). The earlier period is the same length right before; All has none.

## How is a read kept fast?
- Every read starts from `order_items.product_id` (indexed) and reaches `orders` by its key; views use `visitor_content_hourly.idx_item`, favourites `wishlist_items.idx_product`, waiting `stock_notify.idx_product_sent`. No index was added.
- Sales are one query grouped by MySQL `INTERVAL(orders.order_date, bounds…)`: slot 1 the earlier period, then one slot per bucket (`pg_product_insights_bounds()`, folded by `pg_product_insights_sales_fold()`). Revenue is `SUM(CAST(price AS SIGNED) * CAST(quantity AS SIGNED))`, as an unsigned product would fail on a negative price.

## Which choices were made here?
- Views are left out (with conversion) when the rollup's first day is after the period's start; for All they count from that day (`views_since`).
- Margin: the period's orders of the product (at most 20,000, else left out) split by `erp_profitability_split()` as Accounting › Profitability splits them, the product's rows summed, `erp_order_profit_margin()` on the sums; left out without revenue or with no product cost. State is the worst of the rows. Shown only where `erp_order_profit_shown()` is true.
- Left in carts follows the period (last change in it and at least 6 hours ago); open carts, saved lines (only with `ECOMMERCE_SAVE_FOR_LATER`), favourites, waiting (every unsent `stock_notify` row of the product) and rating are today's.

## How long is a read kept?
- Ten minutes in the person's session, `$_SESSION['software']['product_insights']`, keyed product, period and whether the margin is in it, at most 20 reads (`pg_product_insights_keep()`). The session is closed while the figures are read and reopened to keep them. Saving the product does not empty it.

Related: docs/memory/commerce/product-insights.md, docs/memory/core/panel-action-table.md, docs/memory/erp/erp-order-profit-profitability.md
