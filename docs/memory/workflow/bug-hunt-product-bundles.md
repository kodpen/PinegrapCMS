---
name: bug-hunt-product-bundles
description: Bug hunt of product bundles (architect 2026-11-20, agent channel message 10426, #10680): four faults fixed, three issues opened, what was read clean
module: bundles
---

## Which area was read?
- Architect (2026-11-20, agent channel message 10426, third of three areas): `includes/fn/bundles.php`, the Bundle card's save on the product screen (`product_builder.php`), the bundle tokens of the catalog widgets (`__is_bundle`, `__bundle_saving_formatted`, `product.bundle_items`). Not touched, held by open PRs: `submit_order.php`, `includes/fn/ecommerce.php`, `output.php`, `widgets.php`, `includes/payments/`, `includes/api/`.

## What did it fix? (#10680)
- A bundle with Backorder off whose components all take back orders was sold beyond its stock at checkout: the cart check limited only components without back orders. Now such a line is limited by every tracked component (`product-bundles.md`).
- The product screen let a product inside a bundle be switched into a bundle with no contents, a bundle in a bundle: `pg_bundle_switch_on_problem()`, the API's rule.
- `__bundle_saving_formatted` counted each component at its own price even when the visitor pays less for it alone (an offer, an account price), so the saving shown was more than buying the parts would save (`product-bundles-storefront.md`).
- "Discount on the Sum (%)" typed as `10%` or `% 10` was saved as no discount; one per cent sign is now read.

## Which issues did it open?
- #10682: two orders can both take the last bundle (or unit); no lock between the submit check and the take. Needs a decision (lock, reservation or refund).
- #10683 needs no decision; a separate worker fixes it in `ecommerce.php`. #10682 and #10684 wait for the owner (polls 172 and 171): architect 2026-11-20, agent channel message 10458.

## What is a known limit until #10682 is decided?
- The take stops at 0, so an oversell shows only through the `out_stock` bell, once per product (architect 2026-11-20, agent channel message 10458).
- #10683: the plain `check_inventory()` path still cuts a line below its minimum and the checkout bounces (`cart-stock-below-minimum.md`), `ecommerce.php`.
- #10684: a marketplace cancel puts back component units a bundle sale never took (back order, short stock); recording the taken quantity needs a schema step.

## What was read clean?
- Cached stock and price, `pg_bundle_refresh()` and every path to it: a component's stock or price write through `pg_marketplace_product_changed()`, order completion (`pg_bundle_take_stock()`), the marketplace cancel, the ERP stock moves and counts, bulk edits, the import's `pg_bundle_refresh_all()`.
- The back order cache (`pg_bundle_backorder()`), stock below the minimum in the bundle cart path, a bundle containing itself (dropped by `pg_bundle_items_normalize()`), a bundle chosen as a component (refused), a deleted component (refused), a switched-off component (still counted and sold, by the #2902 decision).
- The sum price and the saving in minor units with `round()`; a wholesale account's price reaches the bundle through `get_discounted_product_prices()` like any product.
