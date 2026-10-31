---
name: product-stock-flag-on-create
description: The out-of-stock mark the product screens work out (pg_pb_apply_stock_flag()): tracked with an empty quantity counts as 0, so a created product is marked at once (#4079, PR #4124)
---

## What does pg_pb_apply_stock_flag() decide?
- `product_builder.php`, called by `pg_pb_create_product()` (screens, API, Workspace, designer, catalog groups), the edit save and the list's quick stock cell. Tracking on and a quantity of 0 or less is `out_of_stock = '1'`, with `out_of_stock_timestamp` stamped only when the row was not flagged already; otherwise `'0'`. A write with neither `inventory` nor `inventory_quantity` is left alone.
- An empty quantity counts as 0 (`(int) '' <= 0`), as the row stores it.

## What did it fix? (#4079, PR #4124)
- The rule was `($quantity !== '') && ((int) $quantity <= 0)`: a product created tracked with the box empty was stored with 0 in stock and `out_of_stock = 0`, missing from the "Out of Stock" view and stock cards, until the first edit save (whose box shows 0) marked it and stamped the save time instead of the creation time.

## Which test covers it?
- `tests/product_stock_flag_test.php` (creation path only: with a product id the function reads the stored flag from the database).
