---
name: offer-discounted-units
description: How many units of a cart line an offer discounts (cheapest item: one, gift from a paid line: the action's quantity, product or group: all) and the spread unit price that charges it
---

## How many units does a product discount cover?
- `pg_offer_action_discounted_units()`: the `cheapest` target ("the cheapest item is free") covers one unit of the cheapest line; a product or group target covers every unit of each matching line.
- A gift (`add product`) taken from a line the shopper already pays for (`apply_offer_gift_to_cart()`) covers `add_product_quantity` units of that line. The gift never raises the line's price: the gift price is capped at the price the line stands at (list, member or another offer's).

## How is a partly discounted line charged?
- `order_items.price` is one unit price, so `pg_offer_spread_unit_price()` spreads the discount of the covered units over the whole line: one free item on X×2 charges each X half.
- The spread price rounds down: the line never costs more than its units at their own prices, and the customer keeps at most quantity − 1 minor units (X×3 at 10,00 TL with one free: 6,66 each, 19,98 in all).
- A line whose every unit is covered gets the discounted price unchanged.
- `apply_offers_to_cart()` and `get_best_offer_id()` use the same spread price, so competing offers are weighed by what the cart charges.

## What was the symptom? (#725, PR #917)
- "2 al, en ucuzu bedava", "Gruptan 3 al, en ucuzu bedava" and "X al, Y bedava" made the whole line free: X×2 entirely free, ten of Y×10 free, every unit of a paid Y×5 line free when Y was the gift.

## Why is the line not split into a paid row and a free row?
- `add_order_item()` adds to every non-offer row of the same product and recipient, product form data and gift card rows hang on the line id, and offer conditions count the paid line's quantity: a split-off free unit would stop counting and the offer would undo itself on the next pass.

## What still depends on the order of adding?
- A gift placed before the shopper adds the same product stays its own `added_by_offer` row (Y×5 paid + Y×1 free); a gift placed after takes one unit of the paid line (Y×5, one of them free). Either way one Y is free.

Related: docs/memory/ui/offer-editor.md, docs/memory/commerce/cart-widget-rows-and-groups.md
