---
name: workspace-command-live-line
description: The live line under a /task being written (ws_command_suggest: "Will create: …", @word warning) and what the /task parser reads: next week, datives, 14:00 as due_time, "1.5 saat" as an estimate
---

## What does the live line show?
- While `/task …` (`/görev`) is written, a second row under the usage strip (`drawCommandLive()`, assets/js/workspace.js) shows what Enter would create: "Will create: “title” · people (or the department, or Yours) · from day · due day and time · estimate · priority" (#1731).
- Warnings under it: an `@word` typed without picking the person ("@ayse is not tagged…"), a missing title, people the writer may not give tasks to (`ws_can_assign_to()`).
- Only /task has a line; other commands answer an empty one. The row reuses `.ws-cmd-strip` with Bootstrap utilities (`ws-cmd-live` is only a hook, no CSS).

## Where does it come from?
- Panel action `ws_command_suggest` (`channel_id`, `body`; post right on the channel via `ws_action_channel()`), in the `ws_` list of `pg_panel_actions()`. It calls `ws_command_suggest()` (commands.php), which runs the same `ws_command_parse_task()` that `/task` runs, so the line never says what the parser would not do.
- The composer asks 300 ms after typing stops (`commandLive()`), keeps the last answer on screen until the next arrives and drops answers for another channel or an older text.

## What does the /task parser read now?
- "next week", "gelecek hafta", "önümüzdeki hafta" (two words) as seven days on, like "haftaya"; "next" alone stays in the title.
- Datives: `pazartesiye`, `salıya`, `perşembeye`, `cumaya`, `cumartesiye`, `pazara`, `yarına`, `bugüne`; any day word or date with a suffix after an apostrophe (`25.10'a`, `cuma'ya`).
- `14:00` (`ws_parse_time_word()`) as `due_time`, passed to `ws_task_create()` and checked by `ws_task_reminder_validate()`; a time with no day is today's; `saat` / `at` right before a time is dropped from the title.
- A number before an hour or minute word (`1.5 saat`, `45 dk`, `2 hours`: `ws_parse_estimate_words()`) is the estimate; before #1731 `1.5 saat` was due 1 May. A bare `1.5` is still a date.
- A typed `@word` stays in the title and is listed in `typed_people` for the warning; it assigns nobody (open question 1 of plans/workspace-slash-commands.md: assign on one exact team username or warn only).
- `ws_command_parse_task($text, $today)` takes the day for tests.

## Where are the tests?
- `tests/workspace_commands_test.php`: next week, datives, time, number with a unit, typed people. The action and the composer row were not run on a live instance (they need a database and a channel).
