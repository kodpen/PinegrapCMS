---
name: erp-quantity-and-rounding
description: ERP quantities priced at their four stored places, and line totals and rates rounded on the exact product of the written decimals, the same in PHP (any version) and the invoice editor.
---

## How many places does a quantity keep?
- Four, as stored. `erp_manual_lines_build()` takes it through `erp_quantity_round()` before pricing (#1230). Before, 1.3333333333 x 1,500.00 showed 2,400.00 on the draft, but the issued invoice was 2,399.94 because issue rebuilds the lines from the stored 1.3333. The editor's `quantityRound()` does the same.
- `erp_decimal_units($value, $places)` rounds on the digits `sprintf('%.10F')` writes (JS `toFixed(10)` writes the same ones), half away from zero. It never rounds a binary product such as `1.15 * 10000`.
- Incoming e-invoice line: the price is checked against the stored quantity. A longer quantity whose price does not add up folds into one unit, with the supplier's figures in the text.

## How do a line total and a rate round?
- On the exact product of the written decimals, half away from zero. `erp_line_total()` (quantity at 4 places) and `erp_apply_rate()` (rate at 3) call `erp_multiply_units()`, which uses whole numbers only; the editor has the same `multiplyUnits()` (#1243). Before, 1310 * 1.15 = 1506.4999... gave 1507 on PHP <= 8.3 but 1506 on 8.4+ and in JS, so the preview and the server's PHP version moved a kurus.
- Still float `round()`: the withholding total in `erp_rate_vat()` / `rateTaxes()` and the checkout's `pg_tax_by_rate()`.

Related: docs/memory/erp/erp-money-and-currency.md
