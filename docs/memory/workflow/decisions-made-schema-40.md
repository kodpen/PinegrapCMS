---
name: decisions-made-schema-40
description: Decisions list, schema part 40 (2026-11-20 on): customer_rhythm (one row per customer, poll 191) and shipment_problems (a problem found on a parcel, poll 192), their columns
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-39.md`.

## Does customer rhythm get a table? (owner, 2026-11-20, agent channel poll 191, channel message 10744)
- "Evet, tablo eklensin": `customer_rhythm`, one row per customer. No column is added to an existing table; the person's export and erase cover it.
- Why a table: a stage stays put, so the nightly pass announces each stage once and no reminder goes twice.
- Not chosen: no table, the report only (no stage event, reminder or flow trigger).
- The plan is the owner's yes to poll 176 (customer rhythm); step RY1b.

## Which columns does customer_rhythm have? (architect, 2026-11-20)
- `id` INT UNSIGNED AUTO_INCREMENT; `customer_key` VARCHAR(24) ascii, the value of `pg_customer_value_key()`; `contact_id` INT UNSIGNED, 0 without one.
- `orders` INT UNSIGNED; `first_order_at`, `last_order_at`, `last_order_id` INT UNSIGNED; `interval_days` SMALLINT UNSIGNED, the own interval as fixed at the last order; `avg_order` INT UNSIGNED, minor units of the base currency.
- `stage` TINYINT UNSIGNED (0 none, 1 Regular, 2 Due, 3 Late, 4 Slipping away, 5 Lost, 6 Won back); `stage_before` TINYINT UNSIGNED; `stage_at` INT UNSIGNED.
- `chance` TINYINT UNSIGNED, per cent; `expected_value` INT UNSIGNED, 12 months in minor units.
- `next_due_at` INT UNSIGNED, the earliest due day of a product in its window, 0 for none; `reminded_at` INT UNSIGNED, the last reorder reminder; `held_back` TINYINT UNSIGNED, 1 when that reminder was held back by the comparison share; `came_back_order_id` INT UNSIGNED, the order counted as coming back after it.
- `updated_at` INT UNSIGNED.
- Keys: unique (`customer_key`); `contact_id`; (`stage`, `stage_at`); `next_due_at`.
- No name, e-mail or address. A marketplace buyer gets no row. The month's counts of reminders and returns sit in the `config_text` summary.

## Does the delivery tower get a table? (owner, 2026-11-20, agent channel poll 192, channel message 10745)
- "Evet, tablo eklensin": `shipment_problems`, a problem found on a parcel. No column is added to `shipping_tracking_numbers`.
- Why a table: each problem of a parcel over time stays its own row, and the problem desk reads that record.
- Not chosen: problem columns on the parcel row (only the last problem kept); no table, the measures only.
- The plan is the owner's yes to poll 178 (delivery tower); step DT1b.

## Which columns does shipment_problems have? (architect, 2026-11-20)
- `id` INT UNSIGNED AUTO_INCREMENT; `shipment_id` INT UNSIGNED, the `shipping_tracking_numbers` row; `order_id`, `ship_to_id` INT UNSIGNED.
- `kind` TINYINT UNSIGNED (1 stuck, 2 not handed over, 3 delivery problem, 4 returned); `found_at` INT UNSIGNED.
- `assigned_to` INT UNSIGNED, a user, 0 for nobody; `note` VARCHAR(500).
- `closed_at` INT UNSIGNED, 0 while open; `closed_by` INT UNSIGNED, 0 when a later status closed it; `close_reason` TINYINT UNSIGNED (0 open, 1 settled by a status, 2 sent again, 3 refunded, 4 found, 5 other).
- `updated_at` INT UNSIGNED.
- Keys: unique (`shipment_id`, `kind`, `closed_at`), so a second open row of one kind on a parcel is refused and a pass can run again with `INSERT IGNORE`; (`closed_at`, `kind`); `order_id`; `found_at`.
- No name or address: the desk reads them from the order. An order's deletion removes its rows.

## What else holds for both?
- Every column NOT NULL with its default. InnoDB, utf8mb4_unicode_ci.
- The step's worker writes the columns into its PR body and asks the architect before it is marked ready.
