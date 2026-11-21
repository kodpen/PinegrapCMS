---
name: volunteer-store
description: The five volunteer_* tables (11.0.266): JSON and DATETIME columns, NULL shift_id for outside hours, erasure, export, inventory, the left-volunteers rule, get_file.php's document door
---

## Which tables hold volunteer shifts?
- `volunteer_programmes`, `volunteers`, `volunteer_documents`, `volunteer_shifts`, `volunteer_signups`, made by `upgrade_2026_11_0_volunteer_shifts()` (11.0.266) and listed in `get_tables()`. `tests/volunteer_store_test.php` pins every line of the five CREATE TABLE statements. Tables chosen by the architect, poll 206 (`workflow/decisions-made-schema-46.md`).
- JSON on the rows: a programme's `form_fields`, `roles` (key => name, skills, trainings, document kinds), `coordinators` (a list of user ids), `texts`; a volunteer's `skills` and `hours` (the bookings' hours shape, `pg_booking_hours_clean()`); a shift's `roles` (key => count) and `called` (volunteer ids already called for it).
- States are words: programme `open`/`paused`; volunteer `applied`/`active`/`paused`/`left`; sign-up `state` `signed`/`waiting`/`cancelled`, `outcome` ''/`came`/`no_show`.

## Why DATETIME for a shift's times and the check-in?
- The rules compare the site's wall-clock times as written (`core/volunteer-shift-rules.md`), so `start_time`, `end_time`, `check_in`, `check_out` are `DATETIME NULL`; a document's `valid_until` and an hours row's `worked_on` are `DATE NULL`. Bookkeeping stamps (`created_at`, `approved_at` …) are unix INT as elsewhere.

## Why is shift_id NULL for hours outside a shift?
- Hours worked outside a shift are a sign-up row with no shift. NULL never collides, so the unique `(shift_id, volunteer_id)` key refuses a second sign-up of one volunteer on one shift and never an hours row.
- `link_hash` is NULL until a link is made, for the same reason (unique key). Only the sha256 of the token is stored (`pg_volunteer_link_token_hash()`).

## What does the personal data erasure do?
- `pg_privacy_erase_tables()`: `volunteer_signups` and `volunteer_documents` by `volunteer_id`, `volunteers` by `id` or `contact_id`, all deleted. Scope key `volunteer_ids` (the profiles of the person's contacts) is built before the files, so the documents' `file_id`s join `file_ids` and the `files` entry deletes them from the disk.
- `pg_contact_holders()`: `volunteers.contact_id`, merge `move` (both profiles stay), erase `find`.

## What does the export give?
- Sections `volunteers` ("Volunteering"), `volunteer_documents`, `volunteer_hours` after `feedback`: profiles with the programme's name, documents (kind, number, valid until, added), every shift and hours row with the shift's place and times. Never the file, `link_hash`, `seen_by` or the coordinators.

## Inventory and retention?
- Inventory lines for the three tables, subject `volunteer` ("Volunteers"), retention `left_volunteers` ("Rule: volunteers who left").
- Rule `volunteers` in `pg_privacy_rules_catalogue()`: profiles with `state = 'left'` and `left_at` before the period (3 years, 1-20, off by default) go with their documents, files, shifts and hours (`pg_privacy_rule_one()`), the contact stays; no skip.
- The passive contacts rule counts a profile not left, or left within the period, as activity (`_pg_privacy_rule_active()`): a sign-up changes neither the contact nor an order.

## Who opens a document's file?
- `get_file.php`, before the folder's check: an attachment a `volunteer_documents` row holds opens for roles 0-2, the programme's coordinators, the volunteer signed in (by contact) and the volunteer of the session's signed link (`$_SESSION['volunteer']['ids']`); `pg_volunteer_document_access_verdict()`.
- Anyone else: sign in, or 403; answered `private, no-store`; the form attachment check is skipped. A document file is written with `attachment = 1`, or the door is not asked.

Related: docs/memory/core/volunteer-shift-rules.md, docs/memory/plans/volunteer-shifts.md, docs/memory/core/personal-data-erasure.md, docs/memory/core/privacy-retention-rules.md
