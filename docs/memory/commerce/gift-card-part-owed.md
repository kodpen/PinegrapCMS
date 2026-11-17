---
name: gift-card-part-owed
description: How an order's gift card part is counted (architect 2026-11-17, #10259, #10260): the cards gave SUM(applied_gift_cards.amount); a return gave back order_refunds rows of type return_gift_card
---

## What did gift cards pay of an order?
- Architect (2026-11-17, agent channel message 8803): the sum of the order's `applied_gift_cards.amount`, not `orders.gift_card_discount`. `pg_order_complete_gift_cards()` writes `amount` on each row it redeemed and deletes the rows that failed; `pg_order_gift_card_refund_line()` reads the same rows.
- Why: after a redemption that fell short (two orders on one card at once; the order is accepted and the store gets the "Gift card error" mail) `orders.gift_card_discount` keeps the amount asked for. A cancellation then owed it, "Give it as store credit" issued credit for money no card gave, and ERP settled the invoice with it (bug #10260).
- `orders.gift_card_discount` stays as written and is not a fallback for the sum. The ERP bridge (`includes/erp/order_bridge.php`, `gift_card_total`) settles the sum alone.
- No column was added: both figures come from rows that exist.

## Where is the gift card part a return gave back counted?
- Architect (2026-11-17, the same message): in `order_refunds`, as rows of `refund_type` `return_gift_card`. The count is the `SUM(amount_cents)` of the order's rows of that type.
- A `credit` or `manual` refund of a return splits at the payment money left (`remaining` of `pg_return_refund_plan()`). The payment part keeps its row (`return_credit` / `return_manual`) and is what `orders.refunded_amount` grows by. The part beyond it is a second row of type `return_gift_card` with the same note (`Return request #<id>`) and reference; it adds nothing to `refunded_amount`, which is payment money alone. `order_returns.refund_amount` keeps the whole amount.
- The `driver` method keeps the payment cap: a payment service cannot give back what a card paid. `credit` and `manual` go up to the payment left plus the gift card part still owed.
- The refund bonus is on the payment part only, `min(amount, payment left)` (owner, #7017, `store-credit-refunds.md`).
- Not the sum of the order's refund rows less `refunded_amount`: refunds older than the table (2026.1.14) have no row, so the difference undercounts and the part could be given twice.
- Why: `pg_return_refund()` refused every amount above the payment left, so what store credit or a gift card paid could not come back through a return, not even as credit (bug #10259).

## What does an order still owe of its gift card part?
- What the cards gave less what returns gave back, never below 0: one helper in `includes/fn/ecommerce.php` and its SQL form. `pg_order_cancel_refund_owed()`, its `_sql` twin and the return's plan read it, so a cancellation after a return does not give the part twice.
- A return is refunded only while its order is complete or exported (`pg_return_refund_order_open()`); a cancellation gives all that is owed at once and writes no `return_gift_card` row.
- The fix of #10260 writes the helper, the fix of #10259 the rows; each corrects this file to what it built.

Related: docs/memory/commerce/store-credit-refunds.md, docs/memory/commerce/order-returns-refund.md, docs/memory/commerce/order-cancel-gift-cards.md, docs/memory/commerce/order-refunded-amount-writes.md
