---
name: bug-hunt-event-tickets
description: Bug hunt of event tickets, a ticket's lifetime and the door (architect 2026-11-20, agent channel message 10668, #10754): what was read, the five faults fixed, the two issues, what was found clean
module: event_check_in, event_tickets, event_tickets_page
---

## What was the area, and what was read?
- Architect (2026-11-20, agent channel message 10668): event tickets, a ticket's lifetime and the door. Hunt PR #10754. The waiting list (`stock_notify`) was outside: #10712 hunted it, #10733 holds it.
- Read: `includes/fn/event_tickets.php` (rows, code, cancel, restore, delete, key, mail), `tickets.php` and `includes/fn/event_tickets_page.php`, `widgets_event_tickets.php`, `event_check_in.php`, `includes/fn/event_check_in.php`, `assets/js/event_check_in.js`, `includes/api/resources/event_tickets.php`, the calls in `delete_order.php`, `process_order_cancellation()`, `pg_order_restore_cancelled()`, `pg_order_complete_lines()`, the export, and the four `tests/event_*_test.php`.

## What was fixed?
- The door's pick list read 500 event dates in no order and dropped ended ones after the cap: on a site with many past dates a new event never showed. Now the dates sold last come first.
- A scan that read a ticket valid and lost its write always answered "Already checked in": also for a ticket voided or deleted meanwhile, and for a write that failed (a valid guest turned away). Now the row read again answers; a failed write answers "failed".
- The door, its name search and the API's `guest_name` read only the form's contact name fields, while the tickets page also reads a field named for the guest (`guest_name`, `ad_soyad`…): the door named the buyer. All now use `pg_event_ticket_guest_name()`.
- "Ticket for another event" named a ticket product by its short description alone: none for a product without one. Now its name as fallback.
- A code typed while a camera scan was answered was not sent, yet the field was emptied. Now it stays.
- Details: `commerce/event-tickets-check-in.md`, `commerce/event-tickets-api.md`.

## Which issues were opened?
- #10755: a repeating event with shared places books repeat 0, so its tickets show the first date, the door's pick drops after it, and a series place checks in once. Needs a decision on what such a ticket stands for.
- #10756: a refunded return of a ticket line leaves its tickets valid (gift card lines are not returnable, ticket lines are). Needs a decision: void the returned units, or no returns for ticket lines.

## What was found clean?
- Lifetime: one row per unit by `INSERT IGNORE` on line and unit, so a second completion makes none and mails none; cancel voids by one conditional UPDATE and keeps `used_at`, restore gives back used or valid; the only cancel and restore paths are `process_order_cancellation()` and `pg_order_restore_cancelled()` (the API and the Workspace call them); `delete_order.php` deletes the rows; `pg_local_order_discard()` deletes only incomplete orders.
- Code: 60 bits in Crockford base 32, only the hash stored, normalising O/I/L; the door needs no key.
- Holder's page: the token is checked before the order is read, every failure is the same 404, the throttle, the headers, no QR for a void or mismatched code, `.ics` only for a date the order holds a ticket for.
- Door: the token on every POST, rights per ticket before anything is shown, undo only for role 2 and above and only on the pick's ticket, `ticket.checked_in` only from this request's own write.
- API: named columns only, never `code_salt` or `code_hash`; counts before the state filter; cursor on the id.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/event-tickets.md
