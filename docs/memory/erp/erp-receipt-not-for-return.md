---
name: erp-receipt-not-for-return
description: A receipt is never allocated to a return document: erp_post_receipt() and POST /erp/receipts refuse it (#1063, PR #1132), and the bank statement suggestion offers only invoices (PR #1228).
---

## Can a receipt be allocated to a return?
- No. `erp_post_receipt()` reads the named document with its `doc_type` and refuses anything but an invoice ("That document is not an invoice; a receipt does not close it."); `POST /erp/receipts` answers 422 `invoice_id` with the same sentence. A return closes against the invoice it returns (`erp_invoice_returned_total()`), never with money.

## What went wrong before?
- Before (#1063, PR #1132) the row was read without `doc_type`, so `erp_invoice_open_amount()`'s return gate never held: a return's whole total counted as open, the money settled the return (marked paid) and the invoice stayed open. Checked on the sandbox in a rolled-back transaction.

## Which invoice does a bank statement line suggest?
- With no tax number or name in the line, `erp_bank_line_suggestion()` offers the one open invoice the amount closes exactly, through `erp_bank_open_invoice_query()` (`includes/erp/bank_import.php`).
- Conditions: `doc_type = 'invoice'`; money in a sale, money out a purchase; with FX on, the line's currency exactly (`erp_bank_line_currency()`, the same gate `erp_post_receipt()` applies, so a blank-currency invoice is not offered), with FX off no currency condition (posting checks none); open as `erp_invoice_open_amount()` counts it: total less paid less the returns that are not cancelled.
- Before (#1063, PR #1228) the query had no `doc_type`, currency or returns. It offered a return, which #1132's guard then refused; matched a partly returned invoice by its old open amount; and with FX on matched a foreign invoice on a bare number.
- Returns are summed in one grouped join per query because `erp_invoices.parent_invoice_id` has no index.

Related: docs/memory/erp/erp-invoice-cancel-and-open-amount.md
