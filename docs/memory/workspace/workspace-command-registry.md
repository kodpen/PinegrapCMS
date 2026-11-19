---
name: workspace-command-registry
description: The built-in / command registry ws_command_registry() (command_registry.php, CC1): an entry's fields, which functions read it, what a new command adds, shadowed application commands
---

## Where is the registry?
- `includes/workspace/command_registry.php`, `ws_command_registry()`: a PHP array, one entry per built-in command keyed by its key, sorted by `order`, cached per language a request. No table, no hook (architect, 2026-11-16, agent channel message 6770).
- Each group's entries are in a function of its own (`ws_command_registry_tasks()`, `_plan()`, `_channel()`, `_records()`, `_agents()`), so two steps adding commands never write the same lines.

## What does an entry hold?
- `key`; `tr`, `en` (the name shown first); `label` ("/task · /görev"); `description`, `example` (through `lang()`); `args` (`ws_command_registry_arg()`); `names` (every name the parser answers to, no slash, in table order; empty for /claude and /ai, which become a tag before commands are read); `group`; `where` (`any`, or `channel`: never in a discussion, workspace-command-help.md); `order` (10, 20, …; /help 1000).
- Functions are named as strings: `gate` ($viewer, $key, $channel) → bool, also called with a null viewer or channel, '' for anybody; `runner` ($viewer, $channel, $rest, $options, $body, $key); `live` ($viewer, $channel, $rest) → line, warnings, '' for none; `api`: `preview`, `run`, or a function ($rest, $options, $key) answering one (`ws_api_command_kind_create()`, `_poll()`).

## Who reads it?
- `ws_command_names()` / `ws_command_create_names()` (/create, /product) → `ws_command_table()`, the parser's name map, in the old order. `ws_commands()` builds the picker, /help, palette and API list from it.
- `ws_command_offered()` calls the gate (`ws_command_gate_product()`, `_schedule()`, `_timer()`, `_recap()`…). `ws_command_run()` calls the runner (`ws_command_run_assign()`, `_done()`, `_postpone()`, `_noted()`, `_poll()`, `_help()`… or the command's own function); a key without an entry runs /help. `ws_command_suggest()` calls `live` (`ws_command_task_suggest()`, `ws_command_live_status()`…). `ws_api_command_kind()` (api_commands.php) reads `api`.

## What does a new built-in command add?
- One entry in its group's function and its runner; a gate, live line or API function when needed; names in Turkish and English; texts with `php tools/tr_add.php`. Then the order list in `tests/workspace_command_registry_test.php`.
- A new argument kind or value list also touches `ws_command_argument_kinds()`, `ws_command_suggestions()` and `openArgument()` in `assets/js/workspace.js`; a new kind of answer `send()` there and `ws_api_command_preview_types()`.

## Where does a command run?
- In a channel's box and a discussion (`ws_send`), also as a reply (the run gets `parent_id`: read by /schedule, /create with a file, an application's command); over the API (workspace-command-api.md).
- Never while a message is edited, in a scheduled message ("A command cannot be scheduled"), in a mailbox room, in a note (its "/" lists the note's inserts), or by a guest (`ws_guest_clean_text()` takes the slash off). Every command runs in a customer channel.
- The gate knows the channel too: what does not run there is not offered (workspace-command-help.md).

## What is a shadowed application command?
- An application's command whose name, folded, is a built-in name or /claude, /ai (`ws_command_shadowed()`): a built-in added later took it. The row stays, the built-in runs (the parser reads built-ins first), `ws_command_app_entries()` leaves it out of the picker, /help and closest names, and `GET /workspace/commands` lists it with `shadowed: true` (`ws_commands($viewer, true)`). Registration refuses such a name (409 `name_taken`).

## Tests?
- tests/workspace_command_registry_test.php: every runner, gate, live and API function exists, every field is there; keys, order, names, live lines and API classes as before; shadowing. Picker: workspace-command-picker.md.
