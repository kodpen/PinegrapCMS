---
name: bug-hunt-channel-commands-server
description: Bug hunt of the channel commands' server side (commands.php, command_registry.php, app_commands.php, quick_edit.php, changes.php): the three faults fixed, the issue opened, what was read clean
---

## Which area was hunted?
- Architect 2026-11-20, agent channel message 10779: the server side of the channel commands, `includes/workspace/commands.php`, `command_registry.php`, `app_commands.php`, `quick_edit.php`, `changes.php`. Outside: `includes/workspace/api.php` and `assets/js/workspace.js`. PR #10769.

## What did it fix? (the PR lists each with file and line)
- A contact edited in the record drawer with "Keep it in this channel as a decision" on posted "Contact was changed. E-mail: “old” → “new”" to every member, also those without the contacts right. `ws_quick_edit_decision_body()` names a contact by its tag alone, as a new contact's decision does.
- "/help topic" in a discussion answered "There is no /topic command. Did you mean: /topic", and an application command refused in a channel (its application may not read it, its agent is not allowed there) was answered "Did you mean" the same word. `ws_command_closest()` takes the channel and never names the word typed.
- An application command whose definition was kept by a later change that sent no usage lost its argument line in /help and the picker. `ws_app_command_entry()` writes it from the labels when none is stored.

## What was opened as an issue?
- #10778: an applied proposal's decision (`ws_change_apply()`) still prints a contact's values; the delete wording needs a choice.

## What was read clean?
- Who runs a command: the panel's `ws_send` and `POST /workspace/commands/run` ask `ws_can_post_channel()` first; guests never reach `ws_command_run()`; every rights gate is asked again by its runner or the function it writes through (/invite, /recap, /flow, /memory, /price, /stock, /tracking, /create, /schedule, /topic, /notify, /leave).
- An application's command: offered and sent only while its key works and its owner is a member (`ws_app_commands_live()`), where the owner reads the channel, an agent's only where the agent is allowed; names folded against built-ins at registration; a missing required argument sends nothing.
- Quick edit: kind, right, Recycle Bin and stock right checked before anything is written; the stale check in each field's shape; the history row and log line in the person's name. A person's edit has no Undo (only an agent's applied change has, `agent-risk-tiers.md`).
- The parsers probed with edge input: amounts with marks and currency, quantities, /schedule times, application argument values.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workspace/workspace-quick-edit.md, docs/memory/workspace/workspace-app-commands.md
