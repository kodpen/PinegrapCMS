---
name: workspace-bookings
description: Confirmed bookings on a staff member's Workspace calendar (#6305, #6302): one reader, Work Calendar and Today, sync item ws-booking-<id>, refused move
module: booking_agenda
---

## Where do bookings show in the Workspace?
- One reader, `pg_booking_calendar_items($user_id, $from, $until)` (`includes/fn/booking_agenda.php`): the `confirmed` bookings of the `booking_staff` row of that panel user that overlap the range, with the service and customer as title and `edit_booking.php?id=N` as address. The shape is pure (`pg_booking_calendar_item_rows()`, tested).
- Work Calendar (`ws_calendar()`): only on the reader's own calendar (no person filter, or the reader); `ws_booking_calendar_events()` and `ws_booking_calendar_place()` (calendar.php) put them on the days as kind `booking`, `readonly`, with `url`; the day drawer opens the agenda. CSS `.ws-kind-booking` (green).
- Today (`ws_home_calendar()`): the same events among today's rows; the row's title links to the agenda.
- No `ws_events` row and no schema: the booking row is the one source of a booking's time (owner, #6302 option B).

## How do they reach Google or Outlook?
- `ws_calsync_bodies()` adds `ws_calsync_booking_feed_items()`: uid `ws-booking-<id>`, timed, busy (opaque / `showAs` busy), the agenda link; first in the list, so the feed limit cuts tasks before bookings. Cancelled, done and no-show bookings drop out and the sync deletes their event as it deletes any item no longer wanted.
- The outgoing iCal subscription (`workspace_calendar_feed.php`) does not carry bookings.

## What does a move made in the calendar do?
- Nothing to the booking: its time changes only on the agenda and the manage page. `ws_calsync_moves()` is called a second time with the bookings map; each move found drops the event's `written` hash, so the same run writes the booking's time back.
- `pg_booking_calendar_refused()` writes one panel bell row (`notifications`, action `booking_refused`, `target_user_id`, `reference_id` = booking id) "The move of booking #N in your calendar was not kept", opening `edit_booking.php?id=N`. Not a `ws_inbox` line: that table has no column to name a booking.

## What about busy times?
- `pg_booking_agenda_ws_busy()` is unchanged: bookings are already counted by the slot engine from their own table, so nothing is counted twice.

## What is left and unverified?
- No live Google or Graph call ran; the Work Calendar and Today were not opened in a browser (pure parts tested in `tests/workspace_bookings_calendar_test.php`).

Related: docs/memory/workspace/workspace-booking-record.md
