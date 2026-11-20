---
name: event-tickets-returns
description: Event tickets and returns (#10756): a refund voids the returned units' tickets, unchecked first from the highest unit, gives the places back, tells the waiting list; restore keeps them void
module: event_tickets
---

## May a ticket line be returned?
- Yes. A calendar event's reservation line or a "Gives tickets" product line is returnable like any line (unlike a gift card line). Architect (2026-11-20, agent channel message 10785): kept, and the refund voids the returned units' tickets, for #10756.

## When are a return's tickets voided?
- Only when the return moves to refunded: `pg_return_refund()` calls `pg_event_tickets_return_refunded($order_id, $returned_quantities)` next to the folder access take-back, after the payment's outcome stands. A refused driver refund goes back to received and voids nothing; an unclear one stays refunded and voids.
- Approved, shipped and received leave the tickets alone (`pg_event_tickets_return_voids()` is true only for `refunded`).
- A return put back after its refund never gives its tickets back.
- Tickets are voided whatever the "Tickets" switch says, as a cancel voids them.

## Which tickets of the line?
- `pg_event_tickets_return_pick()` (pure): as many as the return took of that line, from the line's tickets that are not void; first those not checked in (`valid`), highest unit first; a checked-in (`used`) one only when no valid one is left, highest unit first. More returned than tickets voids all there are.
- `pg_event_tickets_lines_refunded()` writes them: one `UPDATE … SET state = 'void'` per line on the picked ids, `state <> 'void'` kept in the WHERE.

## Are the places given back?
- As on a cancel (`event-tickets-waiting-list.md`): only with tickets on, the calendars on and an event that takes reservations and limits its places. `pg_event_tickets_return_place_lines()` (pure) sets each returned reservation line's quantity to the units returned (never more than the line), then `pg_order_event_places()` and `pg_order_event_places_sql('give', …)`; the dates that moved go to `pg_stock_notify_event_freed()`.

## What does a restore of a cancelled order do with them?
- `pg_event_tickets_order_restored()` gives back every void ticket, then voids again, by the same pick, as many per line as the order's refunded returns took (`pg_event_tickets_returns_reapply()`), so a refunded unit stays void.

## What is left open?
- A cancel after a refunded return gives back the line's whole quantity of places, and a restore takes the whole quantity again: `pg_order_event_places_move()` (`includes/fn/ecommerce.php`) does not subtract refunded returned units yet. Written in #10756's PR under "After #10759".

Test: `tests/event_tickets_returns_test.php`. The database writes (void, places, restore re-void) were not run on a database.

Related: docs/memory/commerce/event-tickets-model.md, docs/memory/commerce/event-tickets-waiting-list.md, docs/memory/commerce/order-returns-refund.md
