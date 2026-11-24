---
name: volunteer-store
description: The five volunteer_* tables (11.0.270): JSON and DATETIME columns, NULL shift_id for outside hours, erasure, export, inventory, the left-volunteers rule
---

## Which tables hold volunteer shifts?
- `volunteer_programmes`, `volunteers`, `volunteer_documents`, `volunteer_shifts`, `volunteer_signups`, made by `upgrade_2026_11_0_volunteer_shifts()` (11.0.270) and listed in `get_tables()`. `tests/volunteer_store_test.php` pins every line of the five CREATE TABLE statements. Tables chosen by the architect, poll 206 (`workflow/decisions-made-schema-46.md`).
- Architect (2026-11-21, decision schema-47): the columns approved with changes; `volunteer_signups.minutes` and `volunteers.thanked_hours` are SMALLINT UNSIGNED.
- JSON on the rows: a programme's `form_fields`, `roles` (key => name, skills, trainings, document kinds), `coordinators` (a list of user ids), `texts`; a volunteer's `skills` and `hours` (the bookings' hours shape, `pg_booking_hours_clean()`); a shift's `roles` (key => count) and `called` (volunteer ids already called for it).
- States are words: programme `open`/`paused`; volunteer `applied`/`active`/`paused`/`left`; sign-up `state` `signed`/`waiting`/`cancelled`, `outcome` ''/`came`/`no_show`.

## Why DATETIME for a shift's times and the check-in?
- The rules compare the site's wall-clock times as written (`core/volunteer-shift-rules.md`), so `start_time`, `end_time`, `check_in`, `check_out` are `DATETIME NULL`; a document's `valid_until` and an hours row's `worked_on` are `DATE NULL`. Bookkeeping stamps (`created_at`, `approved_at` …) are unix INT as elsewhere.

## Why is shift_id NULL for hours outside a shift?
- Hours worked outside a shift are a sign-up row with no shift. NULL never collides, so the unique `(shift_id, volunteer_id)` key refuses a second sign-up of one volunteer on one shift and never an hours row.
- `link_hash` is NULL until a link is made, for the same reason (unique key). Only the sha256 of the token is stored (`pg_volunteer_link_token_hash()`).

## What does the personal data erasure do?
- Architect (2026-11-21, decision schema-47): erasure deletes the sign-up rows, so the person's hours leave the report; nothing is anonymised.
- `pg_privacy_erase_tables()`: `volunteer_signups` and `volunteer_documents` by `volunteer_id`, `volunteers` by `id` or `contact_id`, all deleted. Scope key `volunteer_ids` (the profiles of the person's contacts) is built before the files, so the documents' `file_id`s join `file_ids` and the `files` entry deletes them from the disk.
- `pg_contact_holders()`: `volunteers.contact_id`, merge `move` (both profiles stay), erase `find`.

## What does the export give?
- Sections `volunteers` ("Volunteering"), `volunteer_documents`, `volunteer_hours` after `feedback`: profiles with the programme's name, documents (kind, number, valid until, added), every shift and hours row with the shift's place and times. Never the file, `link_hash`, `seen_by` or the coordinators.

## Inventory and retention?
- Inventory lines for the three tables, subject `volunteer` ("Volunteers"), retention `left_volunteers` ("Rule: volunteers who left").
- Rule `volunteers` in `pg_privacy_rules_catalogue()`: profiles with `state = 'left'` and `left_at` before the period (3 years, 1-20, off by default; architect's yes, schema-47) go with their documents, files, shifts and hours (`pg_privacy_rule_one()`), the contact stays; no skip.
- The passive contacts rule counts a profile not left, or left within the period, as activity (`_pg_privacy_rule_active()`): a sign-up changes neither the contact nor an order.

Related: docs/memory/core/volunteer-document-file.md, docs/memory/core/volunteer-shift-rules.md, docs/memory/core/personal-data-erasure.md, docs/memory/core/privacy-retention-rules.md
