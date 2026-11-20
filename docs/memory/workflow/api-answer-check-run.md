---
name: api-answer-check-run
description: Working rule: who runs tools/check_api_answers.php, when and how: a running site with a database, settings from the environment only, never in CI, findings filed as bug issues
---

## Who runs it, and when?
- The test coordinator (`test-coordinator.md`), on dev (`dev.pinegrap.com`), after changes to API answers or the OpenAPI document have landed. `--only=/path` limits a run to one route family.
- Never in CI and never in `tools/pre_push.sh`: it needs a running site with a database and a key.

## How is it run?
- `PG_WS_SITE=https://site/pinegrap/integration.php PG_WS_KEY=… PG_WS_SECRET=… php tools/check_api_answers.php [--only=/users]`.
- Address, key and secret come from the environment, read by `ws_client_settings()` as `tools/ws_client.php` reads them: never an argument, never printed, never in a PR, issue or channel message. Plain http only for localhost.
- The key holds every read scope; a missing scope shows as a skip, not a difference.
- It sends GET only (lists with `limit=5`), skips routes marked `'moves_money'` and binary answers, and keeps under the rate limit by itself (waits at `X-RateLimit-Remaining` 1, follows `Retry-After` once).

## What is done with the output?
- `OK`, `DIFF` (one line a difference), `SKIP` (with the reason); exit 0 clean, 1 a difference or no document, 2 missing settings.
- Each `DIFF` that is not already an open issue becomes one `bug` issue (route, status, the line, and the `request_id` the tool prints for an error answer), given to a worker by a coordinator. A `SKIP` for "no record to read" on a record route with no list at its parent needs a row in `api_answer_check_parents()`.
- What it checks and leaves out in detail: `api/api-answer-check.md`.
