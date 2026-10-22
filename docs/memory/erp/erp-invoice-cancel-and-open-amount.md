---
name: erp-invoice-cancel-and-open-amount
description: When an ERP invoice can be cancelled, what cancel does to ledger and numbers, how open amount and paid status treat returns, which invoice a bank statement line suggests.
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
- Opening a return and cancelling a return both recompute the parent invoice status (`returns.php`, inside the transaction); on a foreign-currency parent the exchange difference is posted when it reaches `paid` and reversed when it leaves it (#1071, erp-money-and-currency).


## Can a receipt be allocated to a return?
- No. `erp_post_receipt()` reads the named document with its `doc_type` and refuses anything but an invoice ("That document is not an invoice; a receipt does not close it."); `POST /erp/receipts` answers 422 `invoice_id` with the same sentence. A return closes against the invoice it returns (`erp_invoice_returned_total()`), never with money.
- Before (#1063, PR #1132) the row was read without `doc_type`, so `erp_invoice_open_amount()`'s return gate never held: a return's whole total counted as open, the money settled the return (marked paid) and the invoice stayed open. Checked on the sandbox in a rolled-back transaction.

## Which invoice does a bank statement line suggest?
- `erp_bank_line_suggestion()`, when no tax number or name is in the line, offers the one open invoice the amount closes exactly; the query is `erp_bank_open_invoice_query()` (`includes/erp/bank_import.php`): `doc_type = 'invoice'`, money in a sale and money out a purchase, the line's currency (`erp_bank_line_currency()`, the store's own also taking documents with no currency), and open as `erp_invoice_open_amount()` counts it, total less paid less the returns that are not cancelled.
- Before (#1063, PR #1228) the query had no `doc_type`, no currency and no returns: it offered a return document (whose posting #1132 refuses) and matched a partly returned invoice by its old open amount, or a foreign invoice by a bare number.
- Returns are summed in one grouped join per query: `erp_invoices.parent_invoice_id` has no index, so a subquery per open invoice would scan the table for each.

Related: docs/memory/erp/erp-return-documents.md
