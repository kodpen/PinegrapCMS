---
name: offer-gift-refusal
description: How a removed offer gift stays out of the cart, and when the refusal is forgotten in recipient-scope offers (#7664)
module: ecommerce
---

## Where is a refused gift recorded?
- `pg_offer_gift_refuse($order_id, $offer_id, $offer_action_id)` adds `<order>_<offer>_<action>` to `$_SESSION['ecommerce']['declined_offer_gifts']` when the shopper removes a line the offer placed (`added_by_offer`): `pg_cart_remove_line()`, `remove_item_from_cart.php` (`cart-line-removal.md`).
- `apply_offer_gift_to_cart()` does not place the gift again while `pg_offer_gift_refused()` answers true. The key has no recipient: one refusal covers the gift for the whole order.

## When is the refusal forgotten?
- Once the offer stops applying, so qualifying again brings the gift back.
- Order scope (or one recipient): when `validate_offer()` is false for the order.
- Recipient scope (multi-recipient carts): only when the offer qualifies for no recipient. The loop over `$ship_tos` in `apply_offers_to_cart()` collects one answer per recipient and then calls `pg_offer_gift_refusal_settle()`, which forgets the refusal when none is true (also when there is no recipient).

## What was the symptom? (#7664)
- Multi-recipient cart, recipient-scope offer with a shippable gift: recipient A qualified, B did not. The shopper removed the gift from A; on the next refresh A saw the refusal, then B (not qualifying) forgot it, and on the refresh after that the gift was back in A. It returned on every second refresh.

## Tests?
- `tests/offer_gift_refusal_test.php`: remembered once, per order, forgotten, and the A-qualifies / B-does-not sequence.
