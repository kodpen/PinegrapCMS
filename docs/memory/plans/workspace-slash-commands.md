---
name: workspace-slash-commands
description: Plan (2026-10-23, 1 done #1440, 2-3 done #1499, answer 2a): fix /task (Enter on the preview, Turkish names), a command registry, Tab argument suggestions, more commands, GET /workspace/commands
---

## What is asked? (owner, 2026-10-23)
- More, clearer `/` commands ("I never managed to start a task from here"); after `/command ` a popup suggests, Tab writes, a space opens the next. Toolbar, insert menu, notes' `/`: plans/workspace-composer-tools.md; channel `/` commands: here.

## What is there today?
- includes/workspace/commands.php: `ws_command_names()`, `ws_command_help()` (`BOOT.commands`, `/help`, `ws_palette_commands()`), `ws_command_run()`, `ws_command_offered()`. Tested: /price, /stock.
- /task Enter, Turkish names (#1440), the registry and argument popup (#1499) are built.
- Parser: `1.5 saat` is due 1 May; "next week", most datives unread.
- No API lists or runs commands.

## What is proposed? (not approved yet)
- `ws_commands($viewer)` (proposed, commands.php): key, TR/EN names, `lang()` description and usage, typed arguments (person, task, record, date…) with a suggestion source, gate. Picker, palette, `/help`, parser and API read it.
- Suggestions only from what one may see (`BOOT.people`, `.channels`, `.priorities`, `ws_ref_search` through `ws_refs_resolve()`), never site members; runs re-check `ws_can_read_channel()`. Writes as today (/price, /stock: `ws_quick_edit_save()`, changes.php). Texts: `ws_js_strings()` (screen.php), includes/local/tr.json.
- App commands stay in plans/workspace-phase3-leftovers.md item 2 (schema, ask first), in this shape.

## Slices
1. Done (#1440): /task Enter on the preview, Turkish and English names in the picker.
2. Done (#1499). Registry (no schema): `ws_commands()`; unknown `/word` per question 2; `/claude`, `/ai` listed when set up.
3. Done (#1499). Argument popup (no schema): a new picker mode after `/name `, slot from the registry (`@`, `#` keep theirs); Tab/Enter write `value `, `pickerCheck()` reopens; a strip shows what is left.
4. Live line (no schema): `ws_command_suggest` action (proposed) runs the parser: real days, "Will create: …", `@word` warning; reads "next week", datives, `14:00` (`due_time`); `1.5` is no date.
5. API (no schema): `GET /workspace/commands` (workspace:read, `'mcp' => false`) lists the registry.
6. New commands, a PR each (no schema): `/durum /status` (a task's: `ws_task_set_status()`), `/zamanla /schedule` (`ws_scheduled_message_save()`), `/süre /timer` (`ws_task_time_start()`), `/izin /away`, `/toplantı /meeting` (`ws_event_save()`).

## Open questions (recommendation)
1. Typed `@ayse`: (a) one exact team username, else warn, (b) warn only → a.
2. Unknown `/word`: (a) refuse with a hint, (b) post with a hint → a.
3. API: (a) list only, effects have endpoints, (b) run them, dry_run → a.
4. First commands: (a) slice 6, (b) plus record cards (`/sipariş`…) → a.
5. Enter on a /price, /stock card: (a) Save only, (b) saves like /task → a.

## Where to start?
- Slice 4 (live line, `ws_command_suggest`), then 5 (GET /workspace/commands).
