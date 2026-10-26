---
name: workspace-command-schedule
description: /schedule (/zamanla) in a Workspace channel: when it reads (+30dk, a day, 14:00), the 09:00 and tomorrow defaults, ws_scheduled_message_save(), its writer-only answer and live line
---

## What does /schedule (/zamanla) do?
- `/zamanla yarın 09:00 Toplantı başlıyor` saves a scheduled message through `ws_scheduled_message_save()` (scheduled_messages.php), the same row the clock beside the send button writes; no schema. The answer is ephemeral ("Scheduled for …"); the bar above the box counts it on the next `ws_sync` (`scheduled_mine`).
- A reply's `parent_id` reaches it: `ws_send` passes `parent_id` in the options of `ws_command_run()`.
- Offered only to whoever `ws_can_schedule_messages()` (`ws_command_offered()`); without a person it is listed like the rest.

## What does the parser read? (`ws_command_parse_schedule($text, $now)`)
- At the start of the line, in order: `+30dk` / `+2sa` / `+45m` / `+1h` (`ws_parse_schedule_offset()`: here `h` is an hour, not a week as in `ws_parse_date_word()`), or a day word (`ws_parse_date_word()`), then a time (`ws_parse_time_word()`, `saat` / `at` before it dropped). The rest is the message as written: only the words read as the time come off its start (`preg_split()` with a limit), so line breaks, lists, quotes and code stay (#1938; joining the words with spaces had posted a multi-line message as one line).
- A day alone is at 09:00. A time alone is today's, tomorrow's once it has gone by. No time read: `at` 0, refused with "Say when first: …".
- The save's own refusals stay: a command body, empty text, a past time, more than a year ahead, 50 waiting.

## How is it offered?
- Registry entry `schedule`: args `day` (date, optional, suggest `dates`), `time` (kind `time`, added to `ws_command_argument_kinds()`, no suggestions), `message` (text). `live`: `ws_command_schedule_suggest()` says "Will post: Tomorrow 09:00 · “…”" and warns about a missing time, empty message, command body, past or too far.

## Tests?
- tests/workspace_commands_test.php: names, parse (offset, day, time, defaults, suffix, the message kept as written), registry order, live commands. Not run on a live instance (needs a database and a channel).
