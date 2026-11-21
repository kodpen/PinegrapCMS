---
name: decisions-made-schema-41
description: Decisions list, schema part 41 (2026-11-20 on): pick and pack's four tables (product_bins, pick_waves, pick_wave_orders, order_packs) and the "Packing only" right user.manage_packing (poll 193)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-40.md`.

## Does pick and pack get tables and a right? (owner, 2026-11-20, agent channel poll 193, channel message 10746)
- "Evet, dört tablo ve paketleme yetkisi": `product_bins`, `pick_waves`, `pick_wave_orders`, `order_packs`, and a "Packing only" right as a column on `user`. No column is added to `products` or `product_stock`.
- The right: a warehouse worker sees only the pick and pack screens, no prices and no customer e-mail.
- Not chosen: four tables with today's rights (the packer is a person with the orders right); the shelf address alone for now.
- The plan is the owner's yes to poll 181 (pick and pack); step PP1b.

## Which columns does product_bins have? (architect, 2026-11-20)
- `product_id` INT UNSIGNED; `location_id` INT UNSIGNED, 0 on a site without stock locations; `bin` VARCHAR(40), the address as typed, trimmed; `updated_at` INT UNSIGNED.
- Keys: primary (`product_id`, `location_id`), as `product_stock`; (`location_id`, `bin`).

## Which columns does pick_waves have? (architect, 2026-11-20)
- `id` INT UNSIGNED AUTO_INCREMENT; `name` VARCHAR(100), '' shows the number; `location_id` INT UNSIGNED, 0 for any; `status` TINYINT UNSIGNED (0 open, 1 picked, 2 closed); `single_item` TINYINT UNSIGNED.
- `rule` TEXT NULL, the filter that made it as JSON, NULL when made by hand; `picked` TEXT NULL, the lines ticked on the phone as JSON.
- `created_by`, `created_at`, `closed_at` INT UNSIGNED.
- Keys: (`status`, `created_at`).

## Which columns does pick_wave_orders have? (architect, 2026-11-20)
- One row per address in a wave. `wave_id`, `ship_to_id`, `order_id` INT UNSIGNED; `ship_by` INT UNSIGNED, the ship-by day when it was added; `removed_at` INT UNSIGNED, set when a cancelled order left the wave, so the list can say so.
- Keys: primary (`wave_id`, `ship_to_id`); `ship_to_id`; `order_id`.
- "One open wave at most for an address" is the writer's check in its own INSERT; no key can hold it.

## Which columns does order_packs have? (architect, 2026-11-20)
- One row per address. `id` INT UNSIGNED AUTO_INCREMENT; `order_id`, `ship_to_id` INT UNSIGNED; `wave_id` INT UNSIGNED, 0 for none; `status` TINYINT UNSIGNED (0 open, 1 packed, 2 short); `packer_id` INT UNSIGNED.
- `started_at`, `packed_at`, `short_at` INT UNSIGNED; `units`, `scanned`, `refused` INT UNSIGNED (units wanted, good scans, refused scans).
- `scan_lines` TEXT NULL, units scanned per order line as JSON while open; `missing` TEXT NULL, a short pack's missing lines by product id as JSON.
- Architect (2026-11-21): scan_lines, since LINES is a reserved word in MySQL and MariaDB.
- Keys: unique (`ship_to_id`); `order_id`; (`status`, `packed_at`); (`packer_id`, `packed_at`); `wave_id`.
- A short pack finished later becomes `packed` on the same row and keeps `short_at`. No `pack_scans` table; one `audit_log` line when a pack closes.

## What is the right's column? (architect, 2026-11-20)
- `user.manage_packing` TINYINT UNSIGNED NOT NULL DEFAULT 0, unprefixed as `core/permission-columns.md` says. It decides for role 3; roles 0 to 2 and a user with the orders right have the screens already.
- It lets a role-3 user into the panel by itself, so PP1b teaches it to every role-3 ladder file, gives it its sign-in probe and draws it on the user screens; PP3 and PP4 gate their screens on it.
- With this right alone: the Pick waves and Pack screens only, no price, total or customer e-mail on them, no other Orders screen.

## What else holds?
- Every column NOT NULL with its default but the TEXT ones. InnoDB, utf8mb4_unicode_ci. No customer data in any of the four; an order's deletion removes its rows.
