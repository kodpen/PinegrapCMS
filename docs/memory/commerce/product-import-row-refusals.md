---
name: product-import-row-refusals
description: Product import rows left out and why each list says so (too large, not whole, not a number, in the Recycle Bin); an update finds its product by id (PR #4917)
---

## Which rows does import_products.php leave out, and how are they named?
- Each refused row goes into one list, named after the import on the product list as `number (name)` (up to 50), and nothing of it is written. The first refused cell decides the list:
  - a price, compare-at price or extra shipping cost that is not an amount (`rows_not_amounts`), or one above `PG_PRODUCT_PRICE_MAX` / with more than 15 digits (`rows_price_too_large`, "…is too large (at most ₺21.474.836,47)", the top through `pg_money_text()` in the base currency);
  - a weight, length, width or height above decimal(12,4)'s top (`rows_decimal_too_large`), or a decimal cell or stock cell that is no number at all (`rows_not_numbers`);
  - a stock cell with a fraction or a minus sign (`rows_quantity_not_whole`, "…is not a whole number of zero or more"), or one above the column (`rows_quantity_too_large`);
  - an HS code or country of origin that cannot be stored (`rows_not_customs`);
  - a product in the Recycle Bin (`rows_binned`).
- The readers are pure, in `includes/fn/ecommerce.php`: `pg_products_import_price_read()` (`cents`, `too_large`), `pg_products_import_row_prices()` (adds `too_large`), `pg_products_import_row_decimals_problem()` (`''`, `not_number`, `too_large`), `pg_products_import_quantity_refusal()` (`not_whole`, `not_number`, called once `pg_products_mass_quantity_value()` gave null).
- Before (#4719, PR #4917) a too-large price was reported as "not an amount" and a fractional, negative or too-large stock, weight or size as "not a number": the operator looked for a typo that was not there.

## What happens to a row whose product is in the Recycle Bin?
- The product lookup reads `recycled` when the bin's upgrade step has run (`pg_catalog_bin_ready()`); a binned product's row is left out and listed ("…because their product is in the Recycle Bin"), whether Update Existing Products is on or off. A binned product still holds its code (`pg_product_name_holder()`), so the row cannot create a new one either.
- Before (#4718, PR #4917) the import updated the binned product's price, stock and other columns, wrote a price history row and counted it as updated, while Edit Product, Modify Selected, the Catalog Manager and the barcode screens refuse a binned product.

## How does an update find its product?
- `UPDATE products … WHERE id = <the id the name lookup found>`; it used to be `WHERE name = …`, which would change every row holding that name.

## What guards it?
- `tests/import_products_test.php` (price read, row prices, quantity refusal, the lists and messages, the bin check before Update Existing Products, the update by id), `tests/import_decimal_comma_test.php` (decimal problems). The import loop itself needs a database and was not run here.
