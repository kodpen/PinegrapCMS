---
name: workspace-command-plan-items
description: /timer (/süre), /away (/izin) and /meeting (/toplantı) in a Workspace channel (PR #1938): what each reads, the functions they write through, who is told, their live lines
---

## What does /timer (/süre, /sure) do?
- `/süre G-42` starts the writer's timer through `ws_task_time_start()` (task_time.php), which stops and keeps one running elsewhere; `/süre dur` (dur, durdur, bitir, stop, end: `ws_command_timer_stop_word()`) stops it through `ws_task_time_stop()`; `/süre` alone says what runs. Answers are ephemeral; a stopped timer is announced as the drawer's is.
- Offered once `ws_task_time_ready()`. Live line `ws_command_timer_suggest()`: the task it starts on or the timer it stops; warns when the task is not found, the writer may not log (`ws_task_time_may_log()`), or another timer will be stopped.

## What does /away (/izin) do?
- `/izin cuma pazartesi Aile ziyareti`: `ws_command_away_data()` reads the first day from today and a second day word, the last day, from the first, so `pazartesi cuma` typed on a Tuesday is next week's Monday to Friday (#1938; read from today, `friday monday` typed on a Monday ended before it began and was refused); the rest is the title (Leave when empty). Written through `ws_event_save()` as kind `leave`, scope people (the writer). Ephemeral answer, nothing posted.

## What does /meeting (/toplantı, /toplanti) do?
- `/toplantı yarın 14:00 ~1sa Açılış @ayse`: `ws_command_meeting_data()` reads the start as /schedule does (`ws_command_parse_schedule()`: day, time, both, +30dk; a day alone 09:00, a time gone by tomorrow), then an end time or a `~` length (an hour by default; an end before the start is the next day), the `<@user:N>` tags and the rest as the title. The writer is always on it (`ws_command_meeting_people()`). Written through `ws_event_save()`; a system line in the channel names it and its people.

## Live lines?
- `ws_command_event_suggest()` for /away and /meeting: "Will write leave: …" / "Will plan: … · people", with the refusals of `ws_event_save_check()` (plan only for oneself or one's departments, end after start) and a missing meeting title.

## Tests?
- tests/workspace_commands_test.php: names, stop words, away days (the last read from the first), meeting start/end/length/midnight/people. Not run on a live instance.
