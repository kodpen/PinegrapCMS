---
name: erp-return-screen
description: Which invoice the return screen opens for (erp_return_refusal(): issued invoices only) and how it writes quantities and fits a phone (PR #5708).
---

## Which invoices take a return?
- Only an issued invoice: `erp_return_refusal($invoice)` (`includes/erp/returns.php`) refuses a return document ("Only an invoice can be returned."), a draft ("A draft is not a document yet; issue it first.", the API's `invoice_is_draft` text) and a cancelled invoice. `add_erp_return.php` asks it before drawing or reading the form; `erp_invoice_return()` asks it too.
- Before PR #5708 (#5686) a draft took a return: numbered in `_DI`, the account credited for a debt it never had.

## How are quantities written?
- Shown with `erp_quantity_text()` (panel language separators). The preset in the box uses the store's decimal mark (`erp_decimal_comma()`) and no grouping, so `erp_quantity_in()` reads it back the same (a grouped `1.250` would read as 1.25).

## How does it fit a phone?
- Below `sm` the #, Already Returned and Unit price columns are hidden (`d-none sm:d-table-cell`); an earlier return shows under the description (`sm:d-none`). The Returning box stays in view at 375 px (#5693).

Related: docs/memory/erp/erp-return-documents.md, docs/memory/erp/erp-number-input-and-display.md
