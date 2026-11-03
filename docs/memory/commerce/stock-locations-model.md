---
name: stock-locations-model
description: Stock per location: the three tables and two columns (11.0.162), the sum rule, the writer pg_stock_change()/pg_stock_set(), the no-locations path, seeding on the first location
module: stock_locations
---

## Which tables hold it?
- `stock_locations` (name, address, enabled, is_default, sort_order), `product_stock` (one row per product and location, `quantity` INT UNSIGNED, primary key on the pair), `stock_moves` (signed `delta`, `quantity_after` at the location, `reason`, `ref`, `note`, `user_id`, `created_at`). `shipping_methods.stock_location_id` and `orders.stock_location_id` (0 = the default) name where an order ships from. Step `upgrade_2026_11_0_stock_locations()` (11.0.162) creates them and moves no data.
- Read only after `pg_stock_ready()`; whether the site uses locations is `pg_stock_has_locations()`, one cached COUNT per request (pass `true` to read again).

## What is the sum rule?
- Once a product has rows, `products.inventory_quantity` is the sum of them and is written only by `pg_stock_change()` / `pg_stock_set()`. Every reader of the total (catalogue, cart, completion, marketplaces, ERP, API) is unchanged.
- The writer, in one transaction: seed the product's default row if it has none, lock its rows (`FOR UPDATE`), plan with `pg_stock_plan()` (pure), the guarded row update (`pg_stock_change_sql()`: a decrease matches only `quantity >= n`, so a parallel writer cannot go below 0 and gets `not_enough`), one `stock_moves` row, then `pg_stock_sum_sql()` writes the total from the rows. Delta 0 writes nothing.
- Option `transaction => false` when the caller holds one (a transfer's two changes). `products` may still be MyISAM: the sum statement recomputes from the rows, so a later change repairs a total a rollback could not undo.

## What is refused?
- A reason outside `pg_stock_reasons()` (a fixed word list, so the history filters by it); a delta or quantity that is not a whole number (`pg_stock_clean_delta()`, `pg_stock_clean_quantity()`); an unknown location; a product with `inventory = 0` while the site has locations (`untracked`: untracked products have no rows); a decrease past the location's units (`not_enough`, nothing taken from another location, decision #5042 question 2).

## What does a site without locations do?
- `pg_stock_resolve_location()` answers 0 and the writer changes `products.inventory_quantity` itself with the same guarded shape (`pg_stock_total_sql()`), no row and no move; before and after are read back.

## When do rows appear?
- `pg_stock_location_add()`: the first location becomes the default and gets one row per product with `inventory = 1` from its total (`INSERT IGNORE ... SELECT`, rerunnable; no move, the total is unchanged). A product that starts tracking later gets its default row on its first write (`pg_stock_seed_product()`).

## Who announces?
- Not the writer: its caller passes the returned `before`/`after` (or `total_before`/`total`) to `pg_product_stock_changed()` once per product (`api/api-webhook-stock-events.md`), which also queues the marketplace.

## What guards it?
- `tests/stock_locations_model_test.php`: the sum after each change, a decrease past the row refused, the no-locations path, the guarded statements, the step's helpers and `get_tables()`.
