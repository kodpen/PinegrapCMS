---
name: workspace-push-delay
description: When a Workspace push wakes a person's devices: at once, a minute later while they look at the screen, or at the end of their quiet hours (QH1); ws_profiles.quiet_hours, pg_quiet_hours_left()
module: quiet_hours
---

## When is a Workspace push sent?
- `ws_notify()` (`includes/workspace/notify.php`) writes the inbox line and the bell row, then queues the push with `pg_push_enqueue($user_id, 'ws', $inbox_id, $delay)`. One queue (`push_queue`) serves a browser and a team device's native push alike.
- `$delay` is 0, or `pg_push_chat_delay()` for a mention, a guest's message or an agent's answer while the person has the workspace open (`ws_seen_recently()`), so a line read on the screen never wakes the phone. With no delay the queue is worked at once (`pg_push_queue_run(5)`).
- `ws_inbox_send_again()` (a line sent again) queues the same way.
- A row whose inbox line was read before it is due is dropped (`ws_push_still_relevant()`), and so is a disabled account's (`core/push-disabled-account.md`).

## What do quiet hours change?
- Inside the person's quiet hours the delay becomes the time to the range's end (`ws_quiet_hours_delay()`, the larger of the two delays). Nothing breaks through it. The inbox line, the bell, the badge and mail notices are unchanged; a line read meanwhile is dropped by the queue as today.
- The setting: `ws_profiles.quiet_hours` TEXT NULL (step 11.0.219), JSON `{"start": "HH:MM", "end": "HH:MM", "off_days": 0|1}`. Empty, broken, or a start equal to the end: no quiet range (`pg_quiet_hours_decode()`). A site whose update has not run (`pg_schema_has()` false) works as before.
- `pg_quiet_hours_left($setting, $zone, $workdays, $now)` (`includes/fn/quiet_hours.php`, pure): 0 outside the range, else seconds to the first moment no longer quiet. The range runs over midnight when its end comes first; with `off_days` a day that is not a working day (`ws_capacity()['workdays']`, bit 0 Monday) is quiet all day.
- It is counted in the person's zone (`user.timezone` through `pg_timezone_canonical()`, else the site's, as the person card's local time), so it keeps its clock times on the days the clock changes. Looks 8 days ahead at most.
- Tests: `tests/quiet_hours_test.php`.

## Who sets it?
- The person, for themselves: the setting's screen is QH2 of plan quiet-hours. No site-wide default.

## Why?
- Owner (2026-11-15, agent channel poll 54): outside your working hours no notification comes to the phone, it comes when the quiet range ends; a personal setting; one column (`workflow/decisions-made-workspace-35.md`, `workflow/decisions-made-schema-22.md`). That nothing breaks through and there is no default are the architect's readings.
