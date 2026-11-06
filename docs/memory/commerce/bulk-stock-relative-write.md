---
name: bulk-stock-relative-write
description: Edit Selected and the Catalog Manager bulk edit write a stock increase or decrease inside the UPDATE (pg_explorer_bulk_quantity_sql()); the lost update it fixed.
---

## How is a bulk stock change written?
- Without stock locations, both screens put `pg_explorer_bulk_quantity_sql($mode, $value)` into the product's UPDATE (`includes/explorer/handle_products_bulk.php`):
  - `value`: `inventory_quantity = 'N'` (a set is absolute on purpose);
  - `increase`: `inventory_quantity = IF(inventory_quantity <= MAX - N, inventory_quantity + N, inventory_quantity)`;
  - `decrease`: `inventory_quantity = IF(inventory_quantity > N, inventory_quantity - N, 0)` (the column is INT UNSIGNED, so no `GREATEST(x - N, 0)`).
- With stock locations the change already went through `pg_stock_bulk_write()` → `pg_stock_change()` (a delta), unchanged.

## What did it fix?
- Both screens read `inventory_quantity`, computed the new count in PHP (`pg_explorer_bulk_product_new_quantity()`) and wrote it back as a number. A checkout, a barcode scan or an API change between the read and the write (Edit Selected also inserts shipping zones in between) was overwritten: "increase by 10" on a product at 50 that sold 3 meanwhile stored 60, not 57.

## What about the out-of-stock mark and the counters?
- For an increase or a decrease the mark is not built from the read: `pg_stock_refresh_out_of_stock($product_id)` sets it from the stored row right after the UPDATE. A `value` change keeps `pg_products_bulk_out_of_stock_sql()` in the UPDATE.
- The read still decides "too large" (counted and skipped) and the stock events compare the row before and after the write, as before.

## Where is it tested?
- `tests/bulk_stock_relative_write_test.php`: the SET piece per mode and the guard that neither screen writes `inventory_quantity = '" . $new_…`.
