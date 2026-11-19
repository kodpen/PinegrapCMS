---
name: api-workspace-quiet-hours
description: A person's own quiet hours over the API: quiet_hours on GET /auth/me, POST /workspace/quiet-hours from a team devices session only, the quiet_hours feature key (issue #10347)
module: quiet_hours
---

## What does a client read?
- `quiet_hours` on `GET /auth/me`: `{start: "HH:MM", end: "HH:MM", off_days: 0|1}` from `ws_quiet_hours_get()`, null with no range set. The key is left out while the Workspace is off or step 11.0.219 has not run.
- `features.quiet_hours` on `GET /meta` and `GET /auth/me` (`api_meta_quiet_hours()`): true when the Workspace is on and `ws_quiet_hours_ready()`. Without it a client shows no quiet hours part; a 404 or 405 on the write route means an older site.

## How is it set?
- `POST /workspace/quiet-hours` (`includes/workspace/api_quiet_hours.php`, `ws_api_quiet_hours_set()`), body `start`, `end`, `off_days`, optional `user_id`. Answers `{quiet_hours}` as the panel action does. Both times empty, or equal, clears the range.
- Scope `account:write`, which a team devices session always holds. `ws_api_own_words_only()` first: an application key and an OAuth connection get 403; `'mcp' => false`.
- Then `pg_quiet_hours_save_check()` (a `user_id` naming somebody else is 403, a bad time 422 `validation_failed` with field `start` or `end`), then `api_dry_run_stop()`, then `ws_quiet_hours_save()`. No column: 503 `service_unavailable`.

## Tests?
- `tests/quiet_hours_test.php`: the route's row, the order gate → check → dry run → write, the panel action and this route the only writers, the answer's shape.
