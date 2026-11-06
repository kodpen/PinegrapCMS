---
name: offer-rule-gift-lines-not-counted
description: An offer's required products, group condition and up-sell count only lines the shopper added (added_by_offer = 0), so a gift cannot keep its own offer alive
---

## Which cart lines does an offer's product rule count?
- `check_for_products_in_cart()` (root `check_for_products_in_cart.php`) counts active lines (`pg_order_item_active_sql()`) with `added_by_offer = '0'` only. It serves `validate_offer()` (the `offer_rules` required products, order and recipient scope), `pg_offer_cart_conditions_met()` (product-group condition), `pg_offer_group_upsell_met()` and `check_upsell()`.
- Same rule as the "cart quantity" condition (`pg_offer_cart_quantity()`): a line an offer added never helps reach the threshold that earns it.
- A gift taken from a paid line of the same product (`apply_offer_gift_to_cart()`) keeps `added_by_offer = 0`, so those units still count, as before.

## What was the symptom?
- "Buy 2 from X or Y, get Y free": X x2 placed the free Y as its own line. With one X taken out, X x1 plus the gift Y made 2, the gift-line pass of `apply_offers_to_cart()` found the offer still valid and the free Y stayed on one paid item.

## What else changes?
- The order form's autoselect product (`order_form.php`) asks the same function whether the product is already in the cart: a gift copy of it no longer counts, so the form adds the paid line it promises (`add_order_item()` never merges into an offer line).

## What is not covered?
- A required subtotal still counts a gift line charged at a price (`get_order_subtotal()` sums every active line), so "spend 100, get Y at half price" stays valid on 85 paid plus the 20 gift line.
