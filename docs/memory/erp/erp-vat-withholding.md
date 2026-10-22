---
name: erp-vat-withholding
description: VAT withholding (tevkifat, migration 4.68) on ERP documents: what tax_total, withholding_total and grand_total hold, the line columns, the code list rule, returns, document type.
---

## What do the totals hold when a line has withholding?
- `includes/erp/withholding.php` (2026.4.4, step 4.68). On some Turkish sales the buyer pays part of the VAT to the tax office; the invoice still shows the whole VAT and names the code and its share.
- `tax_total`: the whole VAT. `withholding_total`: the part the buyer pays to the tax office. `grand_total` = subtotal − discount_total + tax_total − withholding_total: what the account owes, so the ledger, settlements, aging and due dates read the document unchanged.
- A new totals calculation must subtract the withholding; one that forgets it puts the withheld VAT on the account as debt.
- The document prints the VAT-inclusive total as `grand_total + withholding_total` (`tax_inclusive` in `includes/erp/document.php`).

## What does a line carry?
- `withholding_code`, `withholding_rate` (percentage of the line's VAT: 4/10 = 40; `erp_withholding_rate_text()` prints it back as "4/10"), `withholding_amount` = `erp_apply_rate(line VAT, rate)`; the amount column is written only when it exists (`erp_invoice_lines_have_withholding()`).
- A code in the list (`erp_withholding_codes()`: GİB 601–627 partial, 801–825 full) always carries the list's rate, whatever was sent; only a code the list does not know keeps the rate it came with, and such a code without a rate is refused. A line with withholding must carry VAT (`erp_manual_lines_build()`).
- Withholding is a share of the first tax (VAT); a second tax is never withheld.

## What else follows from withholding?
- `invoice_type` becomes `TEVKIFAT` when `withholding_total > 0` (draft save and issue) and goes back to `SATIS` when the last withholding is taken off.
- A partial return limits the withholding like the VAT: never more than is left on the parent line (`returned_withholding` in `includes/erp/returns.php`), and the part that empties the line takes the remainder exactly.
- The VAT report takes the VAT withheld on sales off the calculated VAT only while the accounting rule says so (`erp_vat_net_withholding()`, in `erp_vat_difference()`): docs/memory/erp/erp-vat-report.md.
- Withholding is Turkish: its caption is `lang('VAT withholding')` and does not follow the store's own tax name (`erp_tax_label()`); the editor's switch shows with `erp_turkish_features()` (store in TR or an e-document provider in use) or when a line already carries it.

Related: docs/memory/erp/erp-tax-names-zones-and-second-tax.md, docs/memory/erp/erp-return-documents.md
