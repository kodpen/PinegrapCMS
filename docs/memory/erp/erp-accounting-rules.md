---
name: erp-accounting-rules
description: ERP accounting rules (4.103): withholding in the VAT difference, the exemption code of zero-rated lines, the expense category VAT default, and the accountant questions file.
---

## Where are the accounting rules kept? (`includes/erp/rules.php`, 2026.4.4 step 4.103)
- Choices the store's accountant makes, not the software: `config.erp_vat_net_withholding` and `config.erp_vat_exemption_code` (TEXT), read once per request by `erp_rules()`.
- Edited on the "Accounting rules" card of ERP Settings (`erp_settings.php`, its own form posting `rules=1`, saved by `erp_rules_save()`, logged when changed). The two Turkish rules show only while `erp_turkish_features()` is true; the card always links to the expense categories.
- Open questions for the accountant: `docs/erp_malimusavir_sorulacaklar.md`. A new question a setting can answer goes into that file and onto the card together.

## How does a zero-rated line get its exemption code?
- Only through `erp_vat_line_exemption_code($line)`: a line that carries VAT gets none; a 0% line keeps the code it has (a return keeps its invoice's), else its product's (`products.vat_exemption_code`), else the store default. An e-document with a 0% line and no reason is refused by the tax authority's schema.
- Every path that writes `erp_invoice_items` writes `vat_exemption_code` with it: today the order bridge (`order_bridge.php`), the manual invoice draft and its issue (`invoice_manual.php`) and returns (`returns.php`). A new line writer does the same.
- The store default is three digits or empty (`erp_vat_exemption_code_valid()`). The product column has no field on the product screen; the Workspace record changes can set it.

## Does an expense take its VAT back?
- The category carries the default (`erp_expense_categories.tax_deductible`, the tax column of `erp_expense_categories.php`); `erp_expense_category_deductible($category_id)` reads it (on for a category without one, and before 4.103). The new-expense form and the API (when `tax_deductible` is not sent) start from it; a saved expense keeps its own value.
- `tax_deductible = 0`: the VAT is a cost (profit report) and stays out of the deductible VAT (VAT report, accountant pack).
