---
name: product-bundles
description: Product bundles (includes/fn/bundles.php): schema 11.0.54, contents, the cached stock and price and what refreshes them, the product screen card, the delete refusal
module: bundles
---

## What is stored? (11.0.54, PR #2646)
- `products.bundle`, `bundle_pricing` ('fixed'/'sum'), `bundle_discount_bp` (basis points). `product_bundle_items` (bundle_id, product_id, quantity, sort_order; UNIQUE (bundle_id, product_id)).
- `order_item_components` and `erp_invoice_item_components` are created empty: the sale and ERP steps write them.
- `erp_stock_moves`: `uniq_line_product (kind, line_id, product_id)` replaces `uniq_line (kind, line_id)`: added first, the old key dropped only once the new one exists, so a rerun is a no-op and the ledger is never without a unique key.
- Before the step `pg_bundles_ready()` is false and every function answers empty.

## How are stock and price derived?
- `pg_bundle_stock()`: least `floor(stock / quantity)` over tracked components, negative stock counts as none; no tracked component = untracked bundle; no contents = tracked with 0 (an empty bundle cannot be sold). Back order kept only while every tracked component takes it.
- `pg_bundle_price()`: 'fixed' keeps `products.price`; 'sum' = `round(sum(price × qty) × (10000 − bp) / 10000)`.
- `pg_bundle_refresh($id)` writes what changed: stock through `pg_product_stock_changed(..., 'bundle')`, price with `pg_price_history_record(..., 'panel')` and the marketplace queue.

## What refreshes a bundle?
- `pg_marketplace_product_changed()` calls `pg_bundle_refresh_for()` first: every stock and price writer ends there, `pg_product_stock_changed()` included. A static guard stops the bundle's own call from looking up again.
- `import_products.php` ends with `pg_bundle_refresh_all()`, which also overwrites a stock or price the file gave a bundle.
- Not yet refused (sale-paths step): quick edit, bulk edit, barcode and the inventory API can still write a bundle's stock; the next refresh replaces it.

## How is a bundle sold? (#2691)
- Counter sale (`local_sale_complete()`): a bundle line calls `pg_bundle_take_stock()` instead of the plain decrement: one `order_item_components` row per component (quantity per bundle, `erp_product_costs.avg_cost` as unit_cost when the ERP table exists), then each tracked component with stock goes down by quantity × line quantity, out-of-stock flag and `pg_product_stock_changed()` as a plain sale; `$stock_left` is shared with plain lines.
- Checkout does not yet: its stock loop moves into the order completion function first (plan step 2c).
- `check_inventory()`: a cart holding a bundle goes to `pg_bundle_check_inventory()`, which checks every line against one stock pool (`pg_bundle_cart_allowance()`, lines in id order); a cart without one keeps the old loop unchanged.

## What does the product screen do?
- Edit screen only: the Bundle card (`pg_pb_render_bundle_card()`, `assets/js/product_bundle.js`), search through `add_order.php?request=search_products` (enabled products only). Hidden `bundle_card` marks a save that carries it.
- `pg_pb_validate_product_post()` refuses bad contents before writing (`pg_bundle_items_problem()`: not found, a bundle, donation, gift card, recurring; a component cannot become a bundle). While on, the posted stock fields are ignored and the stored cache kept; switching off empties the contents.

## Can a component be deleted?
- No: `pg_pb_delete_product()` returns false for a product in a bundle and deletes a bundle's own contents rows. Callers: product screen (error), list bulk delete (skipped), variant set (not counted), Recycle Bin purge (error, stays in the bin), API `DELETE` (409 `product_in_bundle`), Workspace change (error). Moving a component to the Recycle Bin is still allowed.
