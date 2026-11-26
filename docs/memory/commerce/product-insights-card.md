---
name: product-insights-card
description: Edit Product's head and Performance card (#11138): the stored row behind the head, View on site's page, the card's period control, the body the product_insights action draws, the Figure grid recipe
module: product_insights
---

## What does the head show?
- Under the breadcrumb, `pg_pb_render_head()` in `product_builder.php`, drawn by `pg_record_head()` with its `media` slot (48 px cover, or a box tile without one). Title: the Short Description, else the SKU. Pills (`pg_product_insights_head_pills()`): Enabled teal or Disabled grey; with the product's Inventory on, "In stock · 14" teal, "Backorder" amber (quantity 0 or less with Backorder on), else "Out of stock" rose.
- Grey line: the SKU with a copy button, the price, the first parent group (lowest group id), "last changed …" from `products.timestamp`.
- It reads the stored `products` row, not the form's values, so a failed save does not change what the head says.

## What are the head's buttons?
- Duplicate (not in the Recycle Bin), Product Form (when the product has one), View on site: in a new tab, the site's catalog detail page (`pg_sw_catalog_detail_page_name()`, the page lists, cart and feeds link to) plus the product's address. No View on site while disabled, without an address or without a detail page. A product shown by another catalog page opens on that one page only.

## How does the card load?
- `pg_pb_render_insights_card()` draws the first section `pg_pb_sec_insights` (and its rail entry "Performance") on Edit Product only: title, a `pg-seg` of 30 days, 12 months, All, and four skeleton cells.
- `assets/js/product_insights.js` (served as written, no bundle) asks `product_insights` once the page has opened and puts `data.html` in `.pg-pi-body`. The period is kept in `localStorage` (`pg_product_insights_period`), 30 days when storage is blocked.
- A failed read: the server's message for that failure only, else "The figures could not be read. Try again.", with Try again, which asks for the period on screen (before #11815 the stored one, 30 days with storage blocked). An answer for a period no longer chosen is dropped.

## Who draws the body?
- The action adds `html` to its `data`, kept read or fresh: `pg_product_insights_card_html($data, $sku, BASE_CURRENCY_SYMBOL, $now)`, pure, tests in `tests/product_insights_test.php`.
- Cells in order: Units sold, Revenue, Orders (change line each), Views (change line), Conversion, Returns, Margin, Last sale. Views and Conversion go together when views are unknown; Conversion shows "—" and "Shown from 20 views" under 20 views. Returns or Margin left out when null; Margin's note says Estimate or Some costs are missing.
- Change line: "↑ 12%" teal, "↓ 3%" rose, none without an earlier period.
- Chart: one bar a bucket, height from the highest, a zero bucket faint, the first and last bucket named (`pg_product_insights_bucket_name()`: "5 Kas", "Kas 2026", "2026").
- No sales: "No sales in this period" and the views in place of the grid and chart; Carts and interest stay.
- Carts and interest: Left in carts (with View carts when above 0), In open carts, then Favourites, Waiting for stock, Saved for later, Rating, each only when its feature is on.
- Links: `view_orders.php?reset=true&product_name=<SKU>` (Orders of this product), plus `&status=incomplete` (View carts). The All Orders date range stays its default.

## What is the Figure grid?
- The recipe "Look A: figure grid" in `backend.src.css`: `.pg-figure-grid` cells four across divided by rules (two on a phone), `-chart`, `-bars`, `-axis`, `-sub`, `-rows`, `-row` (`.is-wide` the whole row), `-row-link`, `-foot`, `-empty`. One cell from PHP: `pg_figure_grid_cell()` (`includes/fn/output.php`). Design examples "Figure grid" draws the card with fixed figures (`pg_look_examples_figure_grid_pattern()`).

Related: docs/memory/commerce/product-insights-module.md, docs/memory/commerce/order-screen-head.md, docs/memory/commerce/product-insights.md
