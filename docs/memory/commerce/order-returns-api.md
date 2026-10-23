---
name: order-returns-api
description: Returns over the external API (GET/POST /orders/{id}/returns), the order.return.* webhooks and where each is announced, the Workspace trigger order.return.requested, the create check split
---

## Which routes are there?
- `GET /orders/{id}/returns` (`orders:read`): every return of the order, newest first, one page; empty before the tables exist. Presenter `api_order_return_present()`, schema `OrderReturn` (`includes/api/resources/orders.php`).
- `POST /orders/{id}/returns` (`orders:write`, rehearsable, 201 or 200): `items` [{order_item_id, quantity}] (shape judged by `api_order_returns_lines()`, the rest by the model), `reason` (enum from `pg_return_reasons()`), `note` (2000, needed with `other`), `ship_to_id` (optional while the goods went to one address), `notify`.
- Source `api`, `requested_by` the application's owner. No withdrawal-window check: like staff, the shop decides; the panel shows whether it came in time.
- Errors: 404 order; 409 `order_not_returnable`, `no_shipping_address` (no delivery holds lines), `return_in_progress` (lock busy); 422 `ship_to_id` (several deliveries, or not the order's), `reason`, `note`, `items`; 503 `service_unavailable` before the update.

## How do the API and My Account meet one set of refusals?
- `pg_return_create_check()` holds every check of `pg_return_create()` in the same order (reason, note, order, status, delivery, customer timing, the same form sent again, the lines) and answers `field` and `code`; the API maps the code, never the sentence. `pg_return_create()` runs it, takes the lock and checks the repeat and the lines again; its refusals carry `field`/`code` too (`busy` for the lock).
- The repeat is looked for before the lines in the check as well: otherwise a double click whose first request took every unit was refused with "at most 0" before the lock. `order_return.php` gave the same 14 answers before and after (sandbox).
- The handler stops for X-Dry-Run directly above `pg_return_create()`. A repeat (same owner, order, delivery, reason, note, lines within 60 s) answers 200 with that return, nothing else, rehearsal or not.

## What follows a new API request?
- `pg_return_notify_staff(id, 'requested')`, one activity line naming the application, `pg_audit('create', 'order_return', …)` over `pg_return_audit_create_fields()`, the request mail only with `notify`.

## Which events are announced, and from where?
- `pg_return_announce()` → `pg_announce()` with `pg_return_event_payload()`: `id` is the ORDER id (every `order.*` event; `ws_events_record_of()` reads it), `order_number`, `return_id`, `ship_to_id`, `status` after the move, `source`, `reason`, `items` [{order_item_id, quantity}].
- `order.return.requested`: `pg_return_create()` after COMMIT and RELEASE_LOCK, never on a repeat. `order.return.approved` / `.received`: `pg_return_move()` after its conditional UPDATE, so one screen and each request of a bulk approval announce.
- `order.return.refunded`: `pg_return_refund()` after its lock, with `amount`, `refund_method` (driver, manual) and `unclear`. Not from `pg_return_move()`: the refund moves to refunded before the service is asked and reverts on a refusal. Unclear answers announce with `unclear: true` (return stays refunded, commerce/payment-unclear-answers.md).
- Rejected, shipped, closed and a withdrawal are not announced; the catalogue lines say so.

## What does the Workspace get?
- `order.return.requested` is in `pg_event_workspace_events()` and `ws_events_catalog()` (shop block, record order, right ecommerce, no filter): a scheduled action can start on it; the placeholders are the order's. Not in `ws_events_watched()`: watching channels are not told.

## Tests?
- `tests/returns_events_test.php`, `tests/api_order_returns_test.php`. Sandbox runs (customer path, API, panel, refunds with an iyzico stand-in, races, Workspace): PR body.
