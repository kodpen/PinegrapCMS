---
name: catalog-stock-quantity-input
description: How the Catalog Manager quick and bulk stock edits read a typed quantity: grouped the panel's way (1.200), whole counts only, refused with "Enter an inventory quantity."
---

## How is a typed stock quantity read?
- The quick stock cell (`pg_explorer_catalog_quick_quantity()`, `includes/explorer/handle_catalog_edit.php`) and the bulk "Set to this quantity / Increase by / Decrease by" (`pg_explorer_bulk_quantity_value()`, `includes/explorer/handle_products_bulk.php`) read the text through `pg_normalize_amount()`, like the price boxes (product-bulk-price-edit.md): `1.200,00`, `1,200.00` and `1.200` on a Turkish panel are 1200.
- A value that is not a whole count (`3.7`, `2,5`, `1,200` on a Turkish panel) or not a number is refused with "Enter an inventory quantity." and no product is touched. The quick cell takes a negative figure as 0; the bulk helper passes it on and `pg_explorer_bulk_product_new_quantity()` keeps the result at 0 or above.

## What did it fix?
- Both read `is_numeric()` and then cast with `(int)`: Turkish `1.200` was 1.2, stock was set to 1 and `pg_product_stock_changed()` sent that 1 to the marketplaces, without a word. The bulk box is `type=number` in `view_folders.php`, but Chrome keeps a typed `1.200` on a Turkish page and the script posts it as typed; the server is the only check.

## Which tests cover it?
- `tests/explorer_handle_catalog_edit_test.php` (`test_explorer_catalog_quick_quantity`) and `tests/explorer_handle_products_bulk_test.php` (`test_explorer_bulk_quantity_value`). Not submitted in a browser (#3643).

Related: docs/memory/erp/erp-stock-count.md (the ERP count's scan quantity), docs/memory/ui/money-field-input-mask.md
