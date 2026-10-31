---
name: product-bundles
description: Product bundles (includes/fn/bundles.php): schema 11.0.54, contents, the cached stock and price and what refreshes them, the product screen card, the delete refusal
module: bundles
---

## What is stored? (11.0.54, PR #2646)
- `products.bundle`, `bundle_pricing` ('fixed'/'sum'), `bundle_discount_bp` (basis points). `product_bundle_items` (bundle_id, product_id, quantity, sort_order; UNIQUE (bundle_id, product_id)).
- `order_item_components`, `erp_invoice_item_components`: written by the sale and ERP steps.
- `erp_stock_moves`: `uniq_line_product (kind, line_id, product_id)` replaces `uniq_line` (added first: never without a unique key).
- Before the step `pg_bundles_ready()` is false: all answer empty.

## How are stock and price derived?
- `pg_bundle_stock()`: least `floor(stock / quantity)` over tracked components, negative = none; no tracked component = untracked; no contents = tracked with 0 (cannot sell). Back order only while every tracked component takes it.
- `pg_bundle_price()`: 'fixed' keeps `products.price`; 'sum' = `round(sum(price × qty) × (10000 − bp) / 10000)`.
- `pg_bundle_refresh($id)` writes what changed: stock through `pg_product_stock_changed(..., 'bundle')`, price with `pg_price_history_record()` and the marketplace queue.

## What refreshes a bundle?
- `pg_marketplace_product_changed()` calls `pg_bundle_refresh_for()` first: every stock and price writer ends there, `pg_product_stock_changed()` included; a static guard skips the bundle's own call.
- `import_products.php` ends with `pg_bundle_refresh_all()`, which also overwrites a stock or price the file gave a bundle.
- A bundle's stock is never written directly: inventory API 409 `bundle` (bulk: status `bundle`); barcode screens and ERP stock count refuse it; bulk edits (`edit_products.php`, `handle_products_bulk.php`) end with `pg_bundle_refresh()`; the quick stock cells (`pg_pb_quick_update()` code `bundle`, #2826; Catalog Manager `handle_catalog_edit.php`) refuse it.
- A sum-priced bundle takes no typed price: API bulk prices (status `bundle`), the Products list quick price (code `bundle`) and the Catalog Manager's refuse it (`pg_bundle_price_is_summed()`). `pg_bundle_refresh_for()` never refreshes the edited bundle itself, so a typed value would stay until a component changed.

## How is a bundle sold? (#2691)
- Counter sale (`local_sale_complete()`): a bundle line calls `pg_bundle_take_stock()`: one `order_item_components` row per component (unit_cost from `erp_product_costs.avg_cost`), each tracked component goes down; `$stock_left` is shared.
- Marketplace import (`mp_order_take_stock()`) the same, source `marketplace`; cancel restock (`mp_order_return_stock()`) returns the snapshot's components (`pg_bundle_return_stock()`).
- Checkout (#2826): `pg_order_complete_line_stock()` sends a line passing `pg_bundle_sale_takes_components()` (pure) to `pg_bundle_take_stock(..., 'checkout', $stock_left)` before the plain decrement.
- `check_inventory()`: a cart holding a bundle goes to `pg_bundle_check_inventory()`: every line against one stock pool (`pg_bundle_cart_allowance()`, id order).

## Where else is a bundle handled?
- ERP: one invoice line, a stock move per component (`erp/erp-bundle-lines.md`). Storefront: [[product-bundles-storefront]]. Copy: [[product-duplicate]].

## What does the product screen do?
- Edit screen only: Bundle card (`pg_pb_render_bundle_card()`, `assets/js/product_bundle.js`), search via `add_order.php?request=search_products`; hidden `bundle_card` marks a save carrying it.
- `pg_pb_validate_product_post()` refuses bad contents (`pg_bundle_items_problem()`: not found, bundle, donation, gift card, recurring; a component cannot become a bundle). While on, posted stock is ignored; off empties the contents.

## Can a component be deleted?
- No: `pg_pb_delete_product()` returns false for a component and deletes a bundle's own contents rows; API `DELETE` 409 `product_in_bundle`. The Recycle Bin refuses a component (`pg_catalog_bin_product()` false; screen, list, API, Workspace say why).
