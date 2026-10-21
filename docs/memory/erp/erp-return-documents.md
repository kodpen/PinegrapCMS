---
name: erp-return-documents
description: ERP return (credit) documents: series naming, full vs partial return arithmetic, currency and date of a return, line VAT rate, returned_qty and parent_line_id.
---

## How do issued invoices go back?
An issued invoice is never edited; it goes back by return or by cancel (`includes/erp/returns.php`; cancel: erp-invoice-cancel-and-open-amount).

## Which series do purchase invoices and returns use?
- Purchase invoice has its own series: `erp_invoice_series('purchase')`, `ERP_PURCHASE_INVOICE_SERIES`, or invoice series + `A` (PGF → PGFA; those issued before 2026.4.10 stayed in PGF). Its return is PGFAI.
- A return is its own document in its own series: invoice series + `I` (PGF → PGFI) or `ERP_RETURN_SERIES`. It cannot share the invoice series: `erp_invoices.uniq_number` (direction, series, number, issue_year) does not include `doc_type`, and one document number must not sit on two documents anyway.

## How are return amounts computed?
- `erp_return_build()` works every return out, the card refund by line included (erp-order-cancel-and-refund). A return is full when nothing went back before and everything goes back now.
- Full return copies the parent's figures, no recalculation: the parent's VAT was tied to the order header VAT and can be 1 kuruş off the line-derived value.
- Partial return splits the line proportionally (`round(discount × qty / sold)`); the taxes of the lines given back in part come per group of one VAT rate and one withholding rate of the return (`erp_rate_vat()`, #1020): one of each of two 2 x 1.02 lines at 20% gives back 0.41, not 0.40. A single line comes out as before.
- An order's lines (`erp_order_refund_lines()` without an invoice) carry no earlier returns: the group taxes alone, uncapped.
- A return line's taxes cannot exceed what is still on the parent line, and the part that empties the line takes the remainder exactly (`erp_returnable_lines()` → `returned_tax`, `returned_withholding`, `returned_tax2`, matched by `parent_line_id`): the two halves of a 361-kuruş line are 181 + 180, not 181 + 181.
- The card refund dialog (includes/erp/order_refund.php) prices each line alone at once, then takes the whole choice from api.php `erp_refund_preview` (`erp_order_refund_amount()`, read only): with group taxes the whole is not the sum of the lines.

## How is the line VAT rate carried?
- From the order as the legal rate, not as the quotient of two rounded amounts (`erp_line_tax_rate()`): first try `products.tax_rate`; if `erp_apply_rate()` reproduces the stored tax exactly, use it; otherwise fit the simplest rate at 0–3 decimals (361/2008 → 18, not 17.978). Amounts stay unchanged, only the rate.

## In which currency and on which date is a return written?
- In the parent's currency at the parent's rate; a full return also copies `grand_total_base`, so it cancels the invoice's base value to the kuruş.
- Dated the day it is written (or the date given); the period lock judges that date (erp-period-lock).

## How are returned quantities tracked?
- `erp_invoice_items.returned_qty` accumulates on the parent invoice and is decremented when a return is cancelled; this stops the same goods being returned twice by two documents.
- A return line carries its parent line as `parent_line_id` (4.50); cancel and the `returned_tax` mapping go through this id, never by product, because two lines of the same product (different price/discount) cannot be told apart by product.
- Lines written before 4.50 (`parent_line_id = 0`) keep a product-match fallback; the migration backfills unique matches.
- The parent line's withholding and second tax are capped like its VAT: `returned_withholding` and `returned_tax2` beside `returned_tax` (erp-vat-withholding).
