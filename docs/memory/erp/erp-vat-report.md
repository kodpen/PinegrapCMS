---
name: erp-vat-report
description: ERP VAT report: the one place tax totals are added up (vat_report.php), calculated, deductible and difference, what never counts, and the report's name in the menu.
---

## Where are tax totals added up?
- Only in `includes/erp/vat_report.php`: `erp_vat_add_document()` and `erp_vat_add_expense()` fill an `erp_vat_bucket()`, `erp_vat_sorted()` orders it (sales, sales returns, purchases, returns to suppliers, expenses; lower rate first). The screen (`erp_vat_report.php`, `erp_vat_report()`), its workbook (`erp_vat_workbook()`) and the accountant pack's VAT sheet all use them, so the three agree.
- Do not add VAT up again in another loop; a new tax source goes in here (and into the pack: erp-accountant-pack.md).

## What are the figures?
- Calculated = sales − sales returns. Deductible = purchases − returns to suppliers + the VAT of expenses whose VAT is taken back (`tax_deductible = 1`); the VAT of the rest is a cost and stays out.
- Difference: `erp_vat_difference($calculated, $deductible, $withheld_sales)` (`includes/erp/rules.php`); the VAT withheld on sales comes off only while `erp_vat_net_withholding()` is on (erp-accounting-rules.md). Caption: `erp_vat_difference_label()`. Never subtract it by hand elsewhere.
- The second tax is reported apart and never enters the difference: a line's VAT is `tax_total − tax2_amount`.
- Base currency throughout. Drafts count for nothing; a cancelled document or expense is listed and marked, never in a total.
- The report also shows the period's completed orders that have no invoice and their tax (`erp_vat_uninvoiced_orders()`): a sale the report cannot see.

## What is the report called?
- In the menu `pg_erp_tax_report_label()` (`includes/fn/ecommerce.php`): the menu is built before the ERP loads. Inside the ERP `erp_tax_label('report')`. "VAT report", or "<name> report" when `config.erp_tax_name` is set.
- Screen gate: `validate_erp_access($user)` (any ERP user).
