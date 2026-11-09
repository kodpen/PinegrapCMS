---
name: cart-line-removal
description: How a line leaves or joins the cart: remove_order_item(), pg_cart_remove_line(), gift refusals, the emptied recipient, shipping, required products, a refused add making no recipient
---

## What does a removal have to take with it?
- `remove_order_item()`: the line, its `order_item_gift_cards` and product form rows, and its recipient (`ship_tos` and the recipient's form rows) when it was the recipient's last line; otherwise a complete recipient's shipping cost is worked out again (`update_shipping_cost_for_ship_to()`).
- A raw `DELETE FROM order_items` leaves the empty recipient behind: express order asks an address and a shipping method for it (and draws no shipping section, so the order cannot be finished), a multi-recipient cart charges shipping for it, and a complete one keeps its old `shipping_cost` in the totals.

## Which removals go through which helper?
- Quantity 0 on the designer cart widget (`cart_action.php`) and on the classic cart (`shopping_cart.php`): `pg_cart_remove_line()` (ownership checked first on the designer cart), which records a refused offer gift and calls `remove_order_item()`.
- The remove link (`remove_item_from_cart.php`) keeps its own delete and recipient steps (it also picks the next screen), and records the refusal with the same `pg_offer_gift_refuse()`.
- A gift the offer placed by itself (`added_by_offer`) comes back on the next `apply_offers_to_cart()` unless it is recorded as refused: `pg_offer_gift_refuse($order_id, $offer_id, $offer_action_id)` writes `$_SESSION['ecommerce']['declined_offer_gifts']`, `pg_offer_gift_refused()` reads it and forgets it once the offer stops applying. Per recipient: `offer-gift-refusal.md`.

## What else does a quantity change do?
- The designer cart works a complete recipient's shipping out again when a line's quantity changes, as the classic cart does.
- The classic cart's Checkout checks required products against the lines still in the cart: its `$order_items` is read before the posted quantities are saved, so a line set to 0 in the same request no longer adds what it required.

## What was the symptom? (#731, PR #930)
- Designer cart, one recipient: a shippable line set to 0 next to a digital one left an empty recipient, and express order could not be finished; an offer's gift set to 0 came straight back.
- Classic cart: X (requires Y) set to 0 with Checkout pressed at once added Y with "X requires Y" and sent the shopper back to the cart.

Related: docs/memory/commerce/cart-widget-rows-and-groups.md, docs/memory/commerce/offer-discounted-units.md

## Which line does adding a product raise? (PR #6030)
- Only the shopper's own line: `add_order_item()` finds it (`added_by_offer = '0'`) and updates its quantity or donation price by its id. Before, the UPDATEs matched by product and recipient, so an offer's gift line of the same product in the same recipient was raised too; the AJAX add (`add_to_cart.php`, no offer pass) showed the extra gift units in the mini-cart's count and total (`offer-gift-lines.md`).

## In which language does the remove link answer an error?
- The site's: `remove_item_from_cart.php` passes its four refusals (no active cart, no `order_item_id`, line not found, line of another cart) and "Query failed." through `lang()`. Before, they were raw English on every site, e.g. a remove link followed twice (back button) showed "It might have already been removed." in English on a Turkish shop.

## When does an add create its recipient? (#7568)
- Only once the add is accepted: `add_order_item()` resolves the name with `pg_ship_to_name_for_add()`, looks it up with `pg_ship_to_find_id()` (no insert), runs the gift card line limit, and only then calls `create_or_get_ship_to()`. Before, the recipient was created first, so a gift card add refused over the line limit left an empty recipient (express order asked an address and shipping for it).
