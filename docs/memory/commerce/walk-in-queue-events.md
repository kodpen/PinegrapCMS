---
name: walk-in-queue-events
description: Walk-in queue events queue.joined, called, served, no_show (WQ8): where announced, payload, catalogues, kept on demand, who may start a flow on them
---

## Where is each event announced?
- `pg_announce()` in the queue's own functions, so the public page, the kiosk, the desk screen and the API all announce: `queue.joined` in `pg_waiting_take()` after the "taken" notice; `queue.called` in `pg_waiting_call_next()`; `queue.served` in `pg_waiting_finish()`; `queue.no_show` in `pg_waiting_not_here()` only when the number leaves. Step WQ8 of the walk-in-queue plan.
- Not announced: Call again, a Not here that moves the number back to wait again, Send to another service, Leave the queue, priority changes.

## What does an event carry?
- `pg_waiting_event_payload($ticket, $place, $desk)` (pure, `walk_in_queue_tickets.php`): id, place_id, place_name, service, service_name, number (A-012), state, source, priority, party_size, booking_id, desk_id, desk_name, staff_user_id, contact_id, name, phone, email, joined_at, called_at, done_at (ISO UTC, null for 0). Never the link's token. `pg_waiting_event_facts($id)` reads the number again after the move.
- Fields and samples: `$queue` in `api_webhook_event_payloads()`; `tests/webhook_event_payloads_test.php` runs the builder.

## Where are the events listed?
- `api_webhook_core_events()`, `pg_event_workspace_events()`, `ws_events_catalog()` (once `pg_waiting_ready()`, record contact, right member), `ws_flow_event_outputs()` (`$queue`), the Reservations area of `ws_flow_quick_areas()`, the parity table.
- Kept on demand (`pg_event_workspace_on_demand()`): a busy place makes hundreds a day, so a row is kept only while a flow names the event.
- A flow's creator must reach the place's desks (`ws_events_creator_sees()` through `pg_waiting_user_desk_scope()`): roles 0-2 every place, a role-3 user the places whose staff list names them. About the contact when `contact_id` is set, else no record.

## Does a served number get a satisfaction survey?
- Yes, when a survey "After a queue visit" is on: `queue.served` is a touchpoint of `pg_feedback_event_record()` (WQ8b, `platform/feedback-sends.md`).

Related: docs/memory/commerce/walk-in-queue-desk.md, docs/memory/api/api-walk-in-queue-routes.md, docs/memory/api/api-webhook-events.md
