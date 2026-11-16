---
name: channel-commands-census-engine
description: Census of the channel commands' engine (2026-11-16): the commands there are, the places one new command touches, where a command runs, what an application's command lacks, what a person can define
---

## Which commands are there?
- Tasks: /task, /assign, /done, /postpone, /status, /timer. Plan: /schedule, /away, /meeting, /plan. The channel's record: /decision, /note, /poll. Products: /price, /stock, /create product|group, /product. Agents: /wake, /agent-channel, and /claude, /ai once set up. /help. Each has Turkish and English names (`ws_command_names()`, `ws_command_create_names()`).
- An application's own commands come after them, at most twenty an application (`workspace/workspace-app-commands.md`).

## What does one new built-in command touch?
- Its names, its entry in the literal list of `ws_commands()`, a gate in `ws_command_offered()` when it is not everybody's, a `case` in the switch of `ws_command_run()`, a branch in `ws_command_suggest()` for a live line, `ws_api_command_kind()` when its first run writes nothing, the texts, and `tests/workspace_commands_test.php`, which holds the ordered list of keys.
- A new argument kind or list of values also touches `ws_command_argument_kinds()`, `ws_command_suggestions()` and `openArgument()` in `assets/js/workspace.js`; a new kind of answer `send()` there and `ws_api_command_preview_types()`.
- The / picker, /help, the palette, the API list and the closest-name hint read the registry, so they follow by themselves. There is no table a command is run from and no hook: the switch is the only path beside an application's command.
- `ws_command_offered($viewer, $command)` knows the person, not the channel: /agent-channel is listed in a discussion and refused only when run.

## Where does a command run, and where not?
- In a channel's box and in a discussion (`ws_send`), also as a reply: the run gets `parent_id`, which only /schedule, /create with a file and an application's command read. Over the API by the person's own devices session (`workspace/workspace-command-api.md`).
- Not while a message is edited, not in a scheduled message ("A command cannot be scheduled"), not in a mailbox room (nobody posts there), not in a note (its "/" lists the note's inserts, `workspace/workspace-notes-slash-inserts.md`), not by a guest (`ws_guest_clean_text()` takes the slash off).
- A customer channel is a public or private channel with a contact: every command runs there. A direct message is the panel's chat, not a channel.

## What does an application's command lack?
- Arguments (its `args` is empty: no popup, no values offered), a live line, a preview or a card, and an answer of its own: the writer reads "Sent to the application …" and the application posts an ordinary message.
- It reaches the application only as the webhook event `workspace.app.command`. An application agent that waits on its request queue (`workspace/agent-doors.md`) never hears a command typed for it: no request is made.

## What can a person of the team define?
- No command. A saved reply is typed as ";" and its shortcut and put into the box as text (`ws_saved_replies`, personal or the team's).
- A flow "Started by hand" is started from the box's Insert menu, "Start a flow", in a public or private channel (`ws_flows_runnable_in()`, `ws_flow_run_start()`); the start takes a channel and a contact, no typed values.
