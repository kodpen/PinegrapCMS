---
name: shipping-rate-paths
description: Where a recipient's shipping cost is worked out (five copies of one formula), which one charges, how the cart's Checkout button re-checks a chosen method, and how a zone matches a state by id
---

## Which code works out a shipping cost?
- One formula, five copies: `update_shipping_cost_for_ship_to()` (`shipping.php`, prices a stored recipient), `get_shipping_methods()` (express order and API list), `shipping_method.php` (classic Shipping Method submit), the two listings of `get_shipping_method.php` (classic screen). A change to the formula goes into every copy.
- Formula: real-time rate + method base rate (`get_shipping_method_base_rate()`, bands by the recipient's subtotal of discounted line prices, a band starts at its subtotal) + zone base rate + per line (weight points and item rate of method and zone, extra shipping cost) for lines without free shipping, then the best "discount shipping" offer.

## Which zone is charged?
- `ship_tos.zone_id`, written by `shipping_method.php` and `express_order.php` from `check_shipping_method()`; 0 / empty for a method forced onto the destination when no method serves every product, which has no zone rates.
- A listing starts every method from no zone rates, so a forced method listed after a zoned one never shows that zone's rates (`get_shipping_methods()`, and `get_shipping_method.php` since the shipping rates hunt).

## What does the cart's Checkout button re-check?
- Every complete recipient: its lines still go to the destination (`validate_product_for_destination()`), then `check_shipping_method()` (on, dates, address type, day and cut-off time, pickup location, zone still served or still the forced method, arrival date); then it stores the zone the method serves now and prices with `update_shipping_cost_for_ship_to()`. Any failure marks the recipient incomplete and sends the shopper to the shipping screens.
- Before the shipping rates hunt it ran its own copy: no address type or zone check, a zone looked up again by destination only, and zone rates carried from one recipient to the next.

## How does a zone match a state?
- By the state's id inside the recipient's country (`get_valid_zones_for_destination()`, `validate_product_for_destination()`): a state code is unique only per country (WA is Washington and Western Australia). Before, a zone listing one country's state matched another country's state with the same code.

Related: docs/memory/commerce/shipping-label-checkout-rate.md, docs/memory/commerce/offer-percent-rounding.md, docs/memory/commerce/orders-list-state-filter.md
