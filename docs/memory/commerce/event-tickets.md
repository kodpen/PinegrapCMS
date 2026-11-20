---
name: event-tickets
description: Event tickets, start here: QR ticket per place, door check-in, places back on cancel, waiting list; which file holds what, T1 to T15 with PRs, decisions, what was left out
module: event_tickets
---

## What is it?
- One ticket per place of a completed order, with a secret code drawn as an inline QR; staff check it in at the door from a phone; a cancelled order voids its tickets and gives its places back; a full date takes a waiting list. Switch "Tickets" in Site Settings, Features, off by default.
- Two sources: a calendar event's reservation line, and a product marked "Gives tickets".

## Which file holds what?
- `event-tickets-model.md`: lines that give tickets, the row, the code and its hash, cancel and restore, the tickets mail, the switch (TK1, #10556).
- `event-tickets-key.md`: the tickets' own key and an order's deletion.
- `event-tickets-page.md`: `tickets.php` behind the signed link and My Account's tickets (TK2, #10583).
- `event-tickets-check-in.md`: `event_check_in.php`, its answers, rights, search, undo (TK3, #10587).
- `event-tickets-waiting-list.md`: places back on cancel and the waiting list in `stock_notify` (TK4, #10578).
- `event-tickets-api.md`: `GET /calendars/events/{id}/tickets`, `ticket.checked_in`, the product's mark, the export (TK5, #10643).

## Which feature is where? (checked against the code at the close-out)
- T1 lines that give tickets, one per unit, 100 a line: TK1; the product screen's "Gives tickets": TK5.
- T2 the row and the hashed code, T3 the switch, T4 the mail through the queue, T10 void on cancel and back on restore: TK1.
- T5 the tickets page with QR, name, place, "Add to calendar", Print; T6 a void ticket without QR: TK2.
- T7 the check-in screen with no new right and no library, T8 one conditional UPDATE and the answers, T9 counts, name search and undo: TK3.
- T11 places back with tickets on, T12 the waiting list in `stock_notify`, T13 the mail when places free (a cancel, or Remaining Spots raised on Edit Event), T15 a product's notice never mailing an event's requests: TK4.
- T14 the API route and `ticket.checked_in`: TK5. The API and the Workspace never raise an event's spots, so only Edit Event triggers a T13 mail.

## What did the owner decide?
- Owner (2026-11-16, agent channel poll 88, message 6517): a cancelled order's places return to the event, only with Tickets on; off, nothing returns, as before.
- Owner (2026-11-16, poll 91): the tickets table, the product's column and the waiting list's columns may be added, the columns approved by the architect.

## What did the architect decide?
- Architect (2026-11-16, agent channel message 6553): a tickets table, not the product form's record (it keeps no order line and the cart deletes a line's form rows); two phones scanning one ticket need one conditional UPDATE on one row. The waiting list is two columns and an index on `stock_notify`, no table, and no place is held: the first to order has it.
- Architect (2026-11-16, message 6554): no new right; an event's tickets with `validate_calendar_event_access()`, a product's with `validate_ecommerce_access()`.
- Architect (2026-11-20): a QR only when the redrawn code's hash equals `code_hash`; the export lists tickets without code or hash (`workflow/decisions-made-schema-29.md`). Columns: schema 29 (`event_tickets`, step 11.0.247) and 30 (`stock_notify`, step 11.0.248).

## What was left out?
- Seats and seat maps; a place held for the first waiting; a ticket handed on to a named guest; check-in without a connection; wallet passes; the Events template marking its products (templates paused). Each is a later yes.
- No menu entry for the check-in screen yet, and the help section `calendars#ticket-check-in` is still to write.

Related: docs/memory/commerce/calendar-reserve-occurrence.md, docs/memory/commerce/back-in-stock-notices.md, docs/memory/core/qr-code-generator.md, docs/memory/designer/design-template-events.md
