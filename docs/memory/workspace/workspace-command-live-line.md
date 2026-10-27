---
name: workspace-command-live-line
description: The live line under a /task and a /status (ws_command_suggest: "Will create: …", warnings), a typed @username assigning, and what the parser reads: next week, datives, 14:00, "1.5 saat" as an estimate
---

## What does the live line show?
- While `/task …` (`/görev`) is written, a second row under the usage strip (`drawCommandLive()`, assets/js/workspace.js) shows what Enter would create: "Will create: “title” · people (or the department, or Yours) · from day · due day and time · estimate · priority" (#1731).
- Warnings under it: a typed `@name` no team member has or several share, a missing title, people the writer may not give tasks to (`ws_can_assign_to()`).
- /status has one too (PR #1896, `ws_command_status_suggest()`): "Will move: G-42 “title” · To do → Waiting", or the task and its status alone before a word. Warnings: task not found, a word that is no status, `ws_task_set_status_check()` refusing. /schedule, /timer, /away, /meeting have one too (workspace-command-schedule.md, workspace-command-plan-items.md). The composer asks for a line only for registry entries with `live` (task, status, timer, schedule, away, meeting); other commands answer an empty one. The row reuses `.ws-cmd-strip` with Bootstrap utilities (`ws-cmd-live` is only a hook, no CSS).

## Where does it come from?
- Panel action `ws_command_suggest` (`channel_id`, `body`; post right on the channel via `ws_action_channel()`), in the `ws_` list of `pg_panel_actions()`. It calls `ws_command_suggest()` (commands.php), which runs the same `ws_command_parse_task()` that `/task` runs, so the line never says what the parser would not do.
- The composer asks 300 ms after typing stops (`commandLive()`), keeps the last answer on screen until the next arrives and drops answers for another channel or an older text.

## What does the /task parser read now?
- "next week", "gelecek hafta", "önümüzdeki hafta" (two words) as seven days on, like "haftaya"; "next" alone stays in the title.
- Datives: `pazartesiye`, `salıya`, `perşembeye`, `cumaya`, `cumartesiye`, `pazara`, `yarına`, `bugüne`; any day word or date with a suffix after an apostrophe (`25.10'a`, `cuma'ya`).
- `14:00` (`ws_parse_time_word()`) as `due_time`, passed to `ws_task_create()` and checked by `ws_task_reminder_validate()`; a time with no day is today's; `saat` / `at` right before a time is dropped from the title.
- A number before an hour or minute word (`1.5 saat`, `45 dk`, `2 hours`: `ws_parse_estimate_words()`) is the estimate; before #1731 `1.5 saat` was due 1 May. A bare `1.5` is still a date.
- A typed `@name` is listed in `typed_people`; `ws_command_typed_assignees($parsed, ws_team_members())`, called by `/task` and the live line, assigns it when it is exactly one team member's username (case and Turkish letters folded) and takes it out of the title. No match, a prefix (`@a`) or a username several people share assigns nobody and stays in the title (`typed_unknown`, `typed_ambiguous`). Owner decision, 2026-10-26 (workflow/decisions-made-workspace.md). Only team members are looked at, never site members.
- `ws_command_parse_task($text, $today)` takes the day for tests.

## Where are the tests?
- `tests/workspace_commands_test.php`: next week, datives, time, number with a unit, typed people and their assignment. The action and the composer row were not run on a live instance (they need a database and a channel).
