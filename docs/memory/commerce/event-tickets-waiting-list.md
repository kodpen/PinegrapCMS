---
name: event-tickets-waiting-list
description: Event tickets TK4: a cancelled order's places back (tickets on), taken again on restore; the waiting list of a full date in stock_notify (11.0.248), its line, request, send and the product/event split
module: stock_notify
---

## When do places come back?
- `process_order_cancellation()` calls `pg_order_event_places_cancelled()` after the tickets are voided; `pg_order_restore_cancelled()` calls `pg_order_event_places_restored()` (`includes/fn/ecommerce.php`).
- Only with tickets on (`pg_event_tickets_on()`) and the calendars on; off, a cancel gives nothing back, as before (owner, agent channel poll 88).
- `pg_order_event_places()` (pure): reservation lines of an event with `reservations = 1` and `limit_reservations = 1`, quantities summed per date. The date is `pg_stock_notify_event_date()`: the occurrence for a recurring event with separate spots, else 0.
- Cancel: `number_of_remaining_spots + q`. Restore: down again, never below 0 (a place a waiting visitor took stays theirs). A date with no `remaining_reservation_spots` row is left alone: it reads the initial spots already.
- The cancel's guarded status write runs it once per cancellation. A switch turned off between cancel and restore leaves the places given back.

## Where is the waiting list kept?
- `stock_notify`, step 11.0.248: `calendar_event_id`, `recurrence_number` (INT UNSIGNED, default 0), `idx_event_sent (calendar_event_id, recurrence_number, sent_at)`. An event's request has `product_id = 0`; older rows are products'.
- Architect (2026-11-20, agent channel message 10143): the two columns and the index confirmed (owner's permission: poll 91); a deleted event's rows go with it; the export shows the event and its date.
- `pg_stock_notify_waiting_where()` is the only WHERE of waiting rows: a product pins its own id (never 0), an event pins `product_id = 0` and the event and date, so a product's notice never mails an event's requests, nor the reverse.
- Erasure by address or account, the trim and the account delete cover these rows. Deleting an event (Edit Event, the API, the Workspace) calls `pg_stock_notify_forget_event()`. The export adds `event_name` and `event_date` (the date's start, `pg_stock_notify_export_event_dates()`).

## Who may ask, and how?
- Only with `pg_stock_notify_event_on()`: tickets on, calendars on, the 11.0.248 columns there.
- `pg_stock_notify_event_state()`: `full` (limited and no place left, product sellable), `open`, `none` (no reservations, product gone or off, ended, product out of stock: that is the product's notice).
- The line, `pg_stock_notify_event_line()`: under the legacy event page's no-spots message, and as the designed page's `reserve_form` on a full date: "Tell me when a place frees", an address, "Tell me".
- It posts `waiting_list=1` to `reserve_calendar_event.php`, after all its page checks, before the cart: `pg_stock_notify_throttled()` (shared with products), then `pg_stock_notify_event_request()`; the answer returns in the `reserve_calendar_event` liveform.
- Answers: `added`, `already` (address or account waits for that date), `limit` (`PG_STOCK_NOTIFY_PENDING_LIMIT` across products and dates), `open`, `event`, `email`, `unavailable`.

## When does the mail go?
- `pg_stock_notify_event_freed()` after a cancel gave places back and when Edit Event raises Remaining Spots above what was stored (`edit_calendar_event.php`). The API and the Workspace do not call it.
- `pg_stock_notify_event_send()` reads the date again (must be `open`), then per waiting row: `pg_stock_notify_claim()` (conditional UPDATE) and only then the mail, so two frees at once mail once. Queued while the mail job runs (500), else 10 in the request.
- Mail only (no SMS, WhatsApp or push): the event and date, a link to the site, no place held.

Test: `tests/event_waiting_list_test.php` (the step run twice against stubs).

Related: docs/memory/commerce/event-tickets-model.md, docs/memory/commerce/back-in-stock-notices.md, docs/memory/plans/event-tickets.md
