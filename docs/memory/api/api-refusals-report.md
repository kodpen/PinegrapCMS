---
name: api-refusals-report
description: Refused calls report: the Refused calls card of Application Access and GET /reports/api-refusals, read from api_request_log by route, error code and application with each code's guide section
module: api_refusals
---

## What does it show?
- The API calls answered 400 or above in the last 30 days (at most 30), from `api_request_log`, added up by route, error code and application, most refused first; each code with the guide section its hint names (`agent-guide-hints.md`), so the owner sees which guide section to mend.
- Nothing new is recorded: what the log retention of API Settings has purged is gone.
- Built in PR AG13b of plan ai-guide (feature G19).

## Where is it?
- `includes/fn/api_refusals.php`: `pg_api_refusals_sql()` (the one read), `pg_api_refusals_fold()` (ids out of paths, groups added up), `pg_api_refusals_report($routes, $days)`.
- Panel: the Refused calls card on Application Access (`api_settings.php`), the busiest twenty, below Applications; a guide section links `agent_guide.php?topic=…`.
- API: `GET /reports/api-refusals` (`reports.api_refusals`, scope `system:read`, params `days` 1–30, `limit` 1–200); handler and `ApiRefusalsReport` in `includes/api/resources/system.php`.
- Tests: `tests/api_refusals_test.php`.

## How are ids folded out of a path?
- The logged path is matched against the routes with the router's narrowing (an int placeholder takes digits only, `also_accepts` methods count): `/orders/12` becomes route `orders.get`, path `/orders/{id}`.
- A path no route takes (wrong address or method) keeps its words and turns each all-digit segment into `{id}`; `route` is null.
- The panel folds against `api_schema()` (core routes); the API against `api_all_routes()`, modules included, so a module path folds by digits only on the panel.

## Why is the read capped?
- SQL groups by method, raw path, code and application (range on `idx_timestamp`), busiest first, at most 2,000 groups (`pg_api_refusals_read_max()`); a client walking thousands of ids would otherwise return a row per id before PHP folds them.
- `status` is the highest status seen in a row; `application_id` is null for a call refused before its key was known, `application_name` null too when the application was removed.
