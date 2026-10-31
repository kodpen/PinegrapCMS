---
name: import-decimal-cells
description: Product, contact and user imports read weight, dimensions, commission rate limit and affiliate commission rate with either decimal separator, as the screens do
module: ecommerce
---

## Which import cells go through a decimal reader?
- Import Products: `weight`, `length`, `width`, `height` (`decimal(12,4) unsigned`) and `commission_rate_limit` (`decimal(5,2) unsigned`) through `pg_products_import_decimal_cell($column, $value)` (`includes/fn/ecommerce.php`, needs `product_builder.php`), on the update and the insert path. Weight and dimensions: `pg_pb_decimal_from_input()` rounded to four places; the rate: `pg_pb_commission_rate_from_input()` (two places, held to 999.99). Other columns get `null` and keep their own path. An empty cell is still skipped (the column is left as it is).
- Import Contacts (both modes) and Import Users (the user's contact row): every cell through `pg_import_contact_cell($column, $value)`, which trims and sends `affiliate_commission_rate` through `pg_parse_commission_rate()`, the Site Settings reader. Empty is `0.00`, as before.

## Why?
- The three importers read a `;` CSV (`pg_csv_read_separator()`), which Turkish Excel writes with a decimal comma. The session is non-strict (`core.php` pins `sql_mode`), so MySQL cut a raw cell at the comma without a word: `7,5` became 7.00, weight `0,125` became 0.0000 (a product shipped weightless), `12,5` became 12.00. The product screen and Site Settings already read both separators (`product-screen-units-and-commission.md`).
- A .xlsx/.ods upload and the products export write a dot; those files load the same as before.

## How is it checked?
- `tests/import_decimal_comma_test.php`: the readers (7,5 7.50; 0,125 0.1250; 0.125 unchanged; other columns `null`) and source pins that the three importers call them. Not run on a live instance: the import loops need a database.
