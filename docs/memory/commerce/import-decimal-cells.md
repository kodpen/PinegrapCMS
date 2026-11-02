---
name: import-decimal-cells
description: Product, contact and user imports read weight, dimensions, commission rate limit and affiliate commission rate with either decimal separator, as the screens do
module: ecommerce
---

## Which import cells go through a decimal reader?
- Import Products: `weight`, `length`, `width`, `height` (`decimal(12,4) unsigned`) and `commission_rate_limit` (`decimal(5,2) unsigned`) through `pg_products_import_decimal_cell($column, $value)` (`includes/fn/ecommerce.php`, needs `product_builder.php`), on the update and the insert path. Weight and dimensions: `pg_pb_decimal_from_input()` rounded to four places; the rate: `pg_pb_commission_rate_from_input()` (two places, held to 999.99). Other columns get `null` and keep their own path. An empty cell is still skipped (the column is left as it is). A spreadsheet exponent (`1E-3`) is read as that number.
- A cell that is not a number (words, a unit as in `1,5 kg`, a minus sign; weight or a dimension above 99999999.9999) leaves the whole row out before anything is written, checked by `pg_products_import_row_decimals_refused($row, $decimal_positions)`; the rows are named after the import as `number (name)`, up to 50, like price rows (`product-import-prices.md`). Before, `pg_pb_decimal_from_input()` dropped the letters and stored `abc` as 0.0000 (#4077, PR #4345). A commission rate above 999.99 is still held to it.
- Import Contacts (both modes) and Import Users (the user's contact row): every cell through `pg_import_contact_cell($column, $value)`, which trims and sends `affiliate_commission_rate` through `pg_parse_commission_rate()`, the Site Settings reader. Empty is `0.00`, as before.

## And the stock quantity cell?
- `inventory_quantity` is read with `pg_products_mass_quantity_value()` (Modify Selected's reader, `catalog-stock-quantity-input.md`): `1.200` on a Turkish panel is 1200. A cell that is not a whole count of zero or more (`2,5`, `-3`, words) leaves the row out, named with the decimal rows. Before (#4446) the raw cell went to SQL: `1.200` stored 1, `2.500` 3. A count past the column's top leaves the row out on its own list (`stock-quantity-column-bound.md`).
- A file with an `inventory` or `inventory_quantity` column sets each written product's out-of-stock mark from the stored row with `pg_products_import_stock_flag()` (`pg_pb_apply_stock_flag()`'s rule, `product-stock-flag-on-create.md`); before, the import never touched `out_of_stock`.

## Why?
- The three importers read a `;` CSV (`pg_csv_read_separator()`), which Turkish Excel writes with a decimal comma. The session is non-strict (`core.php` pins `sql_mode`), so MySQL cut a raw cell at the comma without a word: `7,5` became 7.00, weight `0,125` became 0.0000 (a product shipped weightless), `12,5` became 12.00. The product screen and Site Settings already read both separators (`product-screen-units-and-commission.md`).
- A .xlsx/.ods upload and the products export write a dot; those files load the same as before.

## How is it checked?
- `tests/locale_amount_inputs_test.php` (stock cell, source pins). `tests/import_decimal_comma_test.php`: the readers (7,5 7.50; 0,125 0.1250; 0.125 unchanged; 1E-3; other columns `null`), the row refusal and source pins that the three importers call them. Not run on a live instance: the import loops need a database.
