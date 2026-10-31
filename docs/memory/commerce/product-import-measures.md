---
name: product-import-measures
description: Product import weight, dimension and commission limit cells read with the product screen's readers; a cell that is not a number leaves its row out and named (#4077, PR #4124)
---

## How are weight, dimensions and the commission limit read?
- `pg_products_import_row_measures($row, $positions)` (`includes/fn/ecommerce.php`) reads `weight`, `length`, `width`, `height` with `pg_pb_decimal_from_input()` and `commission_rate_limit` with `pg_pb_commission_rate_from_input()`, the product screen's readers: `1,5` is 1.5, `7,5` 7.50 (top 999.99). A spreadsheet exponent (`1E-3`) is read as its number; an empty cell keeps the stored value.
- A cell with anything but digits, `.` and `,` (words, a unit, a minus sign) or over 15 digits returns `false`: the row is left out and named ("…a weight, dimension or commission rate limit in them is not a number: …"), after the money and customs checks.
- Before, these cells were written raw and the non-strict session kept 1 of `1,5`.

## Which test covers it?
- `tests/import_products_test.php` (`test_products_import_row_measures`, `test_products_import_reads_measures_before_the_write`). Not run on a live import: the loop needs a database.

Related: product-import-prices.md (the money cells and the refused-row message)
