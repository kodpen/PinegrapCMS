---
name: stock-locations-steps
description: Steps SL1-SL3 of plans/stock-locations.md: tables and the sum-keeping writer, product screens and import, order completion and scans by location; claim as [plan:stock-locations] SL<n>
---

## Which run side by side?
- Wave 1: SL1 alone (question 1 first). Wave 2: SL2, SL3 and SL4 at once (screens, completion, history: different files). Wave 3: SL5 (question 2 first) and SL6 at once. Wave 4: SL7. SL4-SL7: `stock-locations-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/stock_locations_<topic>_test.php` (pure functions, red first), memory under `commerce/`, texts through `tr_add.php`.

## Which steps are there?
- SL1 (M) Tables, model, the writer. `free` after question 1.
  - Migration sub-step with `install_create_table()` for `stock_locations`, `product_stock`, `stock_moves` and the two columns (in `get_tables()`, rerun twice); no data step: rows appear when the operator adds a location (the first add creates the default location and one row per tracked product from its total). New `includes/fn/stock_locations.php`: `pg_stock_locations()`, `pg_stock_default_location()`, `pg_stock_rows($product_id)`, `pg_stock_set($product_id, $location_id, $quantity, $reason, $ref)` and `pg_stock_change($product_id, $location_id, $delta, ...)` (one UPDATE with the guard, the move row, then the sum into `products.inventory_quantity` in the same transaction), `pg_stock_has_locations()` (one cached read, so a site without locations pays nothing). Tests: the sum after each writer call, a negative delta refused, the no-locations path. Memory: `commerce/stock-locations-model.md`.

- SL2 (M) Product screens, bulk, import and export. After SL1.
  - `product_builder.php` and the stock box of the catalog: one quantity per enabled location when locations exist (the total shown read-only), the variant rows the same; `products-bulk-change-stock-bin-price.md`'s bulk change takes a location; `import_products.php` and the export read and write `stock:<location name>` columns beside the old quantity column (the old column alone fills the default location). Memory: `commerce/stock-locations-screens.md`.

- SL3 (M) Completion, scans, the API decrement. After SL1.
  - `pg_order_complete()`'s stock part takes the units from `orders.stock_location_id` (set at completion from the shipping method's location, else the default) through `pg_stock_change()`, with question 2's rule when the location lacks units; `barcode_decrease_inventory.php` gets a location pick (the default when none); the inventory API's atomic decrement takes `location` (`api-bulk-inventory-quantity.md`); `inventory.changed` and `stock.low` carry the location; the out-of-stock flag reads the total as today. Parity test: a site without locations completes an order with the same writes as before. Memory: `commerce/stock-locations-completion.md`.
