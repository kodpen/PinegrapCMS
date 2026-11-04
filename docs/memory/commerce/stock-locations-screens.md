---
name: stock-locations-screens
description: Stock per location on the panel: product screen boxes per location, the quick stock cells refused, the two bulk edits at one location, stock:<location> import and export columns
module: stock_location_screens
---

## What does the product screen show?
- Edit mode on a site with locations (`pg_stock_screen_locations()`: the enabled ones, empty without locations): one box per enabled location `stock_location[<id>]` with the figure it opened with in `stock_location_was[<id>]`, and the total read only, never posted (`pg_pb_render_stock_quantity()`, `product_builder.php`).
- A product with no rows yet shows its whole total at the default location (`pg_stock_screen_quantities()`), where the writer seeds it on its first change.
- Create mode, the variant matrix and a bundle keep the one box; a new product's count lands at the default location on its first stock change.

## How does the save write it?
- The stored total stays in the row (`$stock_action = 'keep'`); after the row (so tracking switched on in the same save counts) each changed box goes through `pg_stock_change()` as the difference to its opened figure (`pg_stock_posted_changes()`, `pg_stock_apply_changes()`, reason `panel`): a sale meanwhile stays sold. Without the opened figure the box is set (`pg_stock_set()`).
- Then `pg_stock_refresh_out_of_stock()` and the save's usual `pg_product_stock_changed()` with the new total. A refused change (a sale took the units) returns `stock_kept`, which `edit_product.php` already reports.
- A box that is not a whole count of 0 or more is refused before anything is written (`pg_pb_validate_product_post()`).

## What do the quick stock cells do?
- With locations the product list's cell (`pg_pb_quick_update()` code `locations`) and the Catalog Manager's quick cell refuse: "Stock is kept per location: change it on the product screen."

## How do the bulk edits take a location?
- Catalog Manager (`handle_products_bulk.php`): the panel's data carries `stock_locations` (`pg_explorer_bulk_stock_location_options()`), `view_folders.php` draws a Location select beside the quantity and posts `quantity_location`.
- Change Selected (`edit_products.php`, popup `includes/templates/edit_products.php`): the popup adds a hidden `edit_inventory_location` to the opener's form.
- Both: no `inventory_quantity =` in the row; after it `pg_stock_bulk_write()` (the location's figure, `pg_stock_bulk_delta()`: set / increase / decrease never below 0, reason `bulk_edit`), then the mark. Untracked products and bundles are left alone; `too_many` counts as "kept their stock quantity". The announcement reads the total before and after as before.

## What do import and export carry?
- Export: one `stock:<location name>` column per location (all, enabled or not), after every other column; empty for an untracked product (`pg_stock_export_headings()`, `pg_stock_export_cells()`, rows read once by `pg_stock_rows_by_product()`).
- Import: `pg_stock_import_columns()` matches `stock:<name>` without case or spaces; a name that is no location (or a second column for one) stays in the unknown headings list. Cells read like `inventory_quantity` and refuse the row the same way.
- With locations the row never writes `inventory_quantity`; after it `pg_stock_import_write()`: tracked products get `pg_stock_set()` per target (reason `import`), where stock columns win and the old column alone fills the default location (`pg_stock_import_targets()`); an untracked product gets the old column written directly (it has no rows). Nothing is announced, as before.

## What guards it?
- `tests/stock_locations_screens_test.php`: quantities shown, posted deltas and refusals, the bulk delta, import columns and targets, export cells read back, source pins on every writer. Not run in a browser.

Related: docs/memory/commerce/stock-locations-model.md, docs/memory/commerce/products-bulk-change-stock-bin-price.md, docs/memory/commerce/catalog-stock-quantity-input.md
