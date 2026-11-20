---
name: booking-buffers
description: Buffers between two bookings (#10805): the larger of the two sides counts, in either order; a Workspace event or holiday keeps only the new service's buffers
module: bookings
---

## How do the buffers of two bookings meet?
- Architect (2026-11-20): buffers do not add up; between two bookings the gap is at least max(the earlier one's `buffer_after`, the later one's `buffer_before`), whichever was booked first (#10805, #10846, channel message 11002). Why: a buffer is the gap between a booking and someone else's bare time; one gap serves both bookings.
- `pg_booking_busy_from_rows()` gives a row read with its service's `buffer_before`/`buffer_after` the range `[start, end, before, after]` (seconds).
- `pg_booking_overlaps()` keeps the slot's start max(its `buffer_before`, the range's after) from the range's end, and its end max(its `buffer_after`, the range's before) from the range's start.
- The widget (`pg_booking_busy_between()`), the hold (`pg_booking_insert()`) and the manage page's move (`pg_booking_manage_move()`) read bookings joined to `booking_services`; the last two through `pg_booking_rows_near()`, whose window widens by the larger buffer of either side.
- Bookings › New booking (`pg_booking_agenda_free_slots()`, `includes/fn/booking_agenda.php`) still reads bare rows: its join waits for the yearly-holiday PR of #10804 to land, as that file was held by it.
- Busy time that is not a booking (Workspace event, holiday, calendar) carries no buffers: only the new service's buffers count. Existing bookings are not moved.
