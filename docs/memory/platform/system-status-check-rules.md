---
name: system-status-check-rules
description: System Status panel rules - a check row and its action button must come from the same read (rules signature), per-check detail boxes, confirm tiles on buttons.
---

## Why must a status row and its action button share one read?
- `get_system_status_checks()` is cached for ten minutes; the server-rules tile reads the file live. When they diverge, a row says "2 missing" next to a button that is never drawn (nothing to do), read as "says missing, does not fix".
- Rule: **a check row and its action button are fed by the same read.** `pg_server_config_scan()` is memoized per request; `pg_server_rules_signature()` collects everything the scan decided into one string, stored with the cache and compared on read - if different the cache is dropped.
- When adding a new "check + button" pair, build the same pattern.

## How is the detail panel organised?
- Rows made with `$makeIcon(..., $detail)` write into a box `system_status_detail_<n>`, and the box starts with which check it belongs to. There used to be one `#system_status_detail`; adding a second detailed check made two chevrons open the same box.

## What about confirm tiles?
- If the confirm-asking tile is a `<button>`, read `event.isDefaultPrevented()`. The `.pg-health-tile[data-confirm-content]` handler only calls `preventDefault()`; on a link that stops navigation, on a button there is nothing to stop.

Related: docs/memory/platform/system-status-ca-bundle.md
