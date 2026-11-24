---
name: api-launch-journey
description: GET /launch (launch.read, MCP launch_read): the launch journey's steps, blockers and warnings as data, scope system:read, the LaunchJourney object, the start topic's line
module: launch_journey
---

## What does GET /launch answer?
- `launch.read` (`includes/api/resources/launch.php`, `api_launch_read()`), scope `system:read`, read only; MCP tool `launch_read`. Built by the pure `pg_launch_journey_report()` (`includes/fn/launch_journey.php`) over `pg_launch_journey_stored()` and `pg_launch_journey_facts()` with the key's owner: the same rules as the set-up card and the go-live check, nothing judged again.
- `LaunchJourney`: purpose (other while `purpose_chosen` is false), status (blocked, in_progress, ready), done, total, finished, live_at (null before the first go-live), steps (every step of the purpose in its order), blockers and warnings (the go-live check, maintenance first), go_live_changes (maintenance, robots).
- `LaunchStep`: key, name, state (done, todo, unknown), level (blocker, warning, none), reason (a stable code), line (for a person, translated), hidden ("Not for this site"; a hidden blocker still blocks, a hidden warning is not in warnings).

## Why system:read, and why no write?
- The answer is the site's operating state beside `GET /system/status`, so it takes that scope (roles 0 and 1, left out of an assistant's key). No setting's value is in it, only states.
- Going live stays a person's press in the panel (owner, poll 209, `workflow/decisions-made-product-94.md`): no API or MCP write.

## Where do agents learn of it?
- The guide's start topic, section jobs: "Whether the site is ready to go live, and what is missing first" names `{tool:launch.read}` (`includes/fn/agent_guide_start.php`).
- Tests: `tests/launch_journey_test.php` (`test_launch_journey_report`); `php tools/check_api_schema.php` checks the route and both objects.
