---
name: product-insights
description: Edit Product's Performance card, start file: the owner's ask, what each figure counts (units sold, revenue, views, conversion, returns, margin, left carts), what was left out
module: product_insights
---

## What is it and why?
- Owner (2026-11-22): Edit Product shows the product's figures, units sold and the times it was left in a cart his two examples; the drawing was accepted with "onay" (`workflow/decisions-made-panel-15.md`).
- Built in #11094 (readers, `product-insights-module.md`) and #11138 (head and card, `product-insights-card.md`). The drawing: `commerce-screens-drawing/product.html` with `product-dark.jpg` and `product-light.jpg`.
- Edit Product (`edit_product.php`, drawn by `product_builder.php`) is a kept screen: only the head and the card were let into its file (`ui/look-kept-screens.md`).

## What is on the screen?
- A head under the breadcrumb: cover, title, status and stock pills, SKU with copy, price, first group, last change; Duplicate, Product Form, View on site.
- "Performance": the first section and rail entry, on Edit Product only. Period 30 days, 12 months or All; eight figures, a bar chart of units sold, "Carts and interest", the foot "Orders of this product".
- Read only: it writes nothing and is no part of Save. It loads after the page has opened; a failed read shows one line and Try again.

## Which sales count?
- Lines of the product (`order_items.product_id`) on orders with status complete or exported, by order date in the period. Cancelled and incomplete orders are left out; a product sold inside a bundle counts by its own line only.
- Units sold: sum of quantity. Orders: different orders. Revenue: price times quantity as the line shows it, store currency. Last sale: the newest order date, whatever the period.
- The change line compares with the period of the same length right before; All has none, nor a figure whose earlier value is zero.

## What are Views, Conversion, Returns and Margin?
- Views: the product's rows of `visitor_content_hourly` (`visitors/visitors-content.md`). With visitor statistics off, or before the rollup's first day, Views and Conversion are left out.
- Conversion: orders divided by views, one decimal, shown from 20 views.
- Returns: units on returns not rejected, counted as Sales watch counts them (`sales-watch-returns.md`), with their share of units sold.
- Margin: profit's share of revenue, the cost taken as the order's Profit card takes it; left out with no cost known. Shown only where the Profit card would show (`erp/erp-order-profit-screens.md`).

## What counts under "Carts and interest"?
- Left in carts: incomplete orders with an active line of the product, last changed in the period and at least 6 hours ago (`pg_cart_recovery_is_left()`); carts and units. View carts opens All Orders on incomplete orders with the Product Name filter.
- In open carts: the same, changed under 6 hours ago. Saved for later, Favourites (different owners in `wishlist_items`), Waiting for stock (unsent `stock_notify` rows), Rating: today's state; each line only when its feature is on.

## How long is a read kept, and what is never shown?
- Ten minutes per product and period in the session, no table; saving the product does not empty it (`product-insights-module.md`).
- No customer name or address: counts only. Its links lead to lists with their own access rules.

## What was left out?
- A variant set's total, a comparison of products, "bought together", an export of the figures, the same card on a product group. Each would be a new plan.

Related: docs/memory/commerce/product-insights-module.md, docs/memory/commerce/product-insights-card.md, docs/memory/commerce/customer-rhythm.md, docs/memory/ui/look-kept-screens.md
