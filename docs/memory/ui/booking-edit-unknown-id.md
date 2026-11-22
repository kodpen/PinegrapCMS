---
name: booking-edit-unknown-id
description: The booking screen (edit_booking.php) opens an existing booking for any id but 0; a negative or unknown id ends on Record not found (404), 0 or no id is a new booking
---

## Which id opens which booking form?
- `$booking_id` is the request's `id` cast to a whole number; a list, no id or `abc` reads as 0.
- 0 is a new booking: the Service and time card.
- Any other id is looked up with `pg_booking_agenda_scope_sql()`. No row, a negative id included, ends on "Record not found." with 404.

## Why?
- The lookup ran only for ids above zero, while the new-booking branches test `=== 0`. `?id=-1` drew the form of an existing booking with an empty row: 18 PHP warnings per load.
- PR #PRNUM; `tests/booking_agenda_test.php` (`test_booking_agenda_edit_loads_any_non_zero_id`).

Related: docs/memory/ui/booking-agenda.md
