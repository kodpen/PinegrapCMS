---
name: bug-hunt-areas-product-variants-stock
description: The bug hunt of product variants and stock (area 30, 2026-11-24): three faults fixed, what was left for a decision, what was read clean
module: ecommerce
---

## Which area was hunted?
- Area 30 "product-variants-stock": a variant's own price, stock and picture; a deleted option still in a cart or an order; stock below zero on two orders at once; the low-stock mark; a sold-out variant bought through a direct request.
- Read: `add_to_cart.php`, `catalog_detail.php`, `cart_action.php` (quick add), `add_order_item()` and `check_inventory()` (`includes/fn/ecommerce.php`), `pg_order_complete_line_stock()` (`includes/fn/order_completion.php`), the variant payload of the catalog item view (`includes/fn/widgets_catalog.php`) and `assets/js/pg_civ_variants.js`, `get_catalog_detail.php`.
- Also read: option removal on `edit_product_attribute.php` and `POST /product-attributes`, the low-stock counts (`includes/dashboard/`), `includes/api/resources/inventory.php`.

## What did the hunt's PR fix?
- The site's add-to-cart action (`api.php` action `add_to_cart`, the upsell button of `add_to_cart.src.js`) added a sold-out variant, or more than its stock, and answered success; the cart then took the line out again. It now refuses with the catalogue's own words through `pg_cart_add_stock_refusal()`, the rule of `catalog_detail.php`.
- `stock.low` from the inventory API: a `set` that switched tracking on with a count at or under `ECOMMERCE_LOW_STOCK_THRESHOLD` announced nothing, because the stored count of the untracked product was read as the level before. The API now asks `pg_product_stock_low_crossed()`, the rule `pg_product_stock_events()` uses for every other writer (no level before = a crossing).
- The variant picker enabled Add to Cart on a sold-out variant until the background `get_product` answer came, and kept it enabled when that call failed. `setStockState()` follows the payload's `out_of_stock` at once, and enables a variant the refresh finds back in stock.

## What was left for a decision or later?
- Completion without stock per location reads the stock, then decrements in a second statement (`pg_order_complete_line_stock()`): two completions in that gap both record `stock_taken` from the stale read, so a cancel can put back more than was taken. Stock holds close the gap for checkouts; a lock or a one-statement take (`LAST_INSERT_ID(LEAST(...))`) would close it for all.
- `catalog_detail.php` compares the quantity asked with the stock, not with the stock less what the cart already holds and what other checkouts hold; the cart's `check_inventory()` trims the line afterwards.

## What was read clean?
- An option removed on the attribute screen or through the API takes its product links and a group's default with it; cart and order lines keep the product row and `product_name`, so no option text goes missing there.
- A deleted, switched off or binned variant in a cart is removed with a notice at checkout (`submit_order.php`), and `check_inventory()` trims or removes a line past the stock.
- A variant's own price (offer discount included), picture and gallery in the catalog item view payload; a variant without a picture keeps the set's.
- The column stops at 0 (unsigned, non-strict `sql_mode`), the out-of-stock flag and its bell line once per product.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/order-stock-taken.md, docs/memory/api/api-webhook-stock-events.md
