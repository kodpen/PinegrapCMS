---
name: order-returns-api
description: Returns over the external API: GET/POST /orders/{id}/returns, body, errors, what source api skips, the create check split and its repeat, the refund fields the object lacks
---

## Which routes are there?
- `GET /orders/{id}/returns` (`orders:read`): every return of the order, newest first, one page; empty before the tables exist. Presenter `api_order_return_present()`, schema `OrderReturn` (`includes/api/resources/orders.php`).
- `POST /orders/{id}/returns` (`orders:write`, rehearsable, 201 or 200): `items` [{order_item_id, quantity}] (shape and each line once judged by `api_order_returns_lines()`, the rest by the model), `reason` (enum from `pg_return_reasons()`), `note` (2000, needed with `other`), `ship_to_id` (optional while the goods went to one address), `notify`.
- Which lines belong to which delivery: each line of `GET /orders/{id}` carries `ship_to_id` (null: sent to no address, not returnable).
- Errors: 404 order; 409 `order_not_returnable`, `no_shipping_address` (no delivery holds lines), `return_in_progress` (lock busy); 422 `ship_to_id` (several deliveries, or not the order's), `reason`, `note`, `items`; 503 `service_unavailable` before the update.

## What does source api skip?
- Every customer timing rule, as for staff: the withdrawal window, goods that have left, a known delivery date. Staff decide (the panel shows whether it came in time); a delivery not yet sent is normally cancelled instead. The route description says so.

## How do the API and My Account meet one set of refusals?
- `pg_return_create_check()` holds every check of `pg_return_create()` in the same order (reason, note, order, status, delivery, customer timing, the same form sent again, the lines) and answers `field` and `code`; the API maps the code, never the sentence. `pg_return_create()` runs it, takes the lock and checks the repeat and the lines again; its refusals carry `field`/`code` too (`busy` for the lock).
- The check reads the repeat, then the held lines, outside the lock. A first request committed between the two reads made the second one "at most 0" (422 items) instead of a repeat, so before a line refusal stands the check looks for the repeat once more. `order_return.php` gave the same 14 answers before and after (sandbox).
- The handler stops for X-Dry-Run directly above `pg_return_create()`. A repeat (same owner, order, delivery, reason, note, lines within 60 s) answers 200 with that return, nothing else, rehearsal or not.

## What follows a new API request?
- `pg_return_notify_staff(id, 'requested')` and one activity line naming the application (both as My Account), `pg_audit('create', 'order_return', …)` over `pg_return_audit_create_fields()` (API only; My Account writes none), the request mail only with `notify` (My Account always mails).

## Where are the refund's amount and method?
- Only in `order.return.refunded` (`amount`, `refund_method`, `unclear`): `order_returns` has no column for them, so `OrderReturn` lacks them, against "no field readable in the event catalogue but missing on the object" (api/api-endpoint-rules.md). The GET description says so. Keeping them needs a column: the owner's call.

## Events and the Workspace?
- commerce/order-returns-events.md.

## Tests?
- `tests/returns_events_test.php`, `tests/api_order_returns_test.php`. Sandbox runs (customer path, API, panel, refunds with an iyzico stand-in, races, Workspace): PR #1352 body.
