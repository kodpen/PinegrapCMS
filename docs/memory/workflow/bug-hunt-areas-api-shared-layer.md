---
name: bug-hunt-areas-api-shared-layer
description: The bug hunt of the external API's shared layer (fault, manners, response, router, preconditions, idempotency; channel message 10241, area c, PR #10615): three faults fixed, what was read clean
---

## What was the area? (channel message 10241, area c)
- The shared layer of the external API built by plan api-clean-modern AM3–AM10 (closed by #10464): `includes/api/fault.php`, `manners.php`, `response.php`, `router.php`, `preconditions.php`, `idempotency.php`.
- Not touched: `includes/api/schema.php`, `resources/commissions.php`, `resources/calendars.php`, `includes/api/outbound/` (held by TK2, TK3, TK4).

## What did PR #10615 fix?
- A failed query wrote no error log line outside DEBUG: the client's request id led to nothing. Now `api_query()` logs the database's message (`api/api-fault-envelope.md`).
- A query parameter sent in the body (`DELETE /products/12` with `{"permanent": true}`) was refused as `unknown_field`, though the route takes it (`api/api-field-errors.md`).
- A 401 to a Bearer token carried no `WWW-Authenticate` while OAuth was off (`api/api-http-manners.md`).

## What was read and found clean?
- The fault guard and shutdown path, HEAD (status, ETag, `Content-Length`, no body), Location over every 201 route (no record points at another type's address), Content-Language, the If-Match read and its restore, the reservation, replay, stale take-over and release of Idempotency-Key.
- Every DELETE and its `_post` twin: same handler, scope, dry run, answer and status. Differences: the twin takes `permanent`/`force`/`mode` as body fields (the router reads either), and `gift_cards.delete_post` lacks the `moves_money` mark (a marker for checks only; the scope is the same, so no check misses it).
- `POST /orders` answers 201 with no Location: its answer has no top-level `id` (`order.id`), by the rule of `api-http-manners.md`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-4.md
