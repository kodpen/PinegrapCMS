---
name: order-gift-card-owed
description: The gift card part an order owes back is what the cards gave (applied_gift_cards.amount) less gift card returns, never gift_card_discount; pg_order_gift_card_owed*() and the ERP settlement (#10260)
module: ecommerce
---

## What is the gift card part a cancelled order owes back?
- What the cards really gave, less what a return already gave back of it, never below 0: `SUM(applied_gift_cards.amount)` of the order minus `SUM(order_refunds.amount_cents)` of its rows with `refund_type = 'return_gift_card'`. No schema; `orders.gift_card_discount` is not read and stays as checkout wrote it (what was asked of the cards).
- Why: `pg_order_complete_gift_cards()` writes `amount` on each card it redeemed and deletes the row of a card that failed (spent by another order at the same moment, deleted, a Givex error), but `gift_card_discount` keeps the asked figure. Symptom fixed (#10260): a card that failed was offered back as ₺50 store credit on cancel, and the ERP invoice was settled by ₺50 of gift card never received.

## Which helpers give it? (`includes/fn/ecommerce.php`)
- `pg_order_gift_card_redeemed_sql($order_id_sql = 'orders.id')`: the first term, a correlated sub-select of `applied_gift_cards.amount`.
- `pg_order_gift_card_owed_sql($order_id_sql = 'orders.id', $has_order_refunds = true)`: `GREATEST(0, redeemed - returned)`, two correlated sub-selects; `false` leaves the return term out (schema before `order_refunds`, which came with `orders.refunded_amount` in 2026.1.14).
- `pg_order_gift_card_owed_amount($redeemed, $returned)`: the same in PHP, pure.
- `pg_order_gift_card_owed($order_id)`: one order from the database, 0 on a failed query.
- The `return_gift_card` rows are written by a return's credit or manual refund beyond the payment money (`pg_return_refund()`, #10259, `gift-card-part-owed.md`); its plan reads `pg_order_gift_card_owed()`.

## Who reads it?
- `pg_order_cancel_refund_owed($order)` takes `gift_card` from `$order['gift_card_owed']` when the caller selected it (`... AS gift_card_owed`), otherwise asks `pg_order_gift_card_owed($order['id'])`. `pg_order_cancel_refund_owed_sql()` adds `pg_order_gift_card_owed_sql()`. So the order screen banner, its "Gift cards paid" sentence and the Give it as store credit amount, cancel notices, bulk cancel, the Refund Pending card and the dashboard refund lane show one figure.
- The dashboard refund list (`pg_dashboard_ops_refund_list()`) selects `gift_card_owed`, so its rows make no query each.

## What does the ERP invoice settle?
- `erp_invoice_from_order_locked()` reads the first term alone into `$order['gift_card_redeemed']`; `erp_order_lines()` puts it in `gift_card_total` (0 when absent), which `erp_settle_gift_card()` settles. The invoice still states the whole sale (`total + gift_card_discount`): a card that failed leaves that part owed on the account. What a return gave back is the return's own document, so the settlement does not subtract it.

Test: `tests/order_gift_card_owed_test.php`. Related: `order-cancel-gift-cards.md`, `erp/erp-order-invoice-header.md`.
