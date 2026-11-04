---
name: gift-card-line-quantity-limit
description: A gift card line holds at most 100 units; every door that writes a line quantity refuses more with one translated error (#6806, #6820)
module: order_completion
---

## Why is a gift card line limited to 100 units?
- A gift card line issues one card per unit, each with its own recipient form, at most 100 (`pg_order_gift_card_count()`, the 100 forms stay). A line of 150 was charged 150 × price but issued 100 cards, without a warning (#6806). The owner chose to refuse the extra units rather than issue cards without recipients (decision #6821, `workflow/decisions-made-product-29.md`).

## Which helpers check it?
- `pg_gift_card_line_quantity_allowed($quantity)` (pure): true when the quantity issues exactly as many cards as it charges (0–100).
- `pg_gift_card_line_quantity_error()`: the one error, "A gift card line can hold at most 100 units".
- `pg_order_item_quantity_allowed($order_item_id, $quantity)`: the same for a cart line by id; no query inside the limit.
- `pg_order_gift_card_lines_over_limit($order_id)`: active gift card lines over the limit.

## Where is it enforced?
- `add_order_item()` refuses a gift card add whose line (existing units + added) would pass the limit: it writes nothing, returns false and records the refusal for the request (`pg_gift_card_quantity_refusal(true)`). Callers read it once with `pg_gift_card_quantity_refusal_take()` and show the error: quick add in `shopping_cart.php`, `express_order.php`, `cart_action.php`; `add_to_cart.php` (instead of "could not be added"); `catalog_detail.php` and `order_form.php` (back to the page). A new caller of `add_order_item()` that may add a gift card does the same.
- Cart update keeps the line's quantity and marks `quantity[<id>]` (`quantities[<id>]` in `cart_action.php`) with the error.
- `submit_order()` refuses an order with a line over the limit (a cart filled before the limit) until the quantity is lowered.
- The external API order and quote creation get the same rule separately.

## Tests?
- `tests/gift_card_line_quantity_test.php`: the limit, the refusal record read once, and that every door calls the check.
