---
name: delivery-tower-problems-table
description: The shipment_problems table (step 11.0.258): one row per problem found on a parcel, its columns, the unique open-row key, who writes and closes rows, order deletion
---

## What is the table?
- `shipment_problems`, step `upgrade_2026_11_0_shipment_problems()` (label 11.0.258, `includes/migrations/2026.11.0.php`), listed in `get_tables()`. Columns as `workflow/decisions-made-schema-40.md` gives them (owner, poll 192; architect, message 10966); no column on `shipping_tracking_numbers`.
- `shipment_id` is the parcel row, `order_id` and `ship_to_id` its order and address. `kind`: 1 stuck, 2 not handed over, 3 delivery problem, 4 returned (`pg_delivery_tower_kind_codes()`).
- `assigned_to` (user, 0 nobody) and `note` VARCHAR(500) are the desk's (DT3). `closed_at` 0 while open; `closed_by` 0 when a later status closed it; `close_reason` 0 open, 1 settled by a status, 2 sent again, 3 refunded, 4 found, 5 other (`pg_delivery_tower_close_reasons()`).

## Why is the unique key (shipment_id, kind, closed_at)?
- An open row has `closed_at` 0, so a second open row of one kind on a parcel is refused and the pass writes with `INSERT IGNORE`: two overlapping runs open it once. Closed rows keep their own `closed_at`, so a parcel's history over time stays row by row.

## Who writes it?
- The hourly problem pass opens and closes with reason 1 (`delivery-tower-passes.md`); the desk (DT3) assigns, notes and closes with reasons 2 to 5.
- `delete_order.php` removes an order's rows (`pg_delivery_tower_delete_for_order()`). No name or address is kept: the desk reads them from the order.

## Was the step run twice?
- Yes, on a sandbox MariaDB (#PR of DT1b): `config.version` set back to 2026.4.10 and `php install/index.php automated_upgrade` twice; the second run found the table in place.
