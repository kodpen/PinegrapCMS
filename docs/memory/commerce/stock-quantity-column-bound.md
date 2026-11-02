---
name: stock-quantity-column-bound
description: Panel stock writers refuse or skip a count past PG_INVENTORY_QUANTITY_MAX (the INT UNSIGNED top) instead of letting MySQL cut it (#4711)
---

## What is the top and where is the check?
- `products.inventory_quantity` is INT UNSIGNED: `PG_INVENTORY_QUANTITY_MAX` is 4294967295 and `pg_inventory_quantity_too_large()` (`includes/fn/ecommerce.php`, beside `PG_PRODUCT_PRICE_MAX`) compares as a float, before any int cast.
- The readers `pg_explorer_catalog_quick_quantity()` and `pg_explorer_bulk_quantity_value()` (so `pg_products_mass_quantity_value()` too) hand a figure past the top back as a float for the caller to refuse, as the price readers do; `pg_explorer_bulk_product_new_quantity()` returns an increase past it as it is.
- The column's top was chosen, not the product list cell's 9,999,999: it refuses only what was silently cut, so no stock that saved before is refused now. The external API keeps its own ±1,000,000 (`api-bulk-inventory-quantity.md`).

## What does each writer do with it?
- Catalog Manager quick stock cell and Edit Selected's typed figure (any method): "Inventory Quantity is too large.", nothing written.
- Catalog Manager bulk edit: the typed figure is refused the same way; an increase that would pass the top keeps that product's stock, is counted in `quantity_too_large`, the answer is `partial` with "The stock quantity was left alone on N of them…".
- Edit Selected increase: the product keeps its stock and is counted ("N product(s) kept their stock quantity…"); a product with nothing else asked is neither written nor counted as updated.
- Import Products: the row is left out and listed by number and name ("These rows were not imported because their stock quantity is too large: …").
- Barcode increase (`barcode_increase_inventory.php`): "New Inventory Quantity is too large.", nothing written. The decrease cannot pass the top.

## What did it do before?
- The session is not strict, so MySQL stored 4294967295, the screen said "stock quantity changed" (the barcode screen showed the uncut sum) and the marketplace queue took the cut value (#4711).

## Tests?
- `tests/explorer_handle_catalog_edit_test.php`, `tests/explorer_handle_products_bulk_test.php` (the check, the readers, source pins of the five writers). Not run on a live instance.
