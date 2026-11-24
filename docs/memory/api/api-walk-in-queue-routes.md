---
name: api-walk-in-queue-routes
description: Walk-in queue API routes (WQ8): places, a day's numbers, kiosk join, Call next at a desk, Finish; scope queue, role-3 rows, 409 codes, the link given once
---

## Which routes are there?
- All in `includes/api/resources/queue.php` (WQ8 of the walk-in-queue plan), tag Queue. 503 `service_unavailable` before the queue tables.
- `queue.places` GET /queue/places (`queue:read`, `QueuePlace` list, one page): status, services (key, name, letter), desks (id, name, services, state, user_id), waiting and today.
- `queue.tickets` GET /queue/tickets (`queue:read`, `QueueTicket` list): `place_id` required, `day` YYYY-MM-DD (today), `state`, cursor on the id in taking order (not the calling order).
- `queue.tickets.create` POST /queue/tickets (`queue:write`, dry run, 201 `QueueTicketJoined`: ticket, link, ahead): `source` kiosk (the place's "At the kiosk" switch) or staff; read by `pg_waiting_join_input()` with priority asked; taken by `pg_waiting_take()`. 409 `queue_closed` (place or way closed), 409 `join_refused` (hours, cap, closing; message `pg_waiting_refusal_text()`).
- The link is given only here: the token is not kept. Way, hours, cap, closing and booking are `pg_waiting_take_check()`, run before the dry-run stop, so a rehearsal meets the same 409 as the take.
- `queue.desks.call` POST /queue/desks/{id}/call (`queue:write`, dry run): `pg_waiting_call_next()` for the user who has the desk open. A role-3 key works only at the desk its owner has open, as the desk screen: Call next and Finish at another desk are 403 `forbidden` (`api_queue_desk_reaches()`). 409 `desk_not_open`, `desk_busy`, `nobody_waiting`. Opening a desk stays on the desk screen.
- `queue.tickets.finish` POST /queue/tickets/{id}/finish (`queue:write`, dry run): `pg_waiting_finish()`; 409 `ticket_state` unless called.

## Who reaches which rows?
- Scope group `queue` (Queue, area store): roles 0-2 in `api_owner_scopes()`, a role-3 user only while a place's staff list names them; the handlers narrow by `pg_waiting_user_desk_scope()`, another place's place, desk or number is 404.
- Not in `api_plugin_writes()`: an assistant plugin reads the queue only.

## What does a number show?
- `api_queue_ticket_present()`: the event payload (`commerce/walk-in-queue-events.md`) with null for empty, plus day, on_way_at, moved_back. Never the token hash or the notice bits.
- Each route is an MCP tool by itself (`queue_places`, `queue_tickets`, `queue_tickets_create`, `queue_desks_call`, `queue_tickets_finish`).

## How is it tested?
- `tests/api_queue_test.php` (presenters against their schemas, the day read, route rows, scope ceiling, the payload). Not run against a database.

Related: docs/memory/commerce/walk-in-queue-desk.md, docs/memory/commerce/walk-in-queue-number-page.md
