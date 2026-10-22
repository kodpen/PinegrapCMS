---
name: product-import-prices
description: Product import price cells (price, extra shipping, compare-at): one reader for spreadsheet numbers and typed figures; a row that is not an amount is named (#749)
---

## Which reader takes a price cell?
- `pg_products_import_price_cents()` (`import_products_f.php`) for `price`, `extra_shipping_cost` and `compare_at_price`, in minor units, or `null` when the cell is not an amount.
- A number as a spreadsheet stores it (`^\d+(\.\d+)?` with an optional exponent: `1499.99`, `1799.988` from `=B2*1,2`, Excel's 17-digit `1799.9879999999998`, `1E+3`) is read as that number and rounded: `round((float) $v * 100)`. A .xlsx `<v>` or an .ods value reaches the importer in this form (the converter writes the CSV).
- Anything else is typed text: a currency symbol or code before or after (`₺15,00`, `15,00 TL`), spaces or apostrophes grouping digits, a decimal comma. The figure goes to the product screen's reader `pg_pb_price_to_cents()` (last separator with 1-2 digits after it is the decimal point; `12,50` 1250, `1.234,56` 123456, `1,234` 123400).
- A figure with only a dot is a decimal point even with three digits (`1.234` is 1.23): that is how a stored number looks, and it was the old price rule. Thousands with a dot need a decimal comma (`1.234,00`) or no grouping.
- `null`: words or other characters (`12/50`), a minus sign, no digit, more than 15 digits, more than the INT top (2147483647).

## What happens to a row whose cell is not an amount?
- The whole row is left out (not created, not updated) and its row number in the file (header is row 1) is listed in an error on the product list after the import ("These rows were not imported because a price, compare-at price or extra shipping cost in them is not an amount: …", up to 50 numbers). The rest of the file is imported.
- An empty cell (or spaces) leaves the column as it is, as for every column. A compare-at price starting with `-` still leaves the stored one alone (docs/memory/commerce/product-compare-at-price.md); `0` takes it off; the read amount is judged by `pg_compare_at_price_value()`.

## What was wrong before?
- `price` and `extra_shipping_cost` were `str_replace(',', '', $v) * 100`: `12,50` became 125000, and `₺15,00` stopped the import with a TypeError (PHP 8) after earlier rows were written.
- `compare_at_price` went through `pg_pb_compare_at_price_from_input()`, so a stored `1799.988` read as thousands (179998800) and the 17-digit form was skipped.
- New products warned `Undefined variable $address_name_key` / `$short_description_key` / `$submit_form_key` for every row when those columns were missing; those cells are now read with `pg_products_import_row_value()` (`''` for a missing column or a short row).

## How is it checked?
- `tests/import_products_test.php` (`pg_products_import_price_cents()`, `pg_products_import_row_value()`). Not run on a live instance: the import loop needs a database.
