---
name: pick-pack-shelf-address
description: Shelf address (product_bins): the "Shelf" box on the stock card, Modify Selected's "Shelf address", shelf import columns, shelf in POST /products/{id}/inventory
module: product_bins
---

## Where is a shelf address kept?
- `product_bins`, one row per product and stock location (`pick-pack-tables.md`); location 0 on a site without locations. An empty address is no row. Every writer goes through `pg_bins_write()` (`includes/fn/product_bins.php`), after `pg_bin_clean()`: control characters and runs of spaces become one space, ends trimmed, at most `PG_BIN_MAX` (40) characters, else refused. PR #10966.
- An address typed before the first location was added stays at location 0: the stock card shows it in the default location's box, and writing the default location's address deletes the 0 row. The pick list (PP3) reads rows as they are.
- A bundle has no address: no box, Modify Selected and the import skip it. Nothing is announced, no `audit_log` line.

## What does the product screen show? (Edit Product, Inventory Tracking panel)
- Edit mode only, not a bundle, after the upgrade (`pg_bins_ready()`). With locations: each location is one row, its count and a "Shelf" box beside it `product_bin[<id>]` (`pg_bin_location_rows_html()`), one help line under "Stock by location". Without: one "Shelf" box `product_bin[0]` under Inventory Quantity, the help line under it.
- Create mode has no box: the address is typed once the product exists. Save: `pg_pb_update_product()` writes the boxes drawn (`pg_pb_bin_location_ids()`), an emptied box removes the address; one too long is refused first in `pg_pb_validate_product_post()`.

## What does Modify Selected do?
- One entry "Shelf address" (`edit_shelf_method`: set or clear) opening a box with the address `edit_shelf` and, with locations, a Location select `edit_shelf_location` (not one: the default). `pg_products_mass_shelf_value()`; refusals `shelf_missing`, `shelf_too_long` in `pg_products_mass_edit_problem()`. The notice adds "shelf address changed" / "cleared".

## What does the import read?
- Heading `shelf` (without locations location 0, with them the default location) and `shelf:<location name>` (any location, enabled or not; matched without case). Unknown names, a second column for one location, or `shelf:` without locations stay in the unrecognised columns warning (`pg_bin_import_columns()`).
- An empty cell clears the address, as the file's other text columns do; a cell past 40 characters leaves the row out, named in "These rows were not imported because their shelf address is longer than 40 characters". No export column yet.

## What does the API take?
- `POST /products/{id}/inventory` takes `shelf` (body string, max 40, nullable): written at the call's location (`pg_bin_location_key()`) after the stock change succeeded, never on a dry run; "" or null removes it, left out leaves it. Text that is not UTF-8: 422 `shelf`. The answer adds `shelf` (`string?`) when sent. The batch endpoint does not take it; `PUT /products/{id}/shelf` for the address alone is PP6.

## What guards it?
- `tests/product_bins_test.php`: the cleaner, screen values, posted boxes, the stock card's rows (one box a location, beside its count), import columns and rows, Modify Selected's one entry, the API param and its order. No browser run.

Related: docs/memory/commerce/pick-pack-tables.md, docs/memory/commerce/stock-locations-screens.md, docs/memory/plans/pick-pack-steps.md
