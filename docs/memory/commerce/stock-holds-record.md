---
name: stock-holds-record
description: The stock_holds table (11.0.253) and stock_holds.php: units free, the cart checks reading them, placing under a per-product lock, release, the daily sweep (SH1 #10711, SH2)
module: stock_holds
---

## What is a hold row?
- `stock_holds` (step `upgrade_2026_11_0_stock_holds()`, label 11.0.253, in `get_tables()`): `order_id`, `product_id`, `location_id` (0 without stock locations), `quantity`, `expires_at`, `created_at` (Unix time). Unique (`order_id`, `product_id`, `location_id`); keys (`product_id`, `location_id`, `expires_at`) and `expires_at`. Columns bind: `workflow/decisions-made-schema-34.md`.
- A claim for `PG_STOCK_HOLD_MINUTES` (15), never a stock figure: the module never writes `products` or the per-location stock.
- Readers probe with `pg_stock_holds_ready()` (`pg_schema_has()`): files land before the upgrade.

## What do the functions answer?
- `pg_stock_hold_units_free($stock, $holds, $order_id, $now)`, pure: stock less other orders' rows whose `expires_at` is after now, never below 0; the order's own rows are not counted. Order 0 counts every hold.
- `pg_stock_hold_order_needs($lines, $stock)`, pure: product id => units in id order. A plain line holds its product; a bundle line (`components`) holds each component for quantity per bundle times the line's quantity, added to the same product bought alone (one pool, as `pg_bundle_cart_allowance()`). Untracked, back-order or unknown products are never held.
- `pg_stock_hold_free_now($product_id, $location_id, $total, $order_id)`: the stock at the location (`pg_stock_quantity_at()`), else the total, less live holds; the read placing does under its lock.
- `pg_stock_hold_order_lines($order_id)`: the order's active lines (`pg_order_item_active_sql()`) with bundle components and the stock flags.

## Which checks read the holds? (SH2)
- `check_inventory()` (`ecommerce.php`) and `pg_bundle_check_inventory()` (`bundles.php`): the stock at the order's location less other orders' live holds there. Nothing else reads a hold; a held unit is still in stock elsewhere.
- `pg_stock_hold_held_for($ids, $location_id, $order_id)`: one query per check, product id => units, empty before the upgrade. Pure parts: `pg_stock_hold_held_by_others()`, `pg_stock_hold_less_held()` (nothing held: the stock exactly as read, so a site with no holds checks as before), `pg_bundle_stock_less_held()` after `pg_bundle_stock_at_location()`.
- A line short of free units is cut or removed with the notices the checks already give; the held-by-a-checkout notice is SH4.

## How is a hold placed?
- `pg_stock_hold_place($order_id)` → `array(placed, product_id)`. Location: `pg_stock_order_location()`. Rows of products the order no longer needs go first. Each product in id order under `GET_LOCK(pg_stock_hold_lock_name(), 5)` for the read and the upsert only (`ON DUPLICATE KEY UPDATE`), then `RELEASE_LOCK`.
- The lock name carries a hash of the database (`pg_stock_hold_<16 hex>_<id>`): GET_LOCK names are server-wide, as `pg_tr_job_lock_sql()` and `mp_webhook_lock_name()` do.
- Not enough units, or the lock not got in 5 s: every hold of the order is removed and the product is named. Before the upgrade it answers placed with nothing written.
- Nothing calls it yet: SH3 calls it in `submit_order.php`.

## Who removes rows?
- `pg_stock_hold_release($order_id)`: `delete_order.php`; SH3 adds completion, a failed payment and the return to the cart.
- `pg_stock_hold_forget_product($product_id)`: `pg_pb_delete_product()` (`product_builder.php`), the one product delete.
- `pg_stock_hold_sweep_daily()` from `job.php`: once a day (marker `data/temp/stock_holds_swept.txt`), rows with `expires_at` a day past, 1,000 a statement, 20 statements a run.

## What guards it?
- `tests/stock_holds_test.php`: units free with other orders, expired and own rows; held by others and less held; plain and bundle needs; a location; the lock name; the sweep's statement; source pins of the callers, the step, `get_tables()` and both checks' statements.
