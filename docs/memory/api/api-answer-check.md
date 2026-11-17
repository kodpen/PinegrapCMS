---
name: api-answer-check
description: tools/check_api_answers.php, the read-only check that calls every GET route of a running site and compares each answer with the site's OpenAPI document; how it is run, what it flags and skips
---

## What does it check?
- It reads the site's own `GET /openapi.json`, then calls every GET route: lists first, then each record route with the first id of its list (`/orders/{id}/items/{item_id}` walks `/orders`, then `/orders/9/items`).
- Each answer against its 200 schema: the status, JSON at all, required fields, types, `date-time` / `date` formats, enums, null only where `nullable`, fields the document does not declare. A difference seen in many rows is told once (`issues[].source: not declared`).
- It would have caught #10088 (`GET /users` 500), #10089 (`GET /reports/cart-recovery` 500) and #10090 (SEO issue rows with undeclared `source`, `label`, `weight`, `earned`); `tests/api_answer_check_test.php` holds those shapes.

## How is it run?
- `PG_WS_SITE=https://site/pinegrap/integration.php PG_WS_KEY=… PG_WS_SECRET=… php tools/check_api_answers.php [--only=/users]`. The settings are read by `ws_client_settings()` as the Workspace tools read them: environment only, never printed; plain http only for localhost.
- The key should hold every read scope. Output: `OK`, `DIFF` with one line a difference, `SKIP` with the reason; exit 0 clean, 1 a difference (or no document), 2 missing settings.
- It needs a running site with a database, so it is not a CI check: the test agent runs it on dev and files what it finds as `bug` issues.

## What does it leave out?
- Routes marked `'moves_money'` (read from this checkout's route rows, core and modules), binary answers, routes with a required query parameter, record routes whose list was empty.
- A 403 envelope (`insufficient_scope`, `forbidden` for a route kept for another kind of application) is a skip, not a difference.
- It waits for the next window when `X-RateLimit-Remaining` reaches 1 and as `Retry-After` says on a 429 (one retry). It sends GET only; a list is read with `limit=5`.

## Where is the logic?
- Pure functions `api_answer_check_*()` in the tool itself, which runs its main only outside the test runner (as `tools/ws_post.php` does). Added in PR #AM5PR.
