---
name: api-answer-check
description: tools/check_api_answers.php, the read-only check that calls every GET route of a running site and compares each answer with the site's OpenAPI document; how it is run, what it flags and skips
---

## What does it check?
- It reads the site's own `GET /openapi.json`, then calls every GET route: lists first, then each record route with the first row of its list (`/orders/{id}/items/{item_id}` walks `/orders`, then `/orders/9/items`).
- Each answer against its 200 schema: the status, JSON at all, required fields, types, `date-time` / `date` formats, enums, null only where `nullable`, fields the document does not declare. A difference seen in many rows is told once (`issues[].source: not declared`).
- It would have caught #10088 (`GET /users` 500), #10089 (`GET /reports/cart-recovery` 500) and #10090 (SEO issue rows with undeclared `source`, `label`, `weight`, `earned`); `tests/api_answer_check_test.php` holds those shapes.

## How is a record route filled?
- Each path parameter takes the row's field of its own name, else the row's `id`: `{n}` reads `n`, `{user_id}` and `{agent_id}` their fields, `{no}` reads `revision_no` (`api_answer_check_param_value()`). Before #10155 every parameter took `id`, so `/workspace/flows/{id}/versions/{n}` was called with a version's id and answered 404.
- A record route with no list at its parent path reads the list `api_answer_check_parents()` names; a new such route gets a row there, or it is skipped as "no record to read".
- The table: message routes and `/workspace/canvases/{id}` from `/workspace/channels/{id}/messages`, `/workspace/memory/{id}` from the channel's memory, `/workspace/mail/items/{id}` from a conversation's `mails`, `/workspace/flow-runs/{id}` from the flow's runs, a box's `{node}` from the flow's `draft.nodes`, `/design/proposals/{id}` from a page's proposals, `/store/states/{id}` from the `states` of `/store/countries`, `/translations/pages/{id}` from `/pages`.
- A list a record needs is read when needed, once (`limit=5` where the route takes it).

## When is a refusal not a difference?
- A 4xx (not 403, not 429) whose status the route's own description names for one record ("404 for a task that has never repeated", "409 not_a_collection") tries the next rows, up to `API_ANSWER_CHECK_TRIES` (5) records: first rows first, the outer list moving before the inner one. When none answers otherwise, the route is a `SKIP` naming each try (#10155).

## How is it run?
- `PG_WS_SITE=https://site/pinegrap/integration.php PG_WS_KEY=… PG_WS_SECRET=… php tools/check_api_answers.php [--only=/users]`. The settings are read by `ws_client_settings()` as the Workspace tools read them: environment only, never printed; plain http only for localhost.
- The key should hold every read scope. Output: `OK`, `DIFF` with one line a difference, `SKIP` with the reason; exit 0 clean, 1 a difference (or no document), 2 missing settings.
- It needs a running site with a database, so it is not a CI check: the test agent runs it on dev and files what it finds as `bug` issues.

## What does it leave out?
- Routes marked `'moves_money'` (read from this checkout's route rows, core and modules), binary answers, routes with a required query parameter, record routes whose list was empty.
- A 403 envelope (`insufficient_scope`, `forbidden` for a route kept for another kind of application) is a skip, not a difference.
- It waits for the next window when `X-RateLimit-Remaining` reaches 1 and as `Retry-After` says on a 429 (one retry). It sends GET only; a list is read with `limit=5`.

## Where is the logic?
- Pure functions `api_answer_check_*()` in the tool itself, which runs its main only outside the test runner (as `tools/ws_post.php` does). Added in PR #10136; filling by parameter name, the parent table and the next-row tries for #10155.
