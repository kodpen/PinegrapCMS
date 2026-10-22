---
name: erp-invoice-cancel-and-open-amount
description: When an ERP invoice can be cancelled, what cancel does to ledger and numbers, and how open amount and paid status treat returns.
---

## When can an invoice be cancelled and what happens?
- Only when nothing hangs on it: no settlements, no returns.
- Cancel does not delete the ledger row; it reverses it with a counter entry, and it does not release the number.
- An order that produced the invoice becomes re-invoiceable again with `erp_invoice_id = 0`.
- Drafts are deleted, not cancelled (see erp-manual-invoice-draft-issue).

## What does the period lock do to a cancel?
- An invoice dated on or before the lock date cannot be cancelled; it is taken back with a return dated in the open period.
- The lock is asked before any side effect (PR #1070): `erp_edoc_invoice_provider_cancel()` refuses first, so the provider's draft is not deleted and an e-Archive document is not cancelled there; the order card's step offers the return instead of "Cancel the invoice" and its run never removes the allocations; `edit_erp_invoice.php` shows the cancel button disabled with the reason.
- Before, the provider was taken back and the order card's allocations removed, and only `erp_invoice_cancel()` refused: the invoice stayed issued, unpaid, with nothing at the provider.

## How do open amount and paid status treat returns?
- `erp_invoice_open_amount()` deducts both settlements and returns.
- `erp_invoice_refresh_paid()` measures settlements against the same figure (total − returns), so the two cannot diverge.
- Still only money makes an invoice "paid": a fully returned invoice that never saw a collection stays `issued`.
- Opening a return and cancelling a return both recompute the parent invoice status (`returns.php`, inside the transaction); on a foreign-currency parent the exchange difference is posted when it reaches `paid` and reversed when it leaves it (#1071, erp-money-and-currency).


## Can a receipt be allocated to a return?
- No. `erp_post_receipt()` reads the named document with its `doc_type` and refuses anything but an invoice ("That document is not an invoice; a receipt does not close it."); `POST /erp/receipts` answers 422 `invoice_id` with the same sentence. A return closes against the invoice it returns (`erp_invoice_returned_total()`), never with money.
- Before (#1063, PR #1132) the row was read without `doc_type`, so `erp_invoice_open_amount()`'s return gate never held: a return's whole total counted as open, the money settled the return (marked paid) and the invoice stayed open. Checked on the sandbox in a rolled-back transaction.
- Still open: the bank statement's "one open invoice" suggestion (`erp_bank_line_suggestion()`) can still offer a return; posting it is now refused.

Related: docs/memory/erp/erp-return-documents.md
