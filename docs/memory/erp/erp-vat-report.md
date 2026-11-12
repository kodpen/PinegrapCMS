---
name: erp-vat-report
description: ERP VAT report: the one place tax totals are added up (vat_report.php), calculated, deductible and difference, what never counts, the figures and chart on top, the menu name.
---

## Where are tax totals added up?
- Only in `includes/erp/vat_report.php`: `erp_vat_add_document()` and `erp_vat_add_expense()` fill an `erp_vat_bucket()`, `erp_vat_sorted()` orders it (sales, sales returns, purchases, returns to suppliers, expenses; lower rate first). The screen (`erp_vat_report.php`, `erp_vat_report()`), its workbook (`erp_vat_workbook()`) and the accountant pack's VAT sheet all use them, so the three agree.
- Do not add VAT up again in another loop; a new tax source goes in here (and into the pack: erp-accountant-pack.md).

## What are the figures?
- Calculated = sales − sales returns. Deductible = purchases − returns to suppliers + the VAT of expenses whose VAT is taken back (`tax_deductible = 1`); the VAT of the rest is a cost and stays out.
- Difference: `erp_vat_difference($calculated, $deductible, $withheld_sales)` (`includes/erp/rules.php`); the VAT withheld on sales comes off only while `erp_vat_net_withholding()` is on (erp-accounting-rules.md). Caption: `erp_vat_difference_label()`. Never subtract it by hand elsewhere.
- The second tax is reported apart and never enters the difference: a line's VAT is `tax_total − tax2_amount`.
- Base currency throughout. Drafts count for nothing; a cancelled document or expense is listed and marked, never in a total.
- Expenses come from `erp_expenses_period($from, $to)` (`includes/erp/expenses.php`): every expense of the period, oldest first, no cap; the accountant pack reads the same. Never `erp_expenses_list()`: it serves the list screen, stops at 5000 rows newest first, and a period with more lost its oldest expenses and their deductible tax. Test: `tests/erp_vat_report_expense_cap_test.php`.
- The report also shows the period's completed orders that have no invoice and their tax (`erp_vat_uninvoiced_orders()`): a sale the report cannot see.

## What does the screen draw over its tables?
- The report screens' shared view (`ui/report-view.md`, #8827) where the four figure cards stood: `pg_report_view_vat_figures()` gives Calculated, Deductible, Difference and, with withholding, Withholding, each with the card's note; a negative difference's note is marked `bad`. Money is `erp_money_out()` text, from the same `erp_vat_report()` totals.
- The chart, `pg_report_view_vat_chart()`: over two months or more a line of the calculated tax by month with the deductible tax (purchases + expenses) dashed beside it, read from the By month figures; a month below zero is drawn at zero (the line starts at zero), its tip says the real figures. Over one month "Tax by document type": the tax of sales, sales returns, purchases, returns to suppliers and the expenses' deductible tax, those above zero.
- The period form, the statement, By rate, withholding, By month, the documents and the Excel export are unchanged; the report has no saved settings, so no settings drawer and no ready report.

## What is the report called?
- In the menu `pg_erp_tax_report_label()` (`includes/fn/ecommerce.php`): the menu is built before the ERP loads. Inside the ERP `erp_tax_label('report')`. "VAT report", or "<name> report" when `config.erp_tax_name` is set.
- Screen gate: `validate_erp_access($user)` (any ERP user).
