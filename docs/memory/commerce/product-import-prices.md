---
name: product-import-prices
description: Product import price cells (price, extra shipping, compare-at): one reader for spreadsheet numbers and typed figures; a row that is not an amount is named (#749, PR #1262)
---

## Which reader takes a price cell?
- `pg_products_import_price_cents()` (`import_products_f.php`) for `price`, `extra_shipping_cost` and `compare_at_price`, in minor units, or `null` when the cell is not an amount.
- A bare number as a spreadsheet stores it (`^\d+(\.\d+)?` with an optional exponent: `1499.99`, `1799.988` from `=B2*1,2`, Excel's 17-digit `1799.9879999999998`, `1E+3`) is read as that number and rounded: `round((float) $v * 100)`. A .xlsx `<v>` or an .ods value reaches the importer in this form.
- Anything else is typed text: a currency symbol or code before or after (`₺15,00`, `15,00 TL`), spaces or apostrophes grouping digits, a decimal comma. The figure follows the product screen's rule, `pg_pb_price_figure()` (the figure behind `pg_pb_price_to_cents()`): the last separator with 1-2 digits after it is the decimal point (`12,50` 1250, `1.234,56` 123456, `1,234` 123400, `1.234 TL` 123400).
- A bare figure with only a dot is a decimal point even with three digits (`1.234` is 1.23): that is how a stored number looks, and it was the old price rule. Dot-grouped thousands need a decimal comma (`1.234,00`) or a symbol or code.
- `null`: words or other characters (`12/50`), a minus sign, no digit, more than 15 digits, more than the INT top (2147483647). The top is checked on the float before the int cast: on 32-bit PHP the cast wraps `25.000.000,00` to a negative figure.

## How does a row get its amounts?
- `pg_products_import_row_prices($row, $positions)` reads the row's money cells before the database is touched: `array('prices' => column => cents, 'refused' => bool)`. An empty cell (or spaces) is left out, as is a compare-at cell starting with `-`.
- It is a function because `import_products.php` keeps each header column's position in a script variable `${column}_key`. A loop there with `$price_key` overwrote the price column's position, so the price was not written (update or create) whenever the last money cell of the row was empty: every exported product without a compare-at price. No script variable there may end in `_key`.

## What happens to a row whose cell is not an amount?
- The whole row is left out (not created, not updated) and named in an error on the product list after the import ("These rows were not imported because a price, compare-at price or extra shipping cost in them is not an amount: …", up to 50), as `number (name)`, e.g. `4 (KALEM-01)`. The number counts the rows the importer reads (header 1); a spreadsheet's empty rows are not written to the CSV, so they are not counted, and the name finds the row. The name is HTML-escaped (a liveform message is HTML). The rest of the file is imported.
- An empty cell leaves the column as it is, as for every column. A compare-at price starting with `-` leaves the stored one alone; `0` takes it off; the amount is judged by `pg_compare_at_price_value()` (product-compare-at-price.md).

## What was wrong before? (#749, PR #1262)
- `price` and `extra_shipping_cost` were `str_replace(',', '', $v) * 100`: `12,50` became 125000, and `₺15,00` stopped the import with a TypeError (PHP 8) after earlier rows were written.
- `compare_at_price` went through `pg_pb_compare_at_price_from_input()`, so a stored `1799.988` read as thousands (179998800) and the 17-digit form was skipped.
- New products warned `Undefined variable $address_name_key` / `$short_description_key` / `$submit_form_key` when those columns were missing; those cells are now read with `pg_products_import_row_value()` (`''` for a missing column or a short row).

## How is it checked?
- `tests/import_products_test.php` (price cents incl. the 32-bit top, row prices with the export header, row value). Not run on a live instance: the import loop needs a database.
