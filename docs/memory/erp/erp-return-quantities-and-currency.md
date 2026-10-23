---
name: erp-return-quantities-and-currency
description: An ERP return's currency, rate and date, and how returned quantities are tracked on the parent (returned_qty, parent_line_id 4.50, the product-match fallback, withholding and second tax caps).
---

## In which currency and on which date is a return written?
- In the parent's currency at the parent's rate; a full return also copies `grand_total_base`, so it cancels the invoice's base value to the kuruş.
- Dated the day it is written (or the date given); the period lock judges that date (erp-period-lock).

## How are returned quantities tracked?
- `erp_invoice_items.returned_qty` accumulates on the parent invoice and is decremented when a return is cancelled; this stops the same goods being returned twice by two documents.
- A return line carries its parent line as `parent_line_id` (4.50); cancel and the `returned_tax` mapping go through this id, never by product, because two lines of the same product (different price/discount) cannot be told apart by product.
- Lines written before 4.50 (`parent_line_id = 0`) keep a product-match fallback; the migration backfills unique matches.
- The parent line's withholding and second tax are capped like its VAT: `returned_withholding` and `returned_tax2` beside `returned_tax` (erp-vat-withholding).

Related: docs/memory/erp/erp-return-documents.md
