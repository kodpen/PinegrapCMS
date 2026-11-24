---
name: bug-hunt-areas-shipping-rates
description: The bug hunt of shipping rates (area 34, 2026-11-24): zones, base rate bands, free shipping offers, a method for an address it does not serve; three faults fixed, what was read clean
---

## Which area was hunted?
- Area 34, shipping rates: shipping methods and their prices, zones and weight or price bands at their edges, free shipping over a limit with a discount, a method shown for an address it does not serve, a basket with digital and physical items, tax on shipping, a rate changed between cart and payment.
- Read: `shipping.php` (`update_shipping_cost_for_ship_to()`, `get_shipping_method_base_rate()`, `get_shipping_methods()`, `check_shipping_method()`), `shipping_method.php`, `get_shipping_method.php`, the cart's Checkout re-check in `shopping_cart.php`, `express_order.php`'s recipient save, `get_valid_zones()`, `get_valid_zones_for_destination()`, `validate_product_for_destination()`, `get_best_shipping_discount_offer()`, the "discount shipping" branch of `apply_offers_to_cart()`.

## What did the hunt's PR fix?
- The cart's Checkout button kept a completed recipient on a method that no longer served its zone or address type, priced it with a zone looked up again by destination only, and carried the previous recipient's zone rates into a recipient on a forced method. It now uses `check_shipping_method()` and `update_shipping_cost_for_ship_to()` (`commerce/shipping-rate-paths.md`).
- The classic Shipping Method screen showed a forced method with the zone rates of the method listed before it, while `shipping_method.php` charges it none: rates reset per method.
- A zone listing one country's state matched another country's state with the same code (WA): zones and product destinations now match the state by id.

## What was read clean?
- Base rate bands: a band starts at its subtotal (`<` the next limit), on discounted line prices of active lines. Free-shipping products: left out of weight and item rates and of the real-time parcel. Digital lines have no recipient, so they move no band. Percent shipping discounts: `pg_percent_off()`. Cut-off and arrival checks of `check_shipping_method()`.
- Tax on shipping: `erp/erp-order-charges-vat.md`. The payment charges the stored `ship_tos.shipping_cost` that the Order Preview shows.

## What was left?
- A "discount shipping" offer's required subtotal is compared with the subtotal before the order discount (a coupon), as every offer rule is: not changed, a product choice.
- Base rate and zone base rate are charged even when every line has free shipping: kept, as every copy does it.

## What was not run?
- No database or storefront: the paths are pinned by `tests/shipping_rate_recheck_test.php`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/shipping-rate-paths.md
