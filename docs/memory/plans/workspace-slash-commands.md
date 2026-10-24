---
name: workspace-slash-commands
description: Plan (2026-10-23, step 1 in PR #1440, answers open): fix /task (Enter on the preview, Turkish names), a command registry, Tab argument suggestions, more commands, GET /workspace/commands
---

## What is asked? (owner, 2026-10-23)
- More, clearer `/` commands ("I never managed to start a task from here"); after `/command ` a popup suggests, Tab writes, a space opens the next. Toolbar, insert menu, notes' `/`: plans/workspace-composer-tools.md; channel `/` commands: here.

## What is there today?
- includes/workspace/commands.php: `ws_command_names()`, `ws_command_help()` (`BOOT.commands`, `/help`, `ws_palette_commands()`), `ws_command_run()`, `ws_command_offered()`. Tested: /price, /stock.
- Why /task fails (server works):
  1. Bug: Enter on the preview resends without `confirm`: the same card returns; only a click on "Create the task" writes, re-reading the box. `self.pending` (`send()`, assets/js/workspace.js) is never read.
  2. Bug: `openPicker('command')` matches only help labels (`/task · /gorev`): `/gö`, `/tamam`, `/anket`, `/sor` find nothing, nor `/help` (no entry); no example.
  3. Typed `@ayse` is no person (only picked chips): /task goes to the author, /assign refuses, /plan shows yours; help examples type it.
  4. An unknown `/word` is posted.
- Parser: `1.5 saat` is due 1 May; "next week", most datives unread.
- No API lists or runs commands.

## What is proposed? (not approved yet)
- `ws_commands($viewer)` (proposed, commands.php): key, TR/EN names, `lang()` description and usage, typed arguments (person, task, record, date…) with a suggestion source, gate. Picker, palette, `/help`, parser and API read it.
- Suggestions only from what one may see (`BOOT.people`, `.channels`, `.priorities`, `ws_ref_search` through `ws_refs_resolve()`), never site members; runs re-check `ws_can_read_channel()`. Writes as today (/price, /stock: `ws_quick_edit_save()`, changes.php). Texts: `ws_js_strings()` (screen.php), includes/local/tr.json.
- App commands stay in plans/workspace-phase3-leftovers.md item 2 (schema, ask first), in this shape.

## Slices
1. Fix /task (no schema): Where to start.
2. Registry (no schema): `ws_commands()`; unknown `/word` per question 2; `/claude`, `/ai` listed when set up.
3. Argument popup (no schema): a new picker mode after `/name `, slot from the registry (`@`, `#` keep theirs); Tab/Enter write `value `, `pickerCheck()` reopens; a strip shows what is left.
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
- Slice 1: on a preview `send()` keeps `pending = {body: data.body}`; `drawPreview()` adds `card`, `hard`, `confirm()` (Create's options plus `body`). Enter (`send()`, no options), card shown (`isConnected`), no hard clash, `tokens(raw) === pending.body`: sends `confirm()`, never `force`; Create too. Card line "Enter: create · Esc: cancel". `ws_command_help()` entries gain `names`, plus `/help · /yardım`; the picker matches names folded (ö→o, ı→i), shows the example. Tests: parse_task, each name in a help entry (red first).
