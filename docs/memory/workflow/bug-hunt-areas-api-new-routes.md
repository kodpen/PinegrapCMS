---
name: bug-hunt-areas-api-new-routes
description: The bug hunt of the API routes and MCP tools new in 2026.11.0 (VS8, WQ8, LJ6, SJ6, DV6, share links; PR of 2026-11-24): the two queue faults fixed, what was read clean, what was not run
---

## Which area was hunted?
- Architect (2026-11-24, area 12): the routes and MCP tools added in 2026.11.0. Asked: each scope against the matching screen's right, a module switched off, dry run writing nothing, ids of another site's or person's rows, the answer types against `tools/check_api_schema.php`. Workspace mail was another hunt's (#11367).
- Read: `includes/api/resources/queue.php`, `volunteers.php`, `leads.php`, `launch.php`, `includes/workspace/api_share_links.php`, their `api_schema()` rows and scope ceilings, `queue_desk.php` for the desk's rights.

## What did the hunt fix?
- A rehearsed POST /queue/tickets answered "created" while the hours, daily cap or closing time would refuse: the checks ran only inside `pg_waiting_take()`. Now `pg_waiting_take_check()`, run by the take and before the API's stop (`api/api-walk-in-queue-routes.md`).
- A user-role key could Call next at, and Finish the number of, a colleague's desk at its place; the desk screen allows only one's own desk. Now 403 `forbidden` (`api_queue_desk_reaches()`).
- Tests: `tests/api_queue_rehearsal_test.php`.

## What was read clean?
- Volunteers (VS8): the user role reads only programmes naming it coordinator, another `programme_id` is 404; days checked; nothing written.
- Lead journey (LJ6): journey and score through `api_customer_reachable_or_fail()`, the hot list through `api_customer_owner_filter()`; revenue only with orders:read; staff contacts give no journey or score.
- Launch (SJ6): read only, `system:read` as the health report.
- Share links: make and take back run the panel's checks before the stop; the list never gives the address; an application takes back only its own links. Routes exist only with the Workspace on.
- Canvas data (DV6): left to its own hunt (`bug-hunt-areas-canvas-data-views.md`). `check_api_schema.php` passes for every route.

## What was not run?
- No database: the handlers were read and tried by their pure parts and by the order of their checks in the source.
