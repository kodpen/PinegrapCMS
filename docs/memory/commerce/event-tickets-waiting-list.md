---
name: event-tickets-waiting-list
description: Event tickets TK4: a cancelled order's places back (tickets on), taken again on restore; the waiting list of a full date in stock_notify (11.0.247), its line, request, send and the product/event split
module: stock_notify
---

## When do places come back?
- `process_order_cancellation()` calls `pg_order_event_places_cancelled()` after the tickets are voided; `pg_order_restore_cancelled()` calls `pg_order_event_places_restored()` (`includes/fn/ecommerce.php`).
- Only with tickets on (`pg_event_tickets_on()`) and the calendars on; off, a cancel gives nothing back, as before (owner, agent channel poll 88).
- `pg_order_event_places()` (pure): reservation lines of an event with `reservations = 1` and `limit_reservations = 1`, quantities summed per date. The date is `pg_stock_notify_event_date()`: the occurrence for a recurring event with separate spots, else 0.
- Cancel: `number_of_remaining_spots + q`. Restore: down again, never below 0 (a place a waiting visitor took stays theirs). A date with no `remaining_reservation_spots` row is left alone: it reads the initial spots already.
- The cancel's guarded status write runs it once per cancellation. A switch turned off between cancel and restore leaves the places given back.

## Where is the waiting list kept?
- `stock_notify`, step 11.0.247: `calendar_event_id`, `recurrence_number` (INT UNSIGNED, default 0) and `idx_event_sent (calendar_event_id, recurrence_number, sent_at)`. An event's request has `product_id = 0`; every older row is a product's.
- `pg_stock_notify_waiting_where()` is the only WHERE of waiting rows: a product pins its own id (never 0), an event pins `product_id = 0` and the event and date, so a product's notice never mails an event's requests, nor the reverse.
- Export (`stock_notify.*`), erasure by address or account, the daily trim and the account delete cover these rows as they cover a product's. A deleted event's rows are not removed; they never send (the event is gone) and the trim takes them in a year.

## Who may ask, and how?
- Only with `pg_stock_notify_event_on()`: tickets on, calendars on, the 11.0.247 columns there.
- `pg_stock_notify_event_state()`: `full` (limited and no place left, product sellable), `open`, `none` (no reservations, product gone or off, ended, product out of stock: that is the product's notice).
- The line: `pg_stock_notify_event_line()` under the no-spots message of the legacy event page and as the designed event page's `reserve_form` on a full date: "Tell me when a place frees", an address (the account's filled in), "Tell me".
- It posts to `reserve_calendar_event.php` with `waiting_list=1`, after the page, publish, calendar, occurrence, end and access checks, before the cart: rate limit `pg_stock_notify_throttled()` (shared with product requests), then `pg_stock_notify_event_request()`; the answer goes back through the `reserve_calendar_event` liveform.
- Answers: `added`, `already` (address or account waits for that date), `limit` (`PG_STOCK_NOTIFY_PENDING_LIMIT` across products and dates), `open`, `event`, `email`, `unavailable`.

## When does the mail go?
- `pg_stock_notify_event_freed()` after a cancel gave places back and when Edit Event raises Remaining Spots above what was stored (`edit_calendar_event.php`). The API and the Workspace do not call it.
- `pg_stock_notify_event_send()` reads the date again (must be `open`), then per waiting row: `pg_stock_notify_claim()` (conditional UPDATE) and only then the mail, so two frees at once mail once. Queued while the mail job runs (500), else 10 in the request.
- Mail only: no SMS, WhatsApp or device push. It names the event and date, links the site, says no place is held: whoever orders first has it.

Test: `tests/event_waiting_list_test.php` (the step run twice against stubs).

Related: docs/memory/commerce/event-tickets-model.md, docs/memory/commerce/back-in-stock-notices.md, docs/memory/plans/event-tickets.md
