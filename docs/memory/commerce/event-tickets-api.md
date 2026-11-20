---
name: event-tickets-api
description: Event tickets (TK5): GET /calendars/events/{id}/tickets (orders:read), ticket.checked_in, the product's "Gives tickets" switch, the personal data export's tickets
module: event_tickets
---

## What does GET /calendars/events/{id}/tickets answer?
- Route `calendars.event_tickets`, handler `api_calendar_event_tickets()` in `includes/api/resources/event_tickets.php`, scope `orders:read` (T14: a ticket reaches a person only through its order). The event follows the calendar row rule (`api_calendar_event_visible_or_fail()`, 404 outside); 503 `service_unavailable` before the tickets table.
- `data`: `EventTicket` rows oldest first, cursor on the ticket id; filters `recurrence_number`, `state` (valid, used, void). `guest_name` from the unit's product form, else the buyer's (`pg_check_in_with_names()`, one query a page).
- `counts` beside the page (`EventTicketCounts`): sold (valid and used), checked_in, void, for the event or the repeat asked, whatever the state filter.
- A key with only `calendars:*` is refused; an owner without the e-commerce right cannot give a key `orders:read` (`api_owner_scopes()`). No new scope or right.
- No product route: a product's tickets are read on the check-in screen only. A product ticket route would be a new endpoint for the architect.

## Is a ticket's code ever answered?
- No. The presenter `api_event_ticket_present()` names its fields one by one and the handler reads named columns, never `code_salt` or `code_hash`; the webhook and the export do the same. `tests/event_tickets_api_test.php` checks all three with a row that carries them.

## When is ticket.checked_in announced?
- From `event_check_in.php` when `pg_check_in_run()` answers `valid`, which it does only when this request's conditional UPDATE marked the ticket used: two phones scanning one ticket announce it once. An undone check-in and a second scan are not announced.
- Payload `pg_check_in_ticket_event()`: id, order_id, order_number, order_item_id, unit, product_id (null for none), calendar_event_id, recurrence_number, checked_in_at (UTC), checked_in_by. Fields and sample in `api_webhook_event_payloads()`; area Calendars in `api_app_event_area()`. Not a Workspace flow trigger.

## Where is a product marked "Gives tickets"?
- Product screen (`product_builder.php`), Advanced Settings, section "Event Tickets", switch "Gives tickets". Drawn only while Tickets is on in Site Settings and `products.gives_tickets` exists (`pg_pb_tickets_mark_drawn()`); not drawn, it posts nothing and the stored mark is kept.
- API: `gives_tickets` on the Product object; POST /products and POST /products/{id} take it (`api_products_tickets_param()`, 503 before the column).

## What does the personal data export list?
- Section `event_tickets` ("Event tickets", after `gift_cards`): the tickets of the person's orders, each with order id and number, unit, event name and date (`get_calendar_event()` of its repeat), product name, state and check-in time (`pg_event_tickets_export_row()`).
- Architect (2026-11-20): the export lists a person's tickets with their order, never the code or its hash (`workflow/decisions-made-schema-29.md`). Not erased: `event-tickets-key.md`.

Test: `tests/event_tickets_api_test.php`, `tests/privacy_export_test.php` (the pinned sections). Not run against a database.

Related: docs/memory/commerce/event-tickets-model.md, docs/memory/commerce/event-tickets-check-in.md, docs/memory/api/api-calendar-locations-reservations.md, docs/memory/core/personal-data-export.md
