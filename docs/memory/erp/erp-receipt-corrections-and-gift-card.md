---
name: erp-receipt-corrections-and-gift-card
description: ERP receipt cancel, settlement removal/late allocation, FX reversal, and the gift card as the only automatic settlement.
---

## How is the gift card settled?
- It is the only automatic settlement. The gift-card part of an order was paid at order time against that order.
- When the invoice is issued, `erp_settle_gift_card()` posts a credit row on the account (`kind = collection`, `doc_type = gift_card`, `doc_id = invoice_id`) and settles it to the invoice, in the same transaction. No cash movement (money already taken).
- An order fully paid by gift card is born `paid`; partly paid is `partially_paid`.
- This settlement does not block cancel: `erp_invoice_receipt_count()` does not count it. On cancel, `erp_unsettle_gift_card()` reverses the credit with a counter entry and deletes the settlement row.
- `erp_invoice_settlements()` also joins cash on `doc_type`; a gift-card row shows an empty cash name.

## How are receipts corrected? (`erp_receipt.php`, gate `manage_erp_cash`)
- Cancel = cash + account counter entries (`doc_type='cancel'`, `doc_id` = the cash movement, original `amount_base`, dated today; `erp_receipt_cancel()`).
- Cancelled state is derived from the counter-entry row (`erp_receipt_is_cancelled()`; a subquery in lists).
- Remove a settlement: `erp_settlement_remove()` → `erp_unsettle()`.
- Late settlement: `erp_settlement_allocate()` (same checks as at receipt time, `amount_base` at the receipt's rate).
- FIFO is only a suggestion (`erp_settlement_suggest()`); the write happens on the user's save.
- A foreign-currency invoice leaving `paid` calls `erp_fx_reverse_difference()`; `erp_fx_post_difference()` does not count a reversed original.
- An invoice left with no settlement gets its `payment_date` reset (except internet sales).

Related: docs/memory/erp/erp-invoice-settlement.md
