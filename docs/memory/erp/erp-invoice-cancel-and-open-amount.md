---
name: erp-invoice-cancel-and-open-amount
description: When an ERP invoice can be cancelled, what the screen asks before the provider, what cancel does to ledger, number, gift card, stock and order, the period lock, open amount and returns.
---

## When can an invoice be cancelled?
- `erp_invoice_cancel()` (`includes/erp/returns.php`) only when nothing hangs on it: no money allocated (the gift-card allocation the invoice posted itself does not count; `erp_invoice_receipt_count()`), no return against it, and its issue date outside the closed period (erp-period-lock).
- Drafts are deleted, not cancelled (erp-manual-invoice-draft-issue). Whether an order's invoice is cancelled or answered with a return: erp-order-cancel-and-refund.

## What does the invoice screen ask before the provider?
- Cancel on `edit_erp_invoice.php` asks the refusals of `erp_invoice_cancel()` again on the row at the POST (cancelled already, money allocated, a return against it) in its words, then `erp_edoc_invoice_provider_cancel()` asks the lock, and only then is the provider asked (#PR).
- Before, a receipt allocated or a return written after the page was drawn reached the provider first: the e-Archive was cancelled at GİB (or the draft there deleted) while the invoice stayed issued and paid here.
- The receipt check is not in `erp_edoc_invoice_provider_cancel()`: the order card removes the allocations only after the provider step.

## What does cancel do?
- The ledger row is never deleted: an opposite entry (`kind = 'adjustment'`, `doc_type = 'cancel'`) reverses it, dated today, carrying the document's own currency, rate and base value.
- The number is not released: a gap that can be explained beats a number that appears twice.
- The gift-card credit and its allocation are reversed with it; every stock move the document made is turned round (`erp_stock_post_cancel()`, counts applied after the commit).
- An order that produced the invoice gets `orders.erp_invoice_id = 0` and can be invoiced again; cancelling a return gives its quantities back to the parent (`returned_qty`) and recomputes the parent's status.
- After the commit the `erp.invoice.cancelled` event is raised.

## What does the period lock do to a cancel?
- An invoice dated on or before the lock date cannot be cancelled; it is taken back with a return dated in the open period.
- The lock is asked before any side effect (PR #1225, #1070): `erp_edoc_invoice_provider_cancel()` refuses first, so the provider's draft is not deleted and an e-Archive document is not cancelled there; the order card's step offers the return instead of "Cancel the invoice" and its run never removes the allocations; `edit_erp_invoice.php` shows the cancel button disabled with the reason.
- Before, the provider was taken back and the order card's allocations removed, and only `erp_invoice_cancel()` refused: the invoice stayed issued, unpaid, with nothing at the provider.

## How do open amount and paid status treat returns?
- `erp_invoice_open_amount()` deducts both settlements and returns; a cancelled invoice or a return document has nothing open.
- `erp_invoice_refresh_paid()` measures settlements against the same figure (total − returns), so the two cannot diverge.
- Only money makes an invoice "paid": a fully returned invoice that never saw a collection stays `issued`; a receipt never goes to a return (erp-receipt-not-for-return).
- Opening a return and cancelling a return both recompute the parent invoice status (`returns.php`, inside the transaction); on a foreign-currency parent the exchange difference is posted when it reaches `paid` and reversed when it leaves it (#1071, erp-money-and-currency).
Related: docs/memory/erp/erp-return-documents.md, docs/memory/erp/erp-order-cancel-and-refund.md, docs/memory/erp/erp-receipt-not-for-return.md
