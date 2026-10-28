---
name: storefront-bundles-hooks
description: Bundles design note, part 2: hook points checked in the code, steps, owner questions
---

## Where does a bundle hook in? (git grep, 2026-10-26)
- Cart: `add_order_item()` and `check_inventory()` (`includes/fn/ecommerce.php`) need nothing for the line itself; `check_inventory()` and `submit_order.php` add the demand of a bundle and of the same component bought alone, so a cart cannot sell one component twice.
- Sale: the stock loop of `submit_order.php` (after the order is saved) and `local_sale_complete()` (`includes/local_sale.php`) take a bundle line off its components (fresh read, `$stock_left`, `pg_product_stock_changed()` per component), write `order_item_components`, then refresh the bundle. `mp_order_take_stock()` (`includes/api/outbound/orders.php`) the same; its refund restock at line ~551 puts components back.
- `inventory.changed`: `pg_product_stock_changed()` announces per component; `pg_stock_notify_heard()` (`includes/fn/stock_notify.php`) hears the bundle's own one from the refresh, unchanged.
- ERP: `erp_order_lines()` (`includes/erp/order_bridge.php`) keeps one line; the bridge copies `order_item_components` into `erp_invoice_item_components`. `erp_stock_post_invoice()` (`includes/erp/stock.php`) expands a bundle line into one move per component (line net split by component cost); `erp_stock_post_return()` and `erp_stock_post_cancel()` follow the moves by `reverses_id`; `erp_stock_apply_pending()` unchanged. A manual sales invoice of a bundle snapshots `product_bundle_items` and counts as today.
- API: `api_product_present()` / `api_product_extras()` (`includes/api/resources/products.php`) add the bundle fields and `bundle_items` (batch read); `api_inventory_apply()` (`includes/api/resources/inventory.php`) refuses a bundle id; `includes/api/schema.php`.
- Catalog widget: the token rows of `_render_system_widget_catalog_listing()` and `_render_system_widget_catalog_item_view()` (`includes/fn/widgets_catalog.php`) get `__is_bundle`, `__bundle_saving_formatted`; contents as a list source in `includes/fn/widgets_lists_product.php`. `get_catalog.php` unchanged.
- Designer binding: catalog token palette and `SW_VISIBILITY_FLAGS` in `assets/js/style_designer.js` (`_sdT()`), `check_bindings.php`.
- Product screen: a contents card (`edit_product.php`, `product_builder.php`); `pg_pb_delete_product()` refuses a component.
- Feeds: `pg_product_feed_availability()` reads the cached stock.

## How is the work cut? (about 1,000 product lines each)
1. Done (#2646): schema 11.0.54, `includes/fn/bundles.php`, refresh, product screen card, delete refusal (`commerce/product-bundles.md`).
2a. Done (#2691). Counter sale (`local_sale_complete()`) takes a bundle off its components with the `order_item_components` snapshot (`pg_bundle_take_stock()`, ready for the checkout); combined demand in `check_inventory()` (`pg_bundle_cart_allowance()`).
2b. Marketplace import (`mp_order_take_stock()`) and its refund restock through `pg_bundle_take_stock()`, refusals in the inventory API, barcode, stock count, the product list's quick and bulk edit of a bundle's stock; refuse binning a component (step 1 refuses only the permanent delete).
2c. Checkout: the stock loop of the order completion calls `pg_bundle_take_stock($order_item_id, $bundle_id, $quantity, 'checkout', $stock_left)` for a bundle line instead of the plain decrement. Waits for order-completion A2b (#2687), which moves that loop out of `submit_order.php`; build on its function once merged.
3. ERP: component snapshot on invoice lines, the `erp_stock_moves` key, component moves on post, return and cancel, profit report check.
4. API fields, catalog tokens, list source, designer palette and flags.

## What did the owner answer? (2026-10-27, #2425)
- All six as recommended: the stock cache in `products.inventory_quantity`, no nesting or choice inside a bundle, a component in a bundle cannot be deleted, one invoice line with the bundle's tax rate, the schema as designed (the `erp_stock_moves` key widened), no marketplace listing in the first version. Step 1 may be claimed.
