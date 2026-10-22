---
name: erp-receipt-not-for-return
description: A receipt is never allocated to a return document: erp_post_receipt() and POST /erp/receipts refuse it (#1063, PR #1132); the bank statement suggestion still open.
---

## Can a receipt be allocated to a return?
- No. `erp_post_receipt()` reads the named document with its `doc_type` and refuses anything but an invoice ("That document is not an invoice; a receipt does not close it."); `POST /erp/receipts` answers 422 `invoice_id` with the same sentence. A return closes against the invoice it returns (`erp_invoice_returned_total()`), never with money.

## What went wrong before?
- Before (#1063, PR #1132) the row was read without `doc_type`, so `erp_invoice_open_amount()`'s return gate never held: a return's whole total counted as open, the money settled the return (marked paid) and the invoice stayed open. Checked on the sandbox in a rolled-back transaction.

## What is still open?
- The bank statement's "one open invoice" suggestion (`erp_bank_line_suggestion()`) can still offer a return; posting it is now refused.

Related: docs/memory/erp/erp-invoice-cancel-and-open-amount.md
