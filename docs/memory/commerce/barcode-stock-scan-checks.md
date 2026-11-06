---
name: barcode-stock-scan-checks
description: Barcode stock increase/decrease and the product screen's barcode save: whole quantities only (pg_barcode_scan_quantity()), no product in the Recycle Bin or missing (#4447)
module: ecommerce
---

## How is a scanned quantity read?
- `barcode_increase_inventory.php` and `barcode_decrease_inventory.php` read `quantity` with `pg_barcode_scan_quantity()` (`includes/fn/ecommerce.php`) before the product lookup: a whole count from 1 to 1000000, read the panel's way (`pg_normalize_amount()`). A fraction, a minus sign, zero or text answers "The quantity must be a whole number from 1 to 1,000,000." and nothing is written (#5775).
- Before (#4447) the posted value was used as sent: `1.5` showed "+1.5 / 4.5", the INT column stored 5 and `inventory.changed` announced 4 (`(int)` cut).

## Which products are refused?
- Both scan screens refuse a product in the Recycle Bin (`pg_catalog_product_binned()`) with "The product is in the Recycle Bin. Restore it in the Catalog Manager or choose another product.", as Edit Product and Modify Selected leave it alone (products-bulk-change-stock-bin-price.md).
- `save_product_barcode` (`pg_panel_save_product_barcode()`, `includes/panel/commerce.php`) answers "Product not found." for an id with no product row and the Recycle Bin message for a binned one, before the insert: a code saved for a missing product was held for good ("already assigned to another product") and a scan looked for a product that does not exist.
- A decrease larger than the stock still goes to 0 and reports success (decisions list); this did not change it.

## What do the screens search and say?
- A code is trimmed (screen and `pg_barcode_scan_product()`), then looked up in `product_barcodes` and `products.name` only, never the product id: the not-found text is "No product found with this barcode or product code." and the first column "Product Code" (#5775). The description says blanks typed in the boxes are not read as a barcode (`ignoreIfFocusOn`).

## Which test covers it?
- `tests/barcode_stock_checks_test.php` (the reader, source pins of both screens and the save). Not scanned on a live instance.

## What happens on a product with stock tracking off?
- The count is written as before (Modify Selected and the Catalog Manager do the same); the answer's `inventory` is read by the screen, and when it is not `1` the table row, the status line and a warning box say "Stock tracking is off for this product; the count is used when tracking is turned on." `out_of_stock` and `inventory.changed` are untouched (#5788). Pinned by `test_barcode_stock_screens_warn_on_an_untracked_product`.

## How is the count written without stock locations?
- In the statement: the increase adds (`inventory_quantity = inventory_quantity + ?`, the column's top in the WHERE, no row matched = "too large"), the decrease takes and stops at 0 (`GREATEST(0, CAST(… AS SIGNED) - ?)`, then the guarded `out_of_stock` update); the count is read back after. With locations `pg_stock_scan()` writes it.
- Before, both screens wrote the figure worked out from their first read: a checkout or another scan between the read and the write was overwritten, a sold unit coming back on the shelf. Pinned by `tests/barcode_stock_in_statement_test.php`.
