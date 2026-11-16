---
name: quiet-hours
description: A person's quiet hours (plan quiet-hours, #9844 and QH2): set in a channel's Notifications, ws_profiles.quiet_hours, the rule pg_quiet_hours_left() reads, the person's own session only
module: quiet_hours
---

## What does it do?
- Inside the person's quiet hours no Workspace push wakes their phone or browser: the push waits for the range's end (`workspace-push-delay.md` says where). Nothing breaks through it. The inbox line, the bell, the badge and mail notices are unchanged; a line read meanwhile is dropped by the queue.

## Where is it set?
- The "Quiet hours" part under Notifications in a channel's info panel (`quietHoursPart()` in `workspace.js`), for all the person's channels: Start time, End time (time fields, `pgDateField()`), "Also quiet on the days I do not work", Save, Clear (shown once a range is set), and the line saying what it does. Drawn for a channel the person has joined, once the column is there (`CFG.quiet_hours.ready`, a team member).
- "From"/"To" are already "Kimden"/"Kime" in Turkish, so the labels are the existing keys "Start time" and "End time".

## Who sets it?
- The person, for themselves, from their panel session only: action `ws_quiet_hours_save` (a `pg_panel_actions()` row) → `ws_quiet_hours_save()` (`includes/workspace/notify.php`). No manager, no API route, no MCP tool, no site-wide default.
- `pg_quiet_hours_save_check($viewer, $request)` (pure) refuses a viewer who is not a team member, a request naming another `user_id`, and a bad time (`pg_quiet_hours_input()`: HH:MM, 24-hour, error field `start` or `end`).

## What is kept?
- `ws_profiles.quiet_hours` TEXT NULL (step 11.0.219), JSON `{"start": "HH:MM", "end": "HH:MM", "off_days": 0|1}`. Both times empty, or equal, is no range: the writer stores NULL. Read back by `ws_quiet_hours_get()` through `pg_quiet_hours_decode()`; `ws_quiet_hours_ready()` probes the column.

## What is the rule?
- `pg_quiet_hours_left($setting, $zone, $workdays, $now)` (`includes/fn/quiet_hours.php`, pure): 0 outside the range, else seconds to the first moment no longer quiet. The range runs over midnight when its end comes first; with `off_days` a day that is not a working day (`ws_capacity()['workdays']`, bit 0 Monday) is quiet all day; with no working day known none is off.
- Counted in the person's zone (`user.timezone` through `pg_timezone_canonical()`, else the site's, as the person card's local time), so it keeps its clock times on the days the clock changes. Looks 8 days ahead at most.

## Tests?
- `tests/quiet_hours_test.php`: the rule, the input, the writer's refusals, the action being panel-only. The save against a database was tried on a sandbox only.

## Which PRs?
- #9844 (the column, the rule, the delay) and #QH2PR (the setting).

## Why?
- Owner (2026-11-15, agent channel poll 54): outside your working hours no notification comes to the phone, it comes when the quiet range ends; a personal setting; one column (`workflow/decisions-made-workspace-35.md`, `workflow/decisions-made-schema-22.md`). That nothing breaks through and there is no default are the architect's readings.
- Left out, each a later yes of the owner: a site-wide default a manager gives; a kind that breaks through; quiet hours for mail notices.
