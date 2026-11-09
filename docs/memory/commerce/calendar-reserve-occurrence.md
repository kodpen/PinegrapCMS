---
name: calendar-reserve-occurrence
description: Which occurrence of a calendar event a visitor may reserve through reserve_calendar_event.php, and why a posted number is checked
module: calendar_writes
---

## Which occurrence may a visitor reserve?
- `reserve_calendar_event.php` asks `pg_calendar_reserve_occurrence_ok()` (`includes/fn/calendar_writes.php`) before it adds the line.
- The posted `recurrence_number` is empty (the first occurrence) or digits; anything else is refused.
- A recurring event with separate spots per occurrence (`separate_reservations = 1`) takes only 0 up to its stored repeat count, and not a number in `calendar_event_exceptions` (an occurrence removed from the calendar).
- Shared spots and single events book number 0 whatever was posted, so only the digit rule applies.

## What did it fix?
- The number came from the hidden field of the event page (the link's `recurrence_number`) unchecked. A number past the last occurrence, or a removed one, had no `remaining_reservation_spots` row, so `get_calendar_event()` gave the initial spots: a full event could be booked again, and the visitor paid for a date that does not take place.

## Which page does the designed event page post?
- The calendar event view widget's reservation form posts `page_id` from `pg_sw_drawn_page_id()` (the page being drawn, home page included). `_pg_sw_current_page_id()` reads only `?page=`, so on the home page it posted 0 and `reserve_calendar_event.php` refused every reservation as a page that does not exist.
