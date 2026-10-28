---
name: product-bundles
description: Product bundles (includes/fn/bundles.php): schema 11.0.54, contents, the cached stock and price and what refreshes them, the product screen card, the delete refusal
module: bundles
---

## What is stored? (11.0.54, PR #2646)
- `products.bundle`, `bundle_pricing` ('fixed'/'sum'), `bundle_discount_bp` (basis points). `product_bundle_items` (bundle_id, product_id, quantity, sort_order; UNIQUE (bundle_id, product_id)).
- `order_item_components` and `erp_invoice_item_components` are created empty: the sale and ERP steps write them.
- `erp_stock_moves`: `uniq_line_product (kind, line_id, product_id)` replaces `uniq_line (kind, line_id)`, added before the old one is dropped (rerun-safe, never without a unique key).
- Before the step `pg_bundles_ready()` is false; every function answers empty.

## How are stock and price derived?
- `pg_bundle_stock()`: least `floor(stock / quantity)` over tracked components, negative stock counts as none; no tracked component = untracked bundle; no contents = tracked with 0 (an empty bundle cannot be sold). Back order kept only while every tracked component takes it.
- `pg_bundle_price()`: 'fixed' keeps `products.price`; 'sum' = `round(sum(price × qty) × (10000 − bp) / 10000)`.
- `pg_bundle_refresh($id)` writes what changed: stock through `pg_product_stock_changed(..., 'bundle')`, price with `pg_price_history_record()` and the marketplace queue.

## What refreshes a bundle?
- `pg_marketplace_product_changed()` calls `pg_bundle_refresh_for()` first: every stock and price writer ends there, `pg_product_stock_changed()` included. A static guard stops the bundle's own call from looking up again.
- `import_products.php` ends with `pg_bundle_refresh_all()`, which also overwrites a stock or price the file gave a bundle.
- A bundle's stock is never written directly: the inventory API answers 409 `bundle` (bulk items: status `bundle`), the barcode screens and the ERP stock count refuse it, the list's bulk edits (`edit_products.php`, `handle_products_bulk.php`) end with `pg_bundle_refresh()`, its quick edit of the stock (`pg_pb_quick_update()`) answers code `bundle` (#2c).

## How is a bundle sold? (#2691)
- Counter sale (`local_sale_complete()`): a bundle line calls `pg_bundle_take_stock()` instead of the plain decrement: one `order_item_components` row per component (unit_cost from `erp_product_costs.avg_cost`), each tracked component goes down as in a plain sale; `$stock_left` is shared.
- Marketplace import (`mp_order_take_stock()`) the same, source `marketplace`; its cancel restock (`mp_order_return_stock()`) puts back the snapshot's components (`pg_bundle_return_stock()`).
- Checkout (#2c): `pg_order_complete_line_stock()` routes a line through `pg_bundle_sale_takes_components()` (pure: a bundle with an order item id) to `pg_bundle_take_stock(..., 'checkout', $stock_left)` and returns before the plain decrement.
- `check_inventory()`: a cart holding a bundle goes to `pg_bundle_check_inventory()`: every line against one stock pool (`pg_bundle_cart_allowance()`, lines in id order); other carts keep the old loop.

## What does the ERP do with a bundle line?
- One invoice line, one stock move per component: `erp/erp-bundle-lines.md`.

## What does the product screen do?
- Edit screen only: the Bundle card (`pg_pb_render_bundle_card()`, `assets/js/product_bundle.js`), search through `add_order.php?request=search_products`. Hidden `bundle_card` marks a save that carries it.
- `pg_pb_validate_product_post()` refuses bad contents before writing (`pg_bundle_items_problem()`: not found, a bundle, donation, gift card, recurring; a component cannot become a bundle). While on, posted stock fields are ignored; switching off empties the contents.

## Can a component be deleted?
- No: `pg_pb_delete_product()` returns false for a product in a bundle and deletes a bundle's own contents rows; API `DELETE` answers 409 `product_in_bundle`. The Recycle Bin refuses a component too (`pg_catalog_bin_product()` false; screen, API and Workspace say why).
