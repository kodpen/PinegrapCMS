---
name: walk-in-queue-store
description: Walk-in queue tables waiting_places, waiting_desks, waiting_tickets (11.0.266, WQ1b): what each holds, states, the keep-period clear, erase, export, inventory, contact merge
module: walk_in_queue_store
---

## Which tables hold the queue?
- `waiting_places`, `waiting_desks`, `waiting_tickets`, made by `upgrade_2026_11_0_walk_in_queue()` (11.0.266) and listed in `get_tables()`. Names: owner poll 206, `workflow/decisions-made-schema-46.md`. `tests/walk_in_queue_store_test.php` pins every column and key. No column on an existing table.
- Columns: Architect (2026-11-21), the schema-47 decisions (#10957): `waiting_places` keyed by its id only (a few rows); `contact_id` without `user_id`; `priority` cleared with the personal fields; staff in the place's settings JSON and the lobby link as an HMAC, no columns; states as short text; no `pg_privacy_rules_catalogue()` entry (the place's keep period covers it).
- Until the step runs `pg_waiting_ready()` is false and nothing is read or written (`includes/fn/walk_in_queue_store.php`).

## What does a place hold?
- `name`, `address`, `status` (`closed` by default, `open`, `paused`), and JSON in TEXT NULL columns: `services` (each with its key, name, letter and starting minutes), `hours` and `exceptions` in the bookings' shapes (`pg_waiting_closes_at()` reads them), `settings` (daily cap, closed text, ways to take a number, notices, order rules, staff, `keep_days`).

## What does a desk hold?
- `place_id`, `name`, `services` (JSON list of service keys), `state` (`closed` by default, `open`, `paused`: the strings `pg_waiting_open_desks()` compares), `user_id` and `opened_at` of whoever has it open.

## What does a number hold?
- `place_id`, `service` (the service's key) and `letter` as it was when taken, `day` (YYYYMMDD in the site's zone) and `number` (the day's count); unique on place, day, service and number, so two numbers never get one count.
- `state`: `waiting` by default, `called`, `served`, `left` (left the queue), `no_show` (Not here the last time). `source`: how it was taken. `priority` is the priority group's key, '' for none.
- `booking_id` and `booking_at` (0 for a walk-in), `desk_id` and `staff_user_id` of the call, `moved_back`, `notices` (bits of the messages sent, at most three), times `joined_at`, `on_way_at`, `called_at`, `done_at`.
- The person: `name`, `phone` (as `pg_sms_number()` gives it, the form export and erase find numbers in), `email`, `contact_id`. `token_hash`: sha256 of the link token (`pg_waiting_ticket_token()`); '' opens nothing. No index: the link carries the number's id, the row is read by it and the hash only compared (`pg_waiting_ticket_token_valid()`) (as the booking manage link).

## When are a number's personal fields cleared? (W14)
- `pg_waiting_keep_clear_daily()` from `job.php`, once a day, at most 5 seconds a tick, 1,000 rows a statement: numbers that joined more than the place's keep period ago get `pg_waiting_clear_fields()` (name, phone, e-mail, contact, priority, link hash emptied) and `cleared_at`. The figures stay for the report.
- `pg_waiting_keep_days()`: settings `keep_days`, 30 by default, 1 to 365. A deleted place's numbers keep the default. The day of the last full run: `data/temp/waiting_clear.state`.

## What do erase, export, inventory and merge do?
- Erase entry `waiting_tickets`: found by `contact_id`, `email`, `phone`; anonymised with the same fields as the clear. `_pg_privacy_erase_apply_waiting_tickets()` first sets a waiting or called number to `left`.
- Export section `queue` ("Queue numbers", after `feedback`): the place's name and the number's own columns, never `token_hash` or the staff columns.
- Inventory line: identity and contact, customers and visitors, legitimate interest, retention `queue_keep` ("The queue place's keep period"), person column `contact_id`.
- `pg_contact_holders()`: `move`, `find`. Places and desks name nobody and are in no list.

Related: docs/memory/commerce/walk-in-queue-rules.md, docs/memory/plans/walk-in-queue.md
