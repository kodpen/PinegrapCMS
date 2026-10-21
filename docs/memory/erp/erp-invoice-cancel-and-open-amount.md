---
name: erp-invoice-cancel-and-open-amount
description: When an ERP invoice can be cancelled, what cancel does to ledger, number, gift card, stock and the order, and how open amount and paid status treat returns.
---

## When can an invoice be cancelled?
- `erp_invoice_cancel()` (`includes/erp/returns.php`) only when nothing hangs on it: no money allocated (the gift-card allocation the invoice posted itself does not count; `erp_invoice_receipt_count()`), no return against it, and its issue date outside the closed period (erp-period-lock).
- Drafts are deleted, not cancelled (erp-manual-invoice-draft-issue). Whether an order's invoice is cancelled or answered with a return: erp-order-cancel-and-refund.

## What does cancel do?
- The ledger row is never deleted: an opposite entry (`kind = 'adjustment'`, `doc_type = 'cancel'`) reverses it, dated today, carrying the document's own currency, rate and base value.
- The number is not released: a gap that can be explained beats a number that appears twice.
- The gift-card credit and its allocation are reversed with it; every stock move the document made is turned round (`erp_stock_post_cancel()`, counts applied after the commit).
- An order that produced the invoice gets `orders.erp_invoice_id = 0` and can be invoiced again; cancelling a return gives its quantities back to the parent (`returned_qty`) and recomputes the parent's status.
- After the commit the `erp.invoice.cancelled` event is raised.

## How do open amount and paid status treat returns?
- `erp_invoice_open_amount()` deducts both settlements and returns; a cancelled invoice or a return document has nothing open.
- `erp_invoice_refresh_paid()` measures settlements against the same figure (total − returns), so the two cannot diverge.
- Only money makes an invoice "paid": a fully returned invoice that never saw a collection stays `issued`.
- Opening a return and cancelling a return both recompute the parent invoice status (`returns.php`, inside the transaction).

Related: docs/memory/erp/erp-return-documents.md, docs/memory/erp/erp-order-cancel-and-refund.md
