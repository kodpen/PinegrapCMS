---
name: erp-line-quantity-places
description: Line quantities are priced at the four places they are stored with: erp_quantity_round(), erp_decimal_units() (float units, 32-bit safe), the editor's quantityRound(), the e-invoice inbox fold.
---

## How many places does a line quantity keep?
- Four, as stored (`erp_invoice_items.quantity` is `DECIMAL(15,4)`). `erp_manual_lines_build()` takes each quantity through `erp_quantity_round()` (`includes/erp/money.php`) before pricing it (#1230). Every typed path builds its lines there: drafts and issue, quotes, purchase orders, the external API, recurring invoices and incoming e-invoices.
- Old symptom: 1.3333333333 x 1,500.00 at 20% showed 2,400.00 on the draft and was issued at 2,399.94, because issue rebuilds the lines from the stored 1.3333. Now both are 1.3333 x 1,500.00 = 1,999.95.
- A quantity that is nothing at four places (0.00004) is refused like a zero quantity.
- The invoice editor (`assets/js/erp_invoice_editor.js`, `lineFigures()`) rounds with the same rule (`decimalUnits()` / `quantityRound()`), so the preview shows what is saved.

## How is a decimal turned into units?
- `erp_decimal_units($value, $places)` rounds half away from zero on the digits `sprintf('%.10F')` writes (JS `toFixed(10)` writes the same ones), never on a binary product such as `1.15 * 10000`, whose `round()` differs between PHP 8.3 and 8.4+.
- The units are a whole number held in a float (exact up to 2^53). An int clamped every quantity above 214,748.3647 at `PHP_INT_MAX` on 32-bit PHP: a 250,000 x 1.00 line was saved and issued at 214,748.36, and a 250,000 kWh e-invoice line folded. CI is 64-bit; `tests/erp_money_test.php` keeps 250,000 cases for 32-bit runs.
- A negative value that rounds to nothing gives 0.0, not -0.0.

## What happens to an incoming e-invoice line with a longer quantity?
- `erp_edoc_inbox_lines()` checks the price against the stored quantity. A price worked out from the amount stands only beside the supplier's own quantity.
- A line that does not add up folds into one unit of its amount, with the supplier's quantity and price in its text, the same fold as for sub-kurus prices: 0.12345 t x 400,000.00 keeps 49,380.00 (stored at 0.1235 it would have been 49,400.00).

## Related
- erp-money-and-currency, erp-number-input-and-display.