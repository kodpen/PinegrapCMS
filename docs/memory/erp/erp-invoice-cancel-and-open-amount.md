---
name: erp-invoice-cancel-and-open-amount
description: When an ERP invoice can be cancelled, what cancel does to ledger and numbers, and how open amount and paid status treat returns.
---

## When can an invoice be cancelled and what happens?
- Only when nothing hangs on it: no settlements, no returns.
- Cancel does not delete the ledger row; it reverses it with a counter entry, and it does not release the number.
- An order that produced the invoice becomes re-invoiceable again with `erp_invoice_id = 0`.
- Drafts are deleted, not cancelled (see erp-manual-invoice-draft-issue).

## How do open amount and paid status treat returns?
- `erp_invoice_open_amount()` deducts both settlements and returns.
- `erp_invoice_refresh_paid()` measures settlements against the same figure (total − returns), so the two cannot diverge.
- Still only money makes an invoice "paid": a fully returned invoice that never saw a collection stays `issued`.
- Opening a return and cancelling a return both recompute the parent invoice status (`returns.php`, inside the transaction).

Related: docs/memory/erp/erp-return-documents.md
