---
name: workspace-command-picker
description: What ws_commands($viewer) gives the / picker, /help, palette and API; the Tab argument popup after "/name "; an unknown /word refused with the closest names; "//" posts it
---

## What does ws_commands($viewer) give?
- includes/workspace/commands.php, built from the registry (workspace-command-registry.md), in picker order: `key`, `names` (each with its slash; `/claude`, `/ai`), `name` (Turkish on a Turkish screen: `ws_command_turkish()`), `label`, `description`, `usage` ("/görev <başlık> [@kişiler] …", built from the args), `example`, `args`, `live` (workspace-command-live-line.md), `app_id`, `app`, `shadowed`. Per command: workspace-command-status, -schedule, -plan-items.
- An arg: `name`, `kind` (`ws_command_argument_kinds()`: person people department task record date time estimate priority status amount quantity text options record_kind), `label`, `optional`, `suggest` (people, people_departments, tasks, records, products, dates, estimates, priorities, statuses, kinds, ''), `multiple`, `anywhere` (/task's people, day, estimate, priority, records: read anywhere).
- Without a person every command is listed but /claude and /ai, and no table is read. /claude, /ai need a person and their assistant set up (`ws_command_assistant_ready()`).
- `ws_command_help()`: command, names, example, description, key, name, usage, args, live for `/help`, `BOOT.commands`, `ws_palette_commands()`. `BOOT.command_values` = `ws_command_suggestions()`: days, estimates, priorities, statuses, kinds in the screen's language; five days are `top`.

## How does the argument popup work? (assets/js/workspace.js)
- `pickerCheck()` → `commandArgument(before)`: entry from the typed name, word being typed, expected arg. Each word or chip fills the next arg; a `multiple` one keeps the rest. A leading text (/task's title) takes words; then `extras` = the `anywhere` args the line lacks (`commandExtras()`). Poll options start at `|`. Not while editing.
- Picker mode `arg` (`openArgument()`): people → `BOOT.people` (+ departments for /task); task → `ws_ref_search` type task, written "G-42 "; products → a chip; dates/estimates/priorities/statuses/kinds → `BOOT.command_values` by folded prefix. /task extras: `@`, the top days, `~`, `!`, `#`.
- Keys: no row picked at first (row 0 marked `.ws-picker-next`, footer "Tab: write · ↑↓: choose · Esc: close"). Tab writes the marked row plus a space; Enter writes only an arrow-picked row, else sends. Esc dismisses that arg; Tab (`commandTab()`) brings it back.
- `drawCommandStrip()` (`.ws-cmd-strip`): typed name, args `<required>` `[optional]`, current ones highlighted, "e.g. …".
- Known: the / picker can open while a message is edited, where no command runs (`pickerCheck()` does not look at `editing`). A saved reply whose body is a command comes into the box as text and runs when sent; that stays (plan channel-commands).

## What happens to an unknown /word?
- `ws_command_unknown()`: first token `^/\p{L}{1,20}` then space or end, no command, not claude/ai. `/var/www x`, `http://x`, `/ 5`, `/2026`, `//x` are text. `ws_command_run()` answers `ws_command_unknown_answer()`: ephemeral, `keep`, up to three `ws_command_closest()` names (prefix or Levenshtein ≤ 1/2, folded; Turkish wins ties; never /g, /t).
- "//word …" posts "/word …" (`ws_command_unescape()` in `ws_send`); "// TODO", "///x", "//claude" stay as typed. Scheduled messages and the API do not unescape.

## Tests?
- tests/workspace_commands_test.php (registry, unknown, closest, "//", suggestions parsed, API route). API answer: workspace-command-api.md.
