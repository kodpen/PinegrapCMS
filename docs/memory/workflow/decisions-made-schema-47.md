---
name: decisions-made-schema-47
description: Decisions list, schema part 47 (2026-11-21 on): the agreed columns and keys of the walk-in queue's three tables (WQ1b) and the volunteer shifts' five tables (VS1b)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-46.md` (the tables themselves, owner's poll 206).

## Which columns do the walk-in queue's tables have? (architect, 2026-11-21, #10956)
- `waiting_places`: name, address, `status` (open, closed, paused), `services`, `hours`, `exceptions` and `settings` as JSON text, created_by and times. No index on `status`.
- `waiting_desks`: place_id, name, `services` (JSON keys), `state`, user_id, opened_at, times; keys on place and user.
- `waiting_tickets`: place_id, service key, letter, `day` (YYYYMMDD), number, `state` (waiting, called, served, left, no_show), source, `priority`, party_size, booking_id and booking_at, desk_id, staff_user_id, moved_back, notices (bits), joined_at, on_way_at, called_at, done_at, name, phone, email, contact_id, `token_hash`, cleared_at. Unique on place, day, service and number.
- `token_hash` gets an index only if the status page finds the row by the token alone.
- States are short text, not number codes. A ticket names a contact, never a user. Staff sit in the place's `settings`; the lobby screen's link is signed.

## What does the walk-in queue do with personal data? (architect, 2026-11-21)
- The keep period is per place (`keep_days` in `settings`), shown in the inventory; no entry in the retention rules catalogue.
- The tickets register with the other privacy lists (export, erasure, inventory, contact merge) and `pg_contact_holders()` in the table step.
- `priority` is cleared with the personal fields: its values (pregnant, disabled) are close to health data.

## Which columns do the volunteer tables have? (architect, 2026-11-21, #10949)
- `volunteer_programmes`: name, state, page_id, `form_fields`, `roles`, `coordinators` and `texts` as JSON text, cutoff_hours, min_age, swap_approval, call_days, call_channels, created_by, times.
- `volunteers`: programme_id, contact_id, form_id, `state` (applied, active, paused, left), skills, hours, calls, call_channel, `link_hash` (unique, NULL when none), link_issued_at, thanked_hours, applied_at, approved_at, approved_by, left_at. No unique key on programme and contact, so a contact merge cannot clash; the code keeps one profile.
- `volunteer_documents`: volunteer_id, kind, number, valid_until, file_id, seen_by, seen_at, notice_sent, added_by, times.
- `volunteer_shifts`: programme_id, place, start_time and end_time (DATETIME), roles, note, series_id, called, reminded_at, created_by, times.
- `volunteer_signups`: programme_id, `shift_id` (NULL for hours outside a shift), volunteer_id, role, `state` (signed, waiting, cancelled), outcome, offered_at, swap_from, check_in, check_out, checked_by, worked_on, minutes, note, approval and cancel times. Unique on shift and volunteer; the NULL lets rows without a shift stand side by side.
- `minutes` and `thanked_hours` are unsigned.

## How are a volunteer's document file and erasure handled? (architect, 2026-11-21)
- The file is a `files` row with `attachment = 1`, `folder = 0` and a `volunteer-doc-` name, not a folder of its own (roles 0 to 2 would see that in the File Manager). `get_file.php` serves it to roles 0 to 2, the programme's coordinators and the volunteer.
- The step that opens the upload also keeps these rows out of search and the file pickers; no upload before that.
- Erasure deletes the person's sign-up rows and their hours leave the reports. Not chosen: keeping them with volunteer 0, which would clash on the unique key within one shift.
- Rule for volunteers who left: three years after leaving, default off.

## Which migration label do two table steps open at once take?
- The first to go ready keeps its label; the other stays a draft until that one lands, then takes the next free label from `development` and goes ready.
