---
name: erp-number-input-and-display
description: How the ERP reads typed quantities, amounts and rates and writes figures back: decimal comma by store country, separators by panel language, percent text, CSV delimiter, paper size.
---

## How is a typed quantity read?
- `erp_quantity_in()` (`includes/erp/money.php`): both separators are accepted; with both, the last one is the decimal one; a separator that appears twice groups; a single point is a decimal point (what the ERP writes back into its own fields).
- The one ambiguous case, a single comma with three digits behind it (`1,500`: one and a half in Istanbul, fifteen hundred in Chicago), is settled by the store's country: `erp_decimal_comma()` (a list of decimal-point countries; every other country writes a decimal comma).
- Never `str_replace(',', '.')` and never `erp_fx_rate_in()` for a quantity: the rate reader always takes the last separator as the decimal one.
- The scripts follow the same rule through `data-decimal-comma` on the form: `erp_invoice_editor.js` for the invoice and waybill editors (`invoice_form.php`, `waybill_form.php`), the expense form's own script (`add_erp_expense.php`).
- Amounts are read by `erp_kurus()` (the last separator with at most two digits behind it is the decimal one), rates by `erp_fx_rate_in()`.

## How are figures written on screens and documents?
- Separators follow the panel language, not the store's country: `erp_number_separators()` → `pg_number_separators()`, read from the language file (tr 1.234,56 · en 1,234.56 · fr 1 234,56 with a no-break space).
- Amount `erp_money_out_currency($kurus, $code)` (`erp_money_out()` for the home currency), rate `erp_fx_rate_text()`, quantity `erp_quantity_text()`, percentage `erp_percent_text()` (Turkish `%20`, other languages `20%`, up to three decimals).
- Never `number_format(..., '.', ',')` for an amount and never `'%' . $rate`: both print one language's shape in every language.

## What stays in machine format?
- A value put into a form field: an amount as two decimals with a point (`number_format($kurus / 100, 2, '.', '')`), a rate as `erp_fx_rate_out()` (point, at least four decimals). The readers above take both back.
- Files and the API keep their own fixed formats.

## What else follows the store's country?
- CSV delimiter `erp_csv_delimiter()`: `;` where the decimal comma is used, `,` elsewhere; the importers detect either.
- PDF paper `erp_paper_size()` (`includes/erp/document.php`): `letter` for the US, Canada, Mexico and a few other American countries, `A4` elsewhere. The built-in templates write `@page { size: {{paper}} portrait; … }`.

## How does the counter sale screen write money?
- With the ERP on, `local_sale_money()` (`includes/local_sale.php`) writes through `erp_money_out()`, so the till and the invoice it produces read alike; with the ERP off, `prepare_amount()`.
- The screen's script formats the change and the quick amounts itself, with the separators `local_sale_number_separators()` puts into the `pg_sale_config` JSON (`decimal`, `thousands`) of `add_order.php`.

Related: docs/memory/erp/erp-money-and-currency.md, docs/memory/ui/money-field-input-mask.md
