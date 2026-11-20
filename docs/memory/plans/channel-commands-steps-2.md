---
name: channel-commands-steps-2
description: Plan channel-commands, steps CC7 to CC12 and the close-out: /oluştur for a contact and a row, an ERP account and an event, /kargo, application arguments (a column), an agent's command
---

## Which steps follow?
- CC7 (M) after CC2: /oluştur kişi and /oluştur satır. Done: #10187, #10213.
  - Existing: `includes/workspace/quick_edit.php` (`ws_quick_create_types()`), `includes/workspace/changes.php` (the contact is in the catalogue), `ws_command_create_names()`, the drawer's new mode in `assets/js/workspace.js`.
  - Tests: a contact's required list; a person without the contacts right neither offered nor let through; a row of a table the person may not write refused; nothing written before Save; "All fields".
  - Pictures: the two cards.
  - Features: K21, K22.

- CC8 (M) after CC7: /oluştur cari and /oluştur etkinlik. Tests: not offered with the ERP module off; each kind's right. Pictures: the two cards. Features: K23, K24. Done: #10262.

- CC9 (S) after CC7: /kargo. Done: #10300.
  - Tests: the card shows the old numbers beside the new; Save writes `tracking_numbers` only if the value is still the one the card showed; no mail is sent; the order right.
  - Picture: the card. Feature: K25.

- CC10 (M) after CC2: application arguments. Done: #10322. Schema, the owner's yes of poll 97.
  - The draft PR's body gives the column (name, type, default) and the definition's shape; the architect confirms them there before the code. One migration step in the open version with `install_add_column()`, run twice on a development database.
  - Existing: `includes/workspace/app_commands.php`, the API call that registers a command, the event's payload in `includes/workspace/api.php`, the argument list of `assets/js/workspace.js`.
  - Tests: an unknown kind and one argument too many refused; the values in the event by name; a command without a definition as today; the limit of twenty commands unchanged.
  - Pictures: an application command's strip and argument list. The PR body names the `[SCHEME]` item.
  - Memory: `workspace/workspace-app-commands.md`. Feature: K26.

- CC11 (M) after CC10: an agent's command. Done: #10586.
  - Existing: `includes/workspace/app_commands.php`, `includes/workspace/agent_queue.php` (the request is made as a mention makes it).
  - Tests: a command typed for an agent that waits on its queue posts the line as the writer's message and makes one request with the command and values in its brief; an agent not allowed in the channel not offered; the loop guard and the braked line; an application without an agent unchanged; the webhook event sent once.
  - Picture: the line, the agent's line under it.
  - Memory: `workspace/agent-doors.md`. Feature: K27.

- CC12 (S, no code) after CC11: census for the writer-only answer, one plans memory file (channel-commands-answer-census) answering K28 from the code. Done: #10636. It ends with a shape for the architect; a table or a column in it is asked of the owner by the architect. CC12b builds what was confirmed.

- Close-out, with the last step that lands: every K number checked against the code with its PR; `workspace/workspace-command-registry.md`, `workspace/workspace-app-commands.md` and `workspace/workspace-command-api.md` current; this plan's files deleted.

## Which steps wait for each other?
- CC1, then CC2. After CC2 the steps CC3 to CC7 and CC10 may run side by side, at most two at a time, and never two that edit the same function of `assets/js/workspace.js` or the same group function of the registry (PR Merge, agent channel 8073, 8076).
- CC8 and CC9 after CC7; CC11 after CC10; CC12 after CC11.
- A step that meets another plan's open PR on `assets/js/workspace.js` waits for it and says so in the agent channel.

## What is outside the plan but near it?
- Two bugs the census found are issues of their own: #9982 (the "/" list opens while a message is edited) and #9983 (the record drawer's "Share in a channel" says shared for a line that starts with a slash). They do not wait for CC1.
