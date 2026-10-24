---
name: order-returns-api
description: Returns over the external API: GET/POST /orders/{id}/returns, body, errors, what source api skips, the create check split and its repeat, where the refund's amount and method are
---

## Which routes are there?
- `GET /orders/{id}/returns` (`orders:read`): every return of the order, newest first, one page; empty before the tables exist. Presenter `api_order_return_present()`, schema `OrderReturn` (`includes/api/resources/orders.php`).
- `POST /orders/{id}/returns` (`orders:write`, rehearsable, 201 or 200): `items` [{order_item_id, quantity}] (shape and each line once judged by `api_order_returns_lines()`, the rest by the model), `reason` (enum from `pg_return_reasons()`), `note` (2000, needed with `other`), `ship_to_id` (optional while the goods went to one address), `notify`, `customer_rules`.
- Which lines belong to which delivery: each line of `GET /orders/{id}` carries `ship_to_id` (null: sent to no address, not returnable).
- Errors: 404 order; 409 `order_not_returnable`, `no_shipping_address` (no delivery holds lines), `return_in_progress` (lock busy), with `customer_rules` `not_sent`, `delivery_date_unknown`, `return_period_ended`; 422 `ship_to_id` (several deliveries, or not the order's), `reason`, `note`, `items`; 503 `service_unavailable` before the update.

## What does source api skip?
- By default every customer timing rule, as for staff (staff decide; an unsent delivery is normally cancelled instead); `customer_rules: true` holds the request to them. Both, and `OrderReturn.withdrawal` (did it come in time): order-returns-api-timing.md.

## How do the API and My Account meet one set of refusals?
- `pg_return_create_check()` holds every check of `pg_return_create()` in the same order (reason, note, order, status, delivery, customer timing, the same form sent again, the lines) and answers `field` and `code`; the API maps the code, never the sentence. `pg_return_create()` runs it, takes the lock and checks the repeat and the lines again; its refusals carry `field`/`code` too (`busy` for the lock).
- The check reads the repeat, then the held lines, outside the lock. A first request committed between the two reads made the second one "at most 0" (422 items) instead of a repeat, so before a line refusal stands the check looks for the repeat once more. `order_return.php` gave the same 14 answers before and after (sandbox).
- The handler stops for X-Dry-Run directly above `pg_return_create()`. A repeat (same owner, order, delivery, reason, note, lines within 60 s) answers 200 with that return, nothing else, rehearsal or not.

## What follows a new API request?
- `pg_return_notify_staff(id, 'requested')` and one activity line naming the application (both as My Account), `pg_audit('create', 'order_return', …)` over `pg_return_audit_create_fields()` (API only; My Account writes none), the request mail only with `notify` (My Account always mails).

## Where are the refund's amount and method?
- On the return since the owner's decision of 2026-10-24 (step 11.0.34, backfilled from the audit trail and `order_refunds`): `OrderReturn.refund` {amount, method, reference, unclear}, null before the refund, the values `order.return.refunded` carries: order-returns-refund-columns.md.

## Events and the Workspace?
- commerce/order-returns-events.md.

## Tests?
- `tests/returns_events_test.php`, `tests/api_order_returns_test.php`. Sandbox runs (customer path, API, panel, refunds with an iyzico stand-in, races, Workspace): PR #1352 body.
