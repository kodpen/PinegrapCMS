---
name: product-insights-figures
description: Plan product-insights, what each figure of Edit Product's Performance card counts: sales, revenue, views, conversion, returns, margin, left carts, favourites, and how long a read is kept
---

## Which sales count?
- Lines of the product (`order_items.product_id`) on orders whose status is complete or exported and whose order date is in the period. Cancelled and incomplete orders are left out.
- Units sold: the sum of the lines' quantity. Orders: the number of different orders. Revenue: the sum of price times quantity as the order's line shows it, in the store's currency. Last sale: the newest order date among them, whatever the period.
- A product sold inside a bundle counts by its own line only.

## What are the periods?
- 30 days and 12 months end today in the site's time zone; All has no start. The change line compares with the period of the same length right before; All has none, nor has a figure whose earlier value is zero.
- The chart: 30 days by day, 12 months by month, All by year (the last ten).

## What are Views and Conversion?
- Views: the product's rows of the visitors' content rollup (`visitor_content_hourly`, `visitors/visitors-content.md`) in the period. With visitor statistics off, or for a time the rollup no longer holds, Views and Conversion are left out.
- Conversion: orders divided by views, one decimal, shown when there are at least 20 views.

## What are Returns and Margin?
- Returns: units of the product's lines on returns, counted as Sales watch counts a product's returns (`commerce/sales-watch-returns.md`), for orders of the period; under it the share of the units sold.
- Margin: the share of profit in the period's revenue, the cost taken as the order's Profit card takes a line's (`includes/erp/order_profit_view.php`). No cost known: the cell is left out.

## What counts under "Carts and interest"?
- Left in carts: incomplete orders holding an active line of the product (`pg_order_item_active_sql()`) that were last changed in the period and at least 6 hours ago, the rule of `pg_cart_recovery_is_left()`; the number of carts and the units in them. "View carts" opens All Orders on incomplete orders with the Product Name filter.
- In open carts: the same, last changed under 6 hours ago. Saved for later: carts holding the product as a saved line.
- Favourites: the different owners in `wishlist_items`. Waiting for stock: `stock_notify` rows not yet sent. Rating: the product's `rating_avg` and `rating_count`. A line is left out when its feature is off.
- These four are today's state, whatever the period.

## How long is a read kept?
- Ten minutes per product and period, in the store the session picks among the existing ones (no table). The foot says so. Saving the product does not empty it.

## What is never shown?
- A customer's name or address: the card holds counts only. The links lead to lists that have their own access rules.

Related: docs/memory/plans/product-insights.md, docs/memory/commerce/customer-rhythm.md
