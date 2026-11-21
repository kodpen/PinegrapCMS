---
name: event-tickets-series
description: Series tickets (#10755): a repeating event with shared places, one ticket for the series, entered once per date in event_ticket_checkins (11.0.268); page, .ics, API, export
module: event_tickets, event_check_in, event_tickets_page
---

## What is a series ticket?
- Owner (2026-11-20, poll 195, `workflow/decisions-made-schema-43.md`): a ticket of a repeating event whose dates share their places (`separate_reservations = 0`) is good for the whole series and is scanned once on each date.
- `pg_event_ticket_is_series()` (pure): `recurrence = 1`, a known repeat type, separate off, repeat count above 0. Its dates are 0 to the repeat count minus `calendar_event_exceptions` (`pg_event_ticket_series_numbers()`); `pg_event_ticket_series()` reads them once a request.
- The ticket row keeps `recurrence_number` 0, as the reservation writes it, and its `state` stays `valid`; `used_at`/`used_by` are never written. Cancel, restore and a refunded return act on `state` as for any ticket.
- Before the table exists (`pg_event_ticket_checkins_ready()`) nothing is a series: the door reads date 0 as before.

## What is the table?
- `event_ticket_checkins` (step 11.0.268, architect 2026-11-20): `id`, `ticket_id`, `calendar_event_id`, `recurrence_number` (the date entered), `checked_at`, `checked_by`; all INT UNSIGNED NOT NULL DEFAULT 0. Unique (`ticket_id`, `recurrence_number`); key (`calendar_event_id`, `recurrence_number`). In `get_tables()`.
- An order's deletion removes the entries first, then the tickets (`pg_event_tickets_delete_for_order()`).

## How does the door treat it?
- Picks: a series ticket's group (event, 0) lists each date of the series, each until a day after its own end; the first open date is found by halving (`pg_check_in_series_first_open()`), then at most 60 dates (`PG_CHECK_IN_SERIES_PICKS`). Values stay `e-<event>-<date>`.
- `pg_check_in_series_overlay()` reads the ticket on the pick's date (`pg_check_in_series_state()`): `series_dates`, `series_date`, and used with the entry's time and checker when entered, else valid; a void row stays void on every date.
- Entry: `INSERT IGNORE INTO event_ticket_checkins … SELECT … FROM event_tickets WHERE id = ? AND state = 'valid'`; one row changed is "Valid ticket", none is read again ("Already checked in", void, unknown). Undo deletes that date's row only.
- Counts of a series date: tickets on date 0 not void, used when entered that date (`pg_check_in_counts_sql()`). The name search reads the series tickets too.
- `ticket.checked_in` carries the date entered in `recurrence_number`.

## What do the page, the API and the export show?
- `tickets.php`: the card's date is the first and last date with the times of the first (`pg_event_ticket_series_when()`); "Add to calendar" (`&ics=<event>-0`) holds every date, each its own VEVENT and UID (`pg_event_tickets_ics_merge()`, 500 dates at most).
- `GET /calendars/events/{id}/tickets`: each ticket has `check_ins` (recurrence_number, checked_in_at, checked_in_by; empty for other tickets). `recurrence_number=<date>` on a series reads its date-0 tickets, and `counts.checked_in` counts that date's entries (any date when none is asked).
- Personal data export: each ticket's `check_ins` (event_date, checked_in_at).

## Tests?
- `tests/event_ticket_series_test.php`; `event_tickets_test.php` (the delete order), `event_tickets_api_test.php` (the export's fields). Not run on a database or a phone when written.

Related: [[event-tickets]], [[event-tickets-check-in]], [[event-tickets-page]], [[event-tickets-api]]
