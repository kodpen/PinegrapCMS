---
name: gift-card-line-credit-cap
description: Store credit pays at most the part of a checkout total that is not gift card lines (pg_gift_card_applied_discount), in submit_order, the express order widget and both classic pages (#10258)
module: widgets_cart
---

## What may store credit pay on a mixed cart?
- At most the total less the gift card product lines charged today: their price × quantity, less their share of the order discount (`pg_tax_spread()` over the amounts), plus their tax once the discount is off (`pg_order_line_charged_taxes()`). Never below 0. Shipping and the other lines are paid by credit as before.
- The gift card line is then paid by another method. An order of gift cards alone still takes no card at all (`submit_order()` removes the applied rows).

## Why?
- Owner rule #5039: credit never pays for a gift card product (`store-credit-rules.md`). Before #10258 only the gift-cards-alone order was refused: ₺101 of credit on a ₺1 product and a ₺100 e-gift card charged ₺0 and issued a plain ₺100 card with no owner and no last day.

## Where does it live?
- Pure helpers in `includes/fn/widgets_cart.php`, beside `pg_express_order_charged_totals()`: `pg_gift_card_lines_part($charged, $discount)` (lines carry a `gift_card` flag; `pg_order_charged_today_totals()` sets it), `pg_gift_card_applied_discount($total, $part, $credit, $cards)` returns credit, cards and their sum.
- DB readers: `pg_gift_card_applied_balances($order_id)` splits the applied rows (credit = a card with `user_id`; Givex and cards of no account are plain); `pg_order_items_gift_card_lines_part($item_ids, $discount)` for pages holding line ids.
- Four callers, so the total shown is the total charged: `submit_order()`, `pg_express_order_charged_totals()` (its balance is `['credit', 'cards']`; one int counts as credit), `get_express_order()` (system and custom layout) and `get_order_preview()` (both).

## What about plain gift cards?
- They are not capped: they pay what is left of the total after credit. Whether they take the same cap is with the architect (channel message 8799).
- Rows are still redeemed by id (`pg_order_complete_gift_cards()`) up to the order's gift card discount, with no limit per kind.

Tests: `tests/gift_card_pays_gift_card_test.php`.
