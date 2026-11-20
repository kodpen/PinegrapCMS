---
name: pick-pack-tables
description: Pick and pack's four tables and their keys, the reserved `lines` column, what an order's deletion removes, the "Packing only" right user.manage_packing
---

## Where do the tables come from?
- Step `upgrade_2026_11_0_pick_pack()` (11.0.260, `includes/migrations/2026.11.0.php`), PR #10873; all four on `get_tables()`. Columns and keys exactly as `workflow/decisions-made-schema-41.md`. InnoDB, utf8mb4_unicode_ci, every column NOT NULL DEFAULT 0 or '' but the TEXT ones (NULL).
- Rules on top of them: `pick-pack-rules.md` (`includes/fn/pick_pack.php`). No customer data in any of the four.

## What does each table hold?
- `product_bins`: a shelf address per product and stock location (`location_id` 0 without locations). Primary (`product_id`, `location_id`); key (`location_id`, `bin`).
- `pick_waves`: `name` ('' shows the number), `location_id` (0 any), `status` 0 open, 1 picked, 2 closed, `single_item`, `rule` JSON or NULL when made by hand, `picked` JSON, `created_by`, `created_at`, `closed_at`. Key (`status`, `created_at`).
- `pick_wave_orders`: one row per address in a wave: `wave_id`, `ship_to_id`, `order_id`, `ship_by`, `removed_at` (a cancelled order left). Primary (`wave_id`, `ship_to_id`); keys `ship_to_id`, `order_id`. One open wave per address is the writer's check, no key.
- `order_packs`: one row per address: `order_id`, `ship_to_id` (unique), `wave_id` (0 none), `status` 0 open, 1 packed, 2 short, `packer_id`, `started_at`, `packed_at`, `short_at`, `units`, `scanned`, `refused`, `lines` and `missing` JSON. Keys `order_id`, (`status`, `packed_at`), (`packer_id`, `packed_at`), `wave_id`.

## Is any column name a trap?
- `order_packs.lines`: LINES is a reserved word in MySQL and MariaDB. Every query names it in backticks (`` `lines` ``), or it fails with a syntax error and returns `false` silently.

## What does an order's deletion remove?
- `delete_order()` deletes the order's `pick_wave_orders` and `order_packs` rows, behind `pg_schema_has('order_packs')`. A wave keeps its other orders; `product_bins` and `pick_waves` hold no order's rows (`order-delete.md`).

## What is the "Packing only" right?
- `user.manage_packing` TINYINT UNSIGNED NOT NULL DEFAULT 0, same step. Read for role 3 only; roles 0-2 and the orders right have the screens already. With it alone: the Pick waves and Pack screens only, no price, total, customer e-mail or other Orders screen (PP3 and PP4 gate on it; PP1b gates nothing).
- On every role-3 ladder (`welcome.php`, `get_page.php`, `includes/fn/content.php`, `set_password.php`, `reset_password.php`, the new-user and import set-password link), read behind `pg_user_has_packing_column()` (`includes/authentication.php`); saved with `pg_user_packing_sql()`.
- Drawn by `pg_user_permission_ui()` as a row of its own after Commerce, not behind its gate, only when the store is on and the column exists: label "Packing only", one line "The Pick waves and Pack screens only, with no prices or customer e-mail.", nothing to pick (as Visitor Reports).
- Tests: `tests/pick_pack_schema_test.php`.
