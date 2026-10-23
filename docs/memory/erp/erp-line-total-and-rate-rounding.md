---
name: erp-line-total-and-rate-rounding
description: Line totals and rates round half away from zero on the exact product of the written decimals (erp_multiply_units(), #1243): every PHP version and width, the editor, the checkout (#1329).
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

## Does the checkout take the same rule? (#1329)
- Yes: `pg_tax_apply_rate()` = `pg_multiply_units($amount, pg_decimal_units($rate, 3), 100000)` (`includes/fn/ecommerce.php`), copies of `erp_multiply_units()` / `erp_decimal_units()`. Copies, because a store without the ERP never loads money.php (behind `PG_ERP_ENTRY`); `pg_` names, because an `erp_` copy is declared twice once an ERP screen loads it. `tests/tax_apply_rate_test.php` holds the two sources equal (the 32-bit float steps CI never runs) and compares 20,000 random amounts and rates. The amount is not cut to an int: a 32-bit rate group past 21,474,836.47 stays exact.
- Used by `pg_tax_by_rate()`, `pg_tax_rate_amount()`, `get_tax_inclusive_price()` (marketplace prices) and the till (`local_sale_line_tax()`, `unit_gross`, `add_order.php`'s price text).
- Symptom on PHP 8.4+: 4.35% of 30.00 was 1.30 at the checkout and 1.31 on the invoice; 31.31 at 4.35% was taken as unreachable and 31.30 as reachable, the reverse of Paraşüt; 0.50 at 29% went to a marketplace as 0.64. The order's tax (amount x rate / 100) never moved at whole rates, nor at 7.7, 8.25, 0.5; the price form (rate / 100 x price) also moved at whole rates such as 29% and 70%. PHP 8.3's `round()` agreed.

## What still rounds a float product with round()?
- The withholding total in `erp_rate_vat()` / `rateTaxes()`, `erp_to_base()`, the late fee (`erp_late_fee_for_invoice()`), and the checkout's discount share for lines without a stored rate (`pg_order_charged_tax()`, orders before 11.0.59): a ratio, not a rate.

Related: docs/memory/erp/erp-money-and-currency.md, docs/memory/erp/erp-line-quantity-places.md, docs/memory/commerce/offer-percent-rounding.md
