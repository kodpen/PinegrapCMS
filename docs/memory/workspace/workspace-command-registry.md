---
name: workspace-command-registry
description: One registry (ws_commands()) for the / picker, /help, palette and the Tab argument popup after "/name "; an unknown /word refused with the closest names; "//" posts it
---

## What does ws_commands($viewer) hold?
- includes/workspace/commands.php, one entry per command in picker order: `key` (task, assign, done, postpone, decision, note, plan, poll, price, stock, claude, ai, help), `names` (every `ws_command_names()` key with its slash; `/claude`, `/ai`), `name` (Turkish on a Turkish screen: `ws_command_turkish()`), `label` ("/task · /görev"), `description`, `usage` ("/görev <başlık> [@kişiler] …", built from the args), `example`, `args`.
- An arg: `name`, `kind` (`ws_command_argument_kinds()`: person people department task record date estimate priority amount quantity text options), `label`, `optional`, `suggest` (people, people_departments, tasks, records, products, dates, estimates, priorities, ''), `multiple`, `anywhere` (/task's people, day, estimate, priority, records: read anywhere).
- Gate: `ws_command_offered()`. /price, /stock: the product rights. /claude, /ai: only with a person, once set up (`ws_command_assistant_ready()`, the test of `ws_claude_command_body()` / `ws_ai_command_body()`); without a person nothing touches the database.
- `ws_command_help()` keeps its shape (command, names, example, description) plus key, name, usage, args: `/help` (with usage), `BOOT.commands`, `ws_palette_commands()` (nothing typed: `name`). `BOOT.command_values` = `ws_command_suggestions()`: days, estimates, priorities in the screen's language, each read by the parser; five days are `top`.

## How does the argument popup work? (assets/js/workspace.js)
- `pickerCheck()` → `commandArgument(before)`: entry from the typed name, word being typed, expected arg. Each word or chip fills the next arg; a `multiple` one keeps the rest. A leading text (/task's title) takes words; once it has one, `extras` = the `anywhere` args the line lacks (`commandExtras()`: tags in the whole markup, day words, `~`, `!`). Poll options start at `|`. Not while editing.
- Picker mode `arg` (`openArgument()`): people/person → `BOOT.people` (+ departments for /task), without those named; task → `ws_ref_search` type task, written "G-42 "; products → type product, a chip; dates/estimates/priorities → `BOOT.command_values` by folded prefix. /task extras: `@`, the top days, `~`, `!`, `#`; a sign opens its list. A fixed list with nothing for the typed word closes (title words).
- Keys: no row picked at first (`pickerIndex -1`, row 0 marked `.ws-picker-next`, footer "Tab: write · ↑↓: choose · Esc: close"). Tab writes the marked row plus a space, `pick()` re-runs `pickerCheck()` for the next arg. Enter writes only an arrow-picked row, else sends (#1440's Enter on the /task preview unchanged). Space moves on. Esc dismisses that arg (`argDismissed`, /task's extras at once); `send()` dismisses too; Tab (`commandTab()`) brings it back. `@` and `#` keep their pickers.
- `drawCommandStrip()` above the box (`.ws-cmd-strip`): typed name, args `<required>` `[optional]`, current ones highlighted, "e.g. …".

## What happens to an unknown /word?
- `ws_command_unknown()`: first token `^/\p{L}{1,20}` then space or end, no command, not claude/ai. `/var/www x`, `http://x`, `/ 5`, `/2026`, `//x` are text. `ws_command_run()` answers `ws_command_unknown_answer()`: ephemeral, `keep` (the box keeps the text), up to three `ws_command_closest()` names (prefix or Levenshtein ≤ 1/2, folded; ties to the spelling with Turkish letters; never /g, /t).
- "//word …" posts "/word …" (`ws_command_unescape()` in `ws_send`); "// TODO", "///x", "//claude" stay as typed. Scheduled messages and the API do not unescape.

## Tests and checks?
- tests/workspace_commands_test.php (registry, unknown vs path, closest, "//", suggestions parsed). Composer checked in headless Chromium on workspace.min.js, stubbed API; no running instance.
