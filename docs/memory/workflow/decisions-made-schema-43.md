---
name: decisions-made-schema-43
description: Decisions list, schema part 43 (2026-11-20 on): a repeating event with shared places gives a series ticket checked in once per date; the event_ticket_checkins table (poll 195, bug #10755)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-42.md`.

## What does a ticket of a repeating event with shared places stand for? (owner, 2026-11-20, agent channel poll 195, channel message 10784)
- "Seri bileti, her tarihte ayrı giriş": the ticket is good for the whole series and shows its first and last date; at the door it is scanned once for each date. A small table keeps the entries: `event_ticket_checkins` (ticket, date, when, who).
- The symptom (bug #10755, found by the hunt #10754): such an event (a 10-week course, places not separate per date) wrote its ticket for the first date only, and from the second week on the event could not be chosen at the door.
- Not chosen: a series ticket with one entry kept (later dates show it valid with the first entry's day, no table); no ticket for such an event.

## Which columns does event_ticket_checkins have? (architect, 2026-11-20)
- `id` INT UNSIGNED AUTO_INCREMENT; `ticket_id` INT UNSIGNED, the `event_tickets` row; `calendar_event_id` INT UNSIGNED; `recurrence_number` INT UNSIGNED, the date entered; `checked_at`, `checked_by` INT UNSIGNED.
- Keys: unique (`ticket_id`, `recurrence_number`); (`calendar_event_id`, `recurrence_number`), the door's counts.
- Every column NOT NULL DEFAULT 0. InnoDB, utf8mb4_unicode_ci.

## How does a series ticket behave? (architect, 2026-11-20, channel message 10806)
- Its `event_tickets` row keeps `recurrence_number` 0, as the reservation writes it; a series ticket is one whose event repeats with `separate_reservations = 0`.
- The door lists each date of the series, each until a day after its own end. An entry is one `INSERT IGNORE`; a second scan on the same date answers "Already checked in"; undo deletes that date's row.
- The row's `state` stays `valid` and `used_at`, `used_by` are not written; a void ticket is refused on every date, and cancel, restore and a refunded return act on `state` as for any ticket.
- The ticket page shows the first and last date and its calendar file carries every date.
- The personal data export, `GET /calendars/events/{id}/tickets` and `ticket.checked_in` carry the date of the entry. An order's deletion removes the entries with its tickets.
- A single event, an event with places separate per date and a product's ticket stay as they are, on `event_tickets.state`.

## Who builds it?
- One worker, one PR, `Fixes #10755`, after #10754 and #10773 have landed (they hold `event_check_in.php`, `event_tickets_page.php` and `event_tickets.php`); red test first.
- The worker writes the columns into its PR body and asks the architect before it is marked ready.
