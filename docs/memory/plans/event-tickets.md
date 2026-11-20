---
name: event-tickets
description: Plan (owner 2026-11-16, agent channel poll 82): a ticket with a QR code per place of an order, check-in at the door from a phone, and a waiting list when an event is full; steps TK1 to TK6
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): a ticket with a QR code, scanning at the door, a waiting list.

## Which event store is used?
- Calendar events and their reservations: a reservation is an order line with the event and its date (`reserve_calendar_event.php`), the line's quantity is the number of people, and the event's remaining spots are lowered when the order is completed (`pg_order_complete_line_reservation()`, `commerce/calendar-reserve-occurrence.md`).
- A product marked "Gives tickets" is the second source: the Events template sells its tickets as plain products and keeps registrations as records (`designer/design-template-events.md`), and it must not be left out.

## What is there today, and what is reused?
- One row per unit made at completion exists for gift cards (`pg_order_complete_line_gift_cards()`). The order's confirmation is written before the lines are completed.
- A cancelled order's rights are taken back beside `pg_order_folder_access_cancelled()`.
- A cancel or refund never gives an event's spots back, and nothing holds a spot before payment (`api/api-calendar-locations-reservations.md`).
- The QR maker is `includes/fn/qr.php`; a secret QR is inline SVG only (`core/qr-code-generator.md`). Nothing in the product uses the camera yet; the site's permissions policy lets its own pages ask for it.
- A request for a product that is out of stock is kept in `stock_notify` and mailed when stock returns (`commerce/back-in-stock-notices.md`).

## What binds it?
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.
- `designer/design-template-events.md` left seats, session collections and QR check-in out as new product behaviour: this plan is that behaviour, without seats.

## What is built?
- The numbered features: `event-tickets-features.md` (T1 to T15). Steps: `event-tickets-steps.md`. Claim as `[plan:event-tickets] <step> — <topic>`.

## What did the owner decide? (2026-11-16)
- Whether a cancelled order's places return to the event (T11). Owner (agent channel poll 88, message 6517): "Evet, yerler geri dönsün".

## What did the architect decide? (2026-11-16)
- Architect (agent channel message 6553), after the owner asked that what exists be grown instead of a table (poll 84): the tickets table stays, with one column on the product for "Gives tickets". A product's form record cannot be the ticket: it does not keep its order line, and the cart deletes the line's own form rows.
- Two phones scanning one ticket need one conditional UPDATE on one row; a field's value, which staff can edit, does not give that. Used from what exists: the order line, the product form per unit for a guest's name, the QR maker.
- The waiting list has no table: two columns and an index on `stock_notify`. Holding a place left the first version: a freed place mails those waiting and the first to order has it.
- Architect (message 6554): no new right. An event's tickets are checked in by whoever may manage that calendar (`validate_calendar_event_access()`), a product's by whoever manages commerce (`validate_ecommerce_access()`, `core/access-control-and-roles.md`).
- Columns: TK1 `workflow/decisions-made-schema-29.md`, TK4 in its draft PR.

## What is left out?
- Seats and seat maps; a place held for the first in the waiting list; a ticket per named guest that can be handed on; check-in without a connection; passes for a phone's wallet; the Events template marking its products (templates are paused). Each is a later yes.

## Whose look?
- The owner's: the tickets page (TK2), the check-in screen on a phone (TK3), the waiting list's line on the reserve page (TK4).
