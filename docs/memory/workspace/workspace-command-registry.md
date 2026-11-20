---
name: workspace-command-registry
description: The built-in / command registry ws_command_registry() (command_registry.php): its 35 entries in five groups, an entry's fields, who reads it, what a new command adds, shadowed application commands
---

## Where is the registry?
- `includes/workspace/command_registry.php`, `ws_command_registry()`: a PHP array, one entry per built-in command (35), sorted by `order`, cached per language a request. No table, no hook (architect, 2026-11-16, agent channel message 6770).
- Groups (`ws_command_groups()`, headers `ws_command_group_labels()`): Tasks, Plan, The channel, Records, Agents and applications. Each group's entries sit in a function of their own (`ws_command_registry_tasks()`, `_plan()`, `_channel()`, `_records()`, `_agents()`).

## What does an entry hold?
- `key`; `tr`, `en`; `label` ("/task · /görev"); `description`, `example` (`lang()`); `args` (`ws_command_registry_arg()`, kinds of `ws_command_argument_kinds()`); `names` (every name the parser answers to, no slash; empty for /claude, /ai); `group`; `where` (`any`, or `channel`: never in a discussion, `ws_command_runs_here()`); `order` (/help 1000).
- `variants` (/create only): per kind word, that kind's own `args` and `example`, shown on the strip once the word is typed (workspace-create-contact-row.md).
- Functions named as strings, defaults filled by the registry: `gate` ($viewer, $key, $channel) → bool, '' for anybody; `runner` ($viewer, $channel, $rest, $options, $body, $key); `live` → line, '' for none; `api` (default `run`): `preview`, `run`, or a function ($rest, $options, $key) → one (`ws_api_command_kind_create()`, `_poll()`, `_remind()`, `_approval()`, `_update()`, `_checkin()`, `_memory()`).

## Who reads it?
- `ws_command_names()` / `ws_command_create_names()` → `ws_command_table()`, the parser's name map. `ws_commands($viewer, $shadowed, $channel)` builds the picker, /help, palette and API list from it, then adds the applications' commands under `agents`.
- `ws_command_offered()` calls the gate and `ws_command_runs_here()`; `ws_command_run()` the runner (a key without an entry runs /help); `ws_command_suggest()` the live line; `ws_api_command_kind()` (api_commands.php) the API class.

## What does a new built-in command add?
- An entry in its group's function, its runner; a gate, live line or API class when needed; both names; texts with `php tools/tr_add.php`; then the order list in `tests/workspace_command_registry_test.php`.
- A new argument kind or value list also touches `ws_command_suggestions()`, `ws_app_command_arg_suggest()` and `openArgument()` in `assets/js/workspace.js`; a new kind of answer `send()` there and, when it writes nothing, `ws_api_command_preview_types()`.

## Where does a command run?
- A channel's box and a discussion (`ws_send`), also as a reply (`parent_id`, workspace-command-help.md); over the API (workspace-command-api.md).
- Never while a message is edited, in a scheduled message, a mailbox room, a note, or by a guest. Every command runs in a customer channel. What does not run in a channel is not offered there.
- A channel's by-hand flows follow the built-ins in the "/" list (workspace-command-flow.md); they are no registry entries.

## What is a shadowed application command?
- An application's command whose folded name is a built-in name or /claude, /ai (`ws_command_shadowed()`): the row stays, the built-in runs, `ws_command_app_entries()` leaves it out of the picker and /help, and `GET /workspace/commands` lists it with `shadowed: true`. Registration refuses such a name (422 `name_taken`).

## Tests?
- tests/workspace_command_registry_test.php: every function named exists, every field is there; keys, order, names, live lines and API classes; shadowing. Each command's own file names its test.
