---
name: erp-order-cancel-and-refund
description: How a cancelled or refunded order reaches the ERP: the order card's two reverse steps, cancel or return invoice, the money owed per order (4.66), the card refund by line, orders writes.
---

## Does cancelling or refunding an order change the ERP?
- Not by itself: `process_order_cancellation()` and the driver's card refund write no ERP document. The order screen's documents card (`includes/erp/order_flow.php`) then offers two steps, "Return or cancellation" and "Refund of the money" (`erp_order_flow_reverse_steps()`).
- The steps appear once the order is cancelled or any amount was refunded, and its invoice is not a draft. How the card runs a step: erp-order-documents-flow.md.

## Is the invoice cancelled or answered with a return?
- Never sent to a provider (no `edoc_external_id`, `edoc_status` none/error/rejected): cancelled; the number stays in the register as cancelled.
- At a provider the driver decides (`erp_edoc_invoice_cancellable()`): a draft there is deleted first, an e-Archive invoice may be cancelled through the API or on the provider's screen, an e-Invoice gets a return. Without a driver answer, `created` (a draft waiting there) means delete it there and confirm here; anything else means a return. Nothing changes here unless the provider lets the document go (`erp_edoc_invoice_provider_cancel()`; erp-edoc-send-and-cancel.md).
- No provider (a paper invoice): cancel when the printed invoice has not reached the customer, otherwise a return. The return form opens with what is left (`add_erp_return.php?invoice_id=…&back_order=…`).
- The cancel step first removes the invoice's settlements (the money stays on the account as credit; a receipt spent wholly on this invoice is tied to the order), then `erp_invoice_cancel()` sets `orders.erp_invoice_id = 0` so the order can be invoiced again. Find an order's invoice, cancelled or not, with `erp_order_flow_invoice_row()`.

## And a free order's 0.00 invoice?
- The same steps. It reads "Returned in full" only once a return stands (`erp_order_flow_returned_in_full()`, `tests/erp_order_flow_test.php`). 0 ≥ 0 used to mark it returned with no return: the cancel was never offered and the invoice stayed issued (#1417).

## How much money goes back?
- Per order, never from the account balance: a walk-in account is shared by every counter sale. `erp_order_flow_money()`: in = settlements on the invoice (gift card excluded) + receipts recorded from the order's card (`erp_cash_transactions.order_id`, step 4.66) not allocated to it; worth = 0 for a cancelled invoice, else the invoice less its returns; owed = in − worth − payments already recorded from the card.
- Only when nothing tied to the order says what came in does the account's credit stand in, flagged as a guess on the form; never for a 0.00 invoice, which took no money.
- The step opens `add_erp_receipt.php?direction=payment` with the amount and `back_order`; the operator picks the till or bank the money left.

## How is a card refund chosen with the ERP on?
- By line and quantity (`includes/erp/order_refund.php`): the server works the amount out with `erp_return_build()` (through `erp_order_refund_amount()`), the function the return invoice uses; an amount posted by the page is never trusted. The refund stops at what is left on the card.
- After the driver refunds, the return form opens with the same quantities (`qty[line]`); nothing is issued until it is saved. Driver side: commerce/payment-refunds.md.

## Can an ERP rollback undo an orders write?
- Do not count on it. `orders` was MyISAM until 2026.4.8, and a table over the conversion limit (250,000 rows or 128 MB) stays MyISAM until the operator converts it, so the flow stays MyISAM-safe (test scripts: erp-dev-environment-traps.md).

Related: docs/memory/erp/erp-invoice-cancel-and-open-amount.md, docs/memory/commerce/order-returns-refund.md
