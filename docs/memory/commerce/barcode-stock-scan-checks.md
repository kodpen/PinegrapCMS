---
name: barcode-stock-scan-checks
description: Barcode stock increase/decrease and the product screen's barcode save: whole quantities only (pg_barcode_scan_quantity()), no product in the Recycle Bin or missing (#4447)
module: ecommerce
---

## How is a scanned quantity read?
- `barcode_increase_inventory.php` and `barcode_decrease_inventory.php` read `quantity` with `pg_barcode_scan_quantity()` (`includes/fn/ecommerce.php`) before the product lookup: a whole count from 1 to 1000000, read the panel's way (`pg_normalize_amount()`). A fraction, a minus sign, zero or text answers "Enter an inventory quantity." and nothing is written.
- Before (#4447) the posted value was used as sent: `1.5` showed "+1.5 / 4.5", the INT column stored 5 and `inventory.changed` announced 4 (`(int)` cut).

## Which products are refused?
- Both scan screens refuse a product in the Recycle Bin (`pg_catalog_product_binned()`) with "The product is in the Recycle Bin. Restore it in the Catalog Manager or choose another product.", as Edit Product and Modify Selected leave it alone (products-bulk-change-stock-bin-price.md).
- `save_product_barcode` (`pg_panel_save_product_barcode()`, `includes/panel/commerce.php`) answers "Product not found." for an id with no product row and the Recycle Bin message for a binned one, before the insert: a code saved for a missing product was held for good ("already assigned to another product") and a scan looked for a product that does not exist.
- A decrease larger than the stock still goes to 0 and reports success (decisions list); this did not change it.

## Which test covers it?
- `tests/barcode_stock_checks_test.php` (the reader, source pins of both screens and the save). Not scanned on a live instance.
