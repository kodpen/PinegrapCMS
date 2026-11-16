---
name: workspace-command-registry
description: The built-in / command registry ws_command_registry() (command_registry.php, CC1a): an entry's fields, which functions read it, what a new command adds, shadowed application commands
---

## Where is the registry?
- `includes/workspace/command_registry.php`, `ws_command_registry()`: a PHP array, one entry per built-in command keyed by its key, sorted by `order`, cached per language a request. No table, no hook (architect, 2026-11-16, agent channel message 6770).
- Each group writes its entries in a function of its own: `ws_command_registry_tasks()`, `_plan()`, `_channel()`, `_records()`, `_agents()` (`ws_command_groups()`), so two steps adding commands never write the same lines.

## What does an entry hold?
- `key`; `tr`, `en` (the name shown first); `label` ("/task · /görev"); `description`, `example` (through `lang()`); `args` (`ws_command_registry_arg()`); `names` (every name the parser answers to, no slash, in table order; empty for /claude and /ai, which become a tag before commands are read); `group`; `where` (`any` until CC2 reads it); `live` (true: `ws_command_suggest()` draws a line); `order` (10, 20, …; /help 1000).
- Functions are named as strings: `gate` ($viewer, $key) → bool, also called with a null viewer, '' for anybody; `api`: `preview`, `run`, or a function ($rest, $options, $key) answering one (`ws_api_command_kind_create()`, `_poll()`).
- The runner and the live line are still the switch of `ws_command_run()` and the branches of `ws_command_suggest()`; CC1b of plan channel-commands makes them entry fields.

## Who reads it?
- `ws_command_names()` / `ws_command_create_names()` (/create, /product) → `ws_command_table()`, the parser's name map, in the old order. `ws_commands()` builds the picker, /help, palette and API list from it.
- `ws_command_offered()` calls the gate (`ws_command_gate_product()`, `_schedule()`, `_timer()`, `_wake()`, `_agent_channel()`, `_assistant()`). `ws_api_command_kind()` (api_commands.php) reads `api`.

## What does a new built-in command add?
- One entry in its group's function, its `case` in `ws_command_run()` (until CC1b); a gate, live line or API function when needed; names in Turkish and English; texts with `php tools/tr_add.php`. Then the order list in `tests/workspace_command_registry_test.php`.
- A new argument kind or value list also touches `ws_command_argument_kinds()`, `ws_command_suggestions()` and `openArgument()` in `assets/js/workspace.js`; a new kind of answer `send()` there and `ws_api_command_preview_types()`.

## Where does a command run?
- In a channel's box and a discussion (`ws_send`), also as a reply (the run gets `parent_id`: read by /schedule, /create with a file, an application's command); over the API (workspace-command-api.md).
- Never while a message is edited, in a scheduled message ("A command cannot be scheduled"), in a mailbox room, in a note (its "/" lists the note's inserts), or by a guest (`ws_guest_clean_text()` takes the slash off). Every command runs in a customer channel.
- The gate knows the person, not the channel: /agent-channel is still listed in a discussion and refused only when run (CC2 of plan channel-commands).

## What is a shadowed application command?
- An application's command whose name, folded, is a built-in name or /claude, /ai (`ws_command_shadowed()`): a built-in added later took it. The row stays, the built-in runs (the parser reads built-ins first), `ws_command_app_entries()` leaves it out of the picker, /help and closest names, and `GET /workspace/commands` lists it with `shadowed: true` (`ws_commands($viewer, true)`). Registration refuses such a name (409 `name_taken`).

## Tests?
- tests/workspace_command_registry_test.php: every gate and API function exists, every field is there; keys, order, names, live lines and API classes as before; shadowing. Picker: workspace-command-picker.md.
