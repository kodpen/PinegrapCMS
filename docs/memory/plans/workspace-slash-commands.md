---
name: workspace-slash-commands
description: Plan (2026-10-23, 1 done #1440, 2-3 #1499, 4 #1731, 6 /status #1896, answer 2a): /task fixes, command registry, Tab suggestions, live line, more commands, GET /workspace/commands
---

## What is asked? (owner, 2026-10-23)
- More, clearer `/` commands ("I never managed to start a task from here"); after `/command ` a popup suggests, Tab writes, a space opens the next. Toolbar, insert menu, notes' `/`: plans/workspace-composer-tools.md; channel `/` commands: here.

## What is there today?
- includes/workspace/commands.php: `ws_command_names()`, `ws_command_help()` (`BOOT.commands`, `/help`, `ws_palette_commands()`), `ws_command_run()`, `ws_command_offered()`. Tested: /price, /stock.
- /task Enter, Turkish names (#1440), the registry and argument popup (#1499), the live line and the parser's next week, datives, `14:00` (#1731, workspace/workspace-command-live-line.md) are built.
- A typed `@username` assigns on one exact team match, else warns (question 1 answered a, #1731).
- No API lists or runs commands.

## What is proposed? (not approved yet)
- `ws_commands($viewer)` (proposed, commands.php): key, TR/EN names, `lang()` description and usage, typed arguments (person, task, record, date…) with a suggestion source, gate. Picker, palette, `/help`, parser and API read it.
- Suggestions only from what one may see (`BOOT.people`, `.channels`, `.priorities`, `ws_ref_search` through `ws_refs_resolve()`), never site members; runs re-check `ws_can_read_channel()`. Writes as today (/price, /stock: `ws_quick_edit_save()`, changes.php). Texts: `ws_js_strings()` (screen.php), keys added with `php tools/tr_add.php` (shards in `includes/local/tr/`).
- App commands stay in plans/workspace-phase3-leftovers.md item 2 (schema, ask first), in this shape.

## Slices
1. Done (#1440): /task Enter on the preview, Turkish and English names in the picker.
2. Done (#1499). Registry (no schema): `ws_commands()`; unknown `/word` per question 2; `/claude`, `/ai` listed when set up.
3. Done (#1499). Argument popup (no schema): a new picker mode after `/name `, slot from the registry (`@`, `#` keep theirs); Tab/Enter write `value `, `pickerCheck()` reopens; a strip shows what is left.
4. Done (#1731). Live line: `ws_command_suggest` runs the parser; "Will create: …", `@word` warning; "next week", datives, `14:00` (`due_time`); `1.5 saat` is an estimate; a typed `@username` assigns on one exact team match.
5. API (no schema): `GET /workspace/commands` (workspace:read, `'mcp' => false`) lists the registry.
6. New commands, a PR each (no schema): `/durum /status` done (#1896, workspace/workspace-command-registry.md); left: `/zamanla /schedule` (`ws_scheduled_message_save()`), `/süre /timer` (`ws_task_time_start()`), `/izin /away`, `/toplantı /meeting` (`ws_event_save()`).

## Open questions (recommendation)
1. Answered (owner, 2026-10-26): (a) a typed `@ayse` assigns on one exact team username, else warns.
2. Unknown `/word`: (a) refuse with a hint, (b) post with a hint → a.
3. API: (a) list only, effects have endpoints, (b) run them, dry_run → a.
4. First commands: (a) slice 6, (b) plus record cards (`/sipariş`…) → a.
5. Enter on a /price, /stock card: (a) Save only, (b) saves like /task → a.

## Where to start?
- Slice 5 (GET /workspace/commands) once no open PR holds includes/workspace/api.php.
- Slice 6: the next command (`/zamanla /schedule`), declared in `ws_commands()` like /status; set `live` and add a `ws_command_*_suggest()` if it gets a live line.
