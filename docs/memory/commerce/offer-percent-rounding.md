---
name: offer-percent-rounding
description: An offer's percentage as a figure (order discount, product, gift, shipping): the exact product rounded half away from zero, pg_percent_of() / pg_percent_off(), on every PHP version (PR #1367)
---

## How does an offer's percentage become a figure?
- On the exact product, never a float `round()`: `pg_percent_of($amount, $percent)` (what is taken off) and `pg_percent_off($amount, $percent)` (what is left), both `pg_multiply_units()` with the percentage at three places (`pg_decimal_units()`), next to `pg_tax_apply_rate()` in `includes/fn/ecommerce.php`. The checkout VAT's rule: docs/memory/erp/erp-line-total-and-rate-rounding.md.
- Half away from zero, on the figure the old code rounded:
  - the order discount rounds what is taken off;
  - a product, a gift and shipping round what is left: 55% off 6.50 leaves 2.925, so 2.93, not 6.50 - 3.58 = 2.92.
- The offer percentage columns are TINYINT (whole percentages); a ladder rung is whole too.

## Which places take it?
- `pg_offer_order_discount_value()` (the session's `order_discount`, `orders.discount`; the ladder rung too) and `pg_offer_gift_price()`.
- The product discount in `apply_offers_to_cart()`, `get_best_offer_id()` and `get_discounted_product_prices()` (catalogue, feeds, price history offer rows): a whole number now, where it was a float that MySQL rounded on the INT write and that the running subtotal later offers are checked against carried with its fraction.
- The shipping discount in `apply_offers_to_cart()`, `get_best_offer_id()`, `shipping.php` (the recipient's cost and the method list), `shipping_method.php`, `shopping_cart.php` and `get_shipping_method.php` (the cost a method list shows).
- `tests/offer_percent_test.php` reads the product tree and fails on a `discount_...percentage ... / 100` left anywhere.

## What did it fix? (PR #1367)
- PHP 8.4 and later no longer pre-round in `round()`: 1310 x 0.35 = 458.4999... gave 4.58 where PHP 8.3 gave 4.59 (35% of 13.10); 29% of 14.50 (4.21) and 55% off 6.50 (2.93) were a kurus lower too. About one amount and percentage pair in 1,700 moved (one in 2,800 of what is left), at percentages such as 29, 35, 55 and 57; the same cart was charged differently by server version.
- Now no figure depends on the PHP version or the build's width (a 32-bit build takes the float steps of `pg_multiply_units()`).
- On PHP 8.3 no figure moved: the test holds the old float formulas equal there (CI runs 8.3) over 20,000 random amounts to 2^31, half of them at a half minor unit, and both helpers against an exact integer reference on every version.

## What still rounds a float percentage?
- None at the checkout. The card surcharge takes `pg_percent_of()` too (docs/memory/commerce/card-surcharge-rounding.md); the ERP storefront's best offer (`includes/erp/products.php`) uses `erp_apply_rate()`.

Related: docs/memory/commerce/offer-order-discount-ladder.md, docs/memory/commerce/offer-gift-lines.md, docs/memory/commerce/offer-discounted-units.md, docs/memory/commerce/card-surcharge-rounding.md
