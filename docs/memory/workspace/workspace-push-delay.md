---
name: workspace-push-delay
description: When a Workspace push wakes a person's devices: at once, a minute later while they look at the screen, or at the end of their quiet hours (quiet-hours.md)
---

## When is a Workspace push sent?
- `ws_notify()` (`includes/workspace/notify.php`) writes the inbox line and the bell row, then queues the push with `pg_push_enqueue($user_id, 'ws', $inbox_id, $delay)`. One queue (`push_queue`) serves a browser and a team device's native push alike.
- `$delay` is 0, or `pg_push_chat_delay()` for a mention, a guest's message or an agent's answer while the person has the workspace open (`ws_seen_recently()`), so a line read on the screen never wakes the phone. With no delay the queue is worked at once (`pg_push_queue_run(5)`).
- `ws_inbox_send_again()` (a line sent again) queues the same way.
- A row whose inbox line was read before it is due is dropped (`ws_push_still_relevant()`), and so is a disabled account's (`core/push-disabled-account.md`).

## What do quiet hours change?
- Inside the person's quiet hours the delay becomes the time to the range's end (`ws_quiet_hours_delay()`, the larger of the two delays); a site whose update has not run (`pg_schema_has()` false) works as before. The setting, who sets it and the rule: `quiet-hours.md`.
