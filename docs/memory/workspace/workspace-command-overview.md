---
name: workspace-command-overview
description: Start file of the / commands in a Workspace channel's writing box: what they are, what binds them, which workspace-command-* file holds each part, how a new command is added
---

## What are the / commands?
- A line starting with `/name` in a channel's writing box (or a discussion's) runs a built-in command instead of posting: tasks, the plan, the channel's own actions, site records, agents and applications. An application can add its own (`workspace-app-commands.md`).
- `includes/workspace/command_registry.php` holds every built-in entry (`ws_command_registry()`); `includes/workspace/commands.php` holds the parsers, gates, runners and live lines; the composer side is in `assets/js/workspace.js`.

## What binds them?
- Plan channel-commands (`plans/channel-commands.md`, owner 2026-11-16, architect message 6770): a command does what its button does, through the same function, with its rights and refusals. Incomplete, it opens the form its button opens, filled with what was typed. No new screen; Turkish and English names; a /help line; texts through `lang()`; an answer meant for the writer is `ephemeral`.
- Slash commands over the API: `decisions-made-product-11.md` (#3801).

## Which file holds what?
- The registry, an entry's fields, what a new command adds, shadowed application commands: `workspace-command-registry.md`.
- The "/" list, the Tab argument popup, unknown names and "//": `workspace-command-picker.md`. Groups, where a command runs, /help and commands typed as a reply: `workspace-command-help.md`.
- The live line under a command and the /task parser: `workspace-command-live-line.md`.
- /status: `workspace-command-status.md`. /schedule: `workspace-command-schedule.md`. /timer, /away, /meeting: `workspace-command-plan-items.md`. /remind, /recap: `workspace-command-remind-recap.md`. /approval, /update, /checkin: `workspace-command-approval-update-checkin.md`. /notify, /invite, /leave: `workspace-command-notify-invite-leave.md`. /topic, /search, /memory: `workspace-command-topic-search-memory.md`.
- /price, /stock: `workspace-record-commands.md`. /create, /product: `workspace-create-commands.md`. /task's preview card: `workspace-task-command.md`.
- Over the API: `workspace-command-api.md`. What other team chats do: `workspace-command-other-chats.md`.

## How is a new command added?
- An entry in its group's function of the registry, its runner in commands.php, a gate, live line or API class when needed, names in both languages, texts with `php tools/tr_add.php`, then the order and live lists in `tests/workspace_command_registry_test.php` and `tests/workspace_commands_test.php` (`workspace-command-registry.md`).
- A new kind of answer is handled in `send()` of workspace.js and listed in `ws_api_command_preview_types()` when it writes nothing.
