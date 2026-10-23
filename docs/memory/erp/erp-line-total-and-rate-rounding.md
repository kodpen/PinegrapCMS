---
name: erp-line-total-and-rate-rounding
description: Line totals and rates round half away from zero on the exact product of the written decimals (erp_multiply_units(), #1243): every PHP version, 32-bit and 64-bit, the invoice editor.
---

## How do a line total and a rate round?
- On the exact product of the written decimals, half away from zero, never an `(int)` cut. `erp_line_total()` takes the quantity at four places and `erp_apply_rate()` the rate at three (the rate columns' scale), both through `erp_decimal_units()` (erp-line-quantity-places), and both call `erp_multiply_units($amount, $units, $scale)` (`includes/erp/money.php`, #1243).
- `erp_multiply_units()` splits both factors at the scale, so no partial product outgrows the integers while the result fits; the only division is of a remainder below the scale squared.
- The invoice editor does the same (`multiplyUnits()`, `decimalUnits()` in `assets/js/erp_invoice_editor.js`); `rateTaxes()` calls `applyRate()`, so the group VAT and the second tax in the preview follow it.

## What did it fix?
- 1310 * 1.15 = 1506.4999... in binary. PHP 7.1-8.3 pre-rounded it to 1507, PHP 8.4+ and JavaScript gave 1506. The preview could be a kurus off the saved invoice (18.07 shown, 18.08 saved), and the same document could be issued for different amounts depending on the server's PHP version. Now 13.10 x 1.15 = 15.07, 3.45 x 2.3 = 7.94, 4.35% of 30.00 = 1.31, 2.3% of 15.00 = 0.35 everywhere.
- Issued documents are never recomputed; a draft saved again can move by a kurus where its old figure came from a PHP 8.4+ server or the preview.

## Does it work on 32-bit PHP?
- Yes. There the remainder product reaches 10^10 for a rate, and the units of a quantity past 214,748.3647 are a float (`erp_decimal_units()`), so the same steps run on floats, exact below 2^53. On 64-bit the integer steps run; both give the same results.
- Symptom before the float path: on 32-bit `erp_apply_rate()` threw a `TypeError` from `intdiv()` at 25% VAT, a 50% discount or 9/10 withholding (999.99 at 25%), and 1.00 x 300,000 came out as 214,748.36.
- The result is an int; only on a 32-bit build, past `PHP_INT_MAX` (21,474,836.47), it is a float holding the exact whole number.
- `tests/erp_money_test.php` runs those cases on every width, and its random check uses an exact float reference on 32-bit.

## What still rounds a float product with round()?
- The withholding total in `erp_rate_vat()` / `rateTaxes()`, `erp_to_base()`, the late fee (`erp_late_fee_for_invoice()`), and the checkout's `pg_tax_by_rate()` / `pg_tax_rate_amount()` (`includes/fn/ecommerce.php`; the next step of plans/erp-vat-per-rate). At decimal rates on PHP 8.4+ the checkout can still differ from the ERP by a kurus.

Related: docs/memory/erp/erp-money-and-currency.md, docs/memory/erp/erp-line-quantity-places.md
