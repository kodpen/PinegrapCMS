---
name: catalog-stock-quantity-input
description: How the Catalog Manager stock edits and the product list's Modify Selected read a typed quantity: grouped the panel's way (1.200), whole counts only, else refused
---

## How is a typed stock quantity read?
- The quick stock cell (`pg_explorer_catalog_quick_quantity()`, `includes/explorer/handle_catalog_edit.php`) and the bulk "Set to this quantity / Increase by / Decrease by" (`pg_explorer_bulk_quantity_value()`, `includes/explorer/handle_products_bulk.php`) read the text through `pg_normalize_amount()`, like the price boxes (product-bulk-price-edit.md): `1.200,00`, `1,200.00` and `1.200` on a Turkish panel are 1200.
- A value that is not a whole count (`3.7`, `2,5`, `1,200` on a Turkish panel) or not a number is refused with "Enter an inventory quantity." and no product is touched. The quick cell takes a negative figure as 0; the bulk helper passes it on and `pg_explorer_bulk_product_new_quantity()` keeps the result at 0 or above.

## How does the product list's Modify Selected read it?
- `edit_products.php` reads `edit_inventory_quantity` once, before the product loop, with `pg_products_mass_quantity_value()` (`includes/explorer/handle_products_bulk.php`, loaded with `require_once`): `pg_explorer_bulk_quantity_value()` plus a refusal of a leading minus, since the method (set / increase / decrease) gives the direction. A refused figure adds "Enter an inventory quantity." and goes back to the list with no product touched.
- The new count is `pg_explorer_bulk_product_new_quantity()` on the stored count read with `db_q()`, never below 0, written as an int. The box (`includes/templates/edit_products.php`) is an Inputmask `integer` field without minus.
- Before (#4075, PR #4124) the posted text went into SQL and arithmetic as typed: Turkish `1.200` stored 1, `12.5` 12, and a decrease by `-5` raised the stock.

## What did it fix?
- Both read `is_numeric()` and then cast with `(int)`: Turkish `1.200` was 1.2, stock was set to 1 and `pg_product_stock_changed()` sent that 1 to the marketplaces, without a word. The bulk box is `type=number` in `view_folders.php`, but Chrome keeps a typed `1.200` on a Turkish page and the script posts it as typed; the server is the only check.

## Which tests cover it?
- `tests/explorer_handle_catalog_edit_test.php` (`test_explorer_catalog_quick_quantity`) and `tests/explorer_handle_products_bulk_test.php` (`test_explorer_bulk_quantity_value`, `test_products_mass_quantity_value`, `test_products_mass_change_quantity_is_read_before_the_write`). Not submitted in a browser (#3643).

Related: docs/memory/erp/erp-stock-count.md (the ERP count's scan quantity), docs/memory/ui/money-field-input-mask.md
