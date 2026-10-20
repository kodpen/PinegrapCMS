---
name: offer-gift-lines
description: An offer's gift line ('add product' result): the price it is charged at (never below zero), which recipient it goes to with one or several recipients, the unshipped group 0
---

## What price does a gift line get?
- `pg_offer_gift_price($product_price, $amount, $percentage)`: the product's price less the action's amount, or less its percentage; rounded minor units, never below 0. Every place that prices a gift uses it: `apply_offer_gift_to_cart()` (first placement), the gift-line pass at the top of `apply_offers_to_cart()` (rewrites price and quantity on every cart pass), `get_best_offer_id()` and the claim button's `add_pending_offers()`.
- The editor does not cap an amount at the product's price, and a product's price can drop after the offer was set up; without the floor the gift line was rewritten with a negative price on the next cart pass and took money off the subtotal (`order_items.price` is a signed INT).

## Which recipient does a gift go to?
- Shipping off, or the gift not shippable: no recipient (`ship_to_id` 0).
- One recipient (`ECOMMERCE_RECIPIENT_MODE` `single recipient`): `apply_offer_gift_to_cart()` puts a shippable gift in the buyer's recipient, `create_or_get_ship_to('', '')`, the one `add_order_item()` gives every shippable product, so weight, shipping cost and the destination check count it. The recipient is found or created only right before the gift's INSERT, so a refused gift leaves no empty recipient (`remove_order_item()` deletes a recipient whose last line goes).
- Several recipients: an order-scope offer asks `get_ship_to_id_for_offer_gift()` (the one paying recipient, else the claim button asks); a recipient-scope offer walks the cart's recipients and skips group 0, the items that are not shipped, which is no recipient.

## What was the symptom? (#727, PR #923)
- A gift with an amount discount above its price: 0 on the first pass, then a negative line price on every later pass, so the subtotal dropped and a small cart could total below zero.
- With one recipient a shippable gift was added with `ship_to_id` 0: outside shipping cost, weight and the zone check, taxed and listed with the unshipped items. With several recipients a recipient-scope gift could land in group 0 the same way.

## What is not moved?
- A gift line already in a cart with `ship_to_id` 0 from before the fix stays there until the cart drops it; the "already placed" check does not move it.

Related: docs/memory/commerce/offer-discounted-units.md, docs/memory/commerce/cart-widget-rows-and-groups.md
