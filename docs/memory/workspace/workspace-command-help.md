---
name: workspace-command-help
description: The command gate that knows the channel (where: channel), /help in groups and /help <command>, the palette's group and place, and /task, /decision, /note typed as a reply without a text (CC2a)
---

## Where may a command run?
- An entry's `where` (command_registry.php): `any`, or `channel` (the channel itself, never one of its discussions: `ws_command_runs_here()` refuses `kind` thread). Only /agent-channel is `channel` today.
- `ws_command_offered($viewer, $key, $channel)`, `ws_commands($viewer, $shadowed, $channel)` and `ws_command_help($viewer, $channel)` leave such a command out where it does not run; without a channel nothing is left out (the composer's `BOOT.commands`, the API list).
- `ws_command_run()` refuses it all the same before its runner: "This command runs in the channel itself, not in one of its discussions."

## How does /help answer?
- To the writer alone (`ephemeral`), with the commands of this channel only. Under "Slash commands", one `ws-help-group` header per group in `ws_command_group_labels()` order (Tasks, Plan, The channel, Records, Agents and applications), each with its `dl.ws-help`. An application's command is under Agents and applications. Empty groups are left out.
- `/help <name>` and `/yardım <ad>` (`ws_command_help_one()`): the name with or without its slash, folded (`ws_command_fold()`). The command alone: its label, description, usage, "Names: …" (every name), "Example: …".
- A name that is no command here: "There is no /x command." and "Did you mean:" with `ws_command_closest()`; the answer carries `closest`.

## What else carries the group?
- Each `ws_command_help()` entry: `group`, `group_label`, `where`. `ws_palette_commands()` rows: `group`, `group_label`, `where`. The "/" list's header rows and its filter by the open channel are CC2b in `assets/js/workspace.js`.

## What does a command typed as a reply do?
- `ws_command_acts_on_reply()`: a reply (`parent_id`) and nothing after the name. Then `ws_command_answered_message()` reads the answered message: this channel, not deleted, readable; else "That message could not be found.".
- /task: the task is made of that message, as its menu's "Make a task of it" does: the title its plain line (`ws_plain_line()`, 200 characters), the description its text only when it is the writer's own. The preview and Create stay as they are; the composer sends `parent_id` again with the confirmation.
- /decision, /note: `ws_message_mark()` marks it, with that function's refusals, as "Mark as a decision" / "Mark as a channel note".
- With a text, or without a reply, all three do as before.

## Tests?
- tests/workspace_command_help_test.php (gate by channel, refusal, groups in order, `/help task`, `/yardım görev`, unknown with closest, palette fields, the reply rule).
