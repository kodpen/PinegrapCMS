---
name: workspace-app-lines-fold
description: Plan (owner 2026-11-09): a run of one-line messages from one application folds into one row with "+N lines" in a channel; each reader chooses folded, all or hidden; step AL1
---

## What is it for?
- A channel that an application writes into loses its conversation. On dev the agent channel held 283 messages on 2026-11-09 and 268 of them were CI lines. Those lines stay in that channel (owner, 2026-11-08), and a GitHub channel link (`api/api-github-channel-links.md`) does the same to any team's channel.
- Decision: `workflow/decisions-made-workspace-10.md`.

## What does the reader see?
- A run of three or more one-line messages in a row from the same application folds into one row: the newest line, and "+N lines", which opens the run in place and closes it again.
- Only plain lines fold. A message stays out, and ends the run, when it has more than one line, a file, a poll, an approval, a checklist, a decision or note mark, a pin, thread replies, an acknowledgement request or a task, or when it mentions the reader.
- Only an application that is no agent folds. Claude, Pinegrap AI, model assistants, MCP agents and registered agents (`ws_agents`) answer people and never fold.
- The channel's menu holds the reader's choice for that channel: Folded (the default), All or Hidden. Hidden shows every plain application line, a single one too, as a count row ("12 application lines") that opens.
- A link or a search hit that lands on a folded line opens its run. A line that arrives live joins the run on the screen.

## What does it not change?
- The stored messages, the API, the mobile API, unread counts, `last_read_id` and the bell: the browser draws the fold.
- The choice is kept in the browser per channel (`localStorage`, as `ws-pin-closed:` is). No schema; the choice does not follow the reader to another device.

## Which steps are there?
- AL1 (M, no schema) The fold, the count row and the choice. In `workspace.js` `drawMessages()`, `appendMessage()` and `messageNode()` draw the conversation; an application's message has `sender_kind` `app` and `sender.app_id` (`ws_app_sender()`, `messages.php`). A pure helper works out the runs from the loaded messages and is tested under node, as `tests/workspace_picker_fold_test.php` runs `match()`. If the payload cannot tell a mention of the reader or a registered agent, `ws_message_payload()` gains a flag for it. Texts through `lang()` and `php tools/tr_add.php`; `php tools/build_js.php` after the edit. Memory: `workspace/workspace-app-lines-fold.md`. Claim as `[plan:workspace-app-lines-fold] AL1 — application lines fold`.

## What is left for later?
- Gathering the lines of one pull request under its newest state: it needs a subject key on the message, a schema question of its own.

## Done when?
- AL1 merged; the lasting points go to `workspace/` and this file is deleted.
