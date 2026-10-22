---
name: offer-donation-lines
description: Donation lines and offers: never the cheapest item, never discounted by a product offer, their typed amount kept when offers are taken off the cart (#987, PR #PRNUM)
---

## How do offers treat a donation line?
- A donation line (`products.selection_type = 'donation'`) carries the amount the visitor typed in `order_items.price`; the donation product's own price is usually 0. It is not an item for the offer engine.
- `pg_offer_cheapest_line_id($lines)` (pure, behind `pg_offer_cheapest_item_id()`) skips donations, lines an offer added and free lines; a tie goes to the lowest row id.
- `pg_offer_action_targets_item()` answers false for a donation, so no `discount product` action (cheapest, product or group target) takes it, in `apply_offers_to_cart()` and in `get_best_offer_id()` alike. Both cart queries select `products.selection_type AS product_selection_type` for it.
- `pg_offer_restored_line_price()` is the price the start of `apply_offers_to_cart()` puts back on a line an offer discounted: the member's agreed price or the list price, and null for a donation, whose price is left alone (only `offer_id` and `discounted_by_offer` are cleared). `update_order_item_prices()` already skipped donations.

## What was the symptom? (#987)
- "2 al, en ucuzu bedava" with Y×2 at 10,00 and a 5,00 donation picked the donation as the cheapest item and set it to 0; Y stayed at full price.
- When the offer stopped applying, the reset wrote the donation product's price (0) back: the 5,00 was lost. A donation re-typed in the cart kept `discounted_by_offer = 1` and the next pass zeroed it again.
- A line already zeroed by the old code keeps its 0 until the visitor types the amount again; nothing restores it.

## What is still open?
- The cheapest item can still be a gift card product or a subscription line (a free subscription line is free in every period). Whether that is wanted is an offer decision; not changed.

Related: docs/memory/commerce/offer-discounted-units.md, docs/memory/commerce/offer-gift-lines.md, docs/memory/commerce/cart-widget-rows-and-groups.md
