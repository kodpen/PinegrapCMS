---
name: workspace-app-lines-fold
description: A run of an application's one-line messages folds into its newest line with "+N lines"; the reader picks Folded, All or Hidden per channel in the browser
---

## What does the reader see?
- Three or more plain lines in a row from one application, on one day, show as the newest line (with its name again) and a "+N lines" row that opens the run in place; open, the row reads "Fold the lines".
- The channel menu holds the choice (Fold application lines, Show every application line, Hide application lines); Hidden turns every run, a single line too, into a count row ("12 application lines") that opens.
- The menu offers the choice when the loaded conversation holds a plain line, or the reader picked something other than Folded.
- Why: 268 of the agent channel's 283 messages on dev were CI lines, which stay in that channel (`workflow/decisions-made-workspace-10.md`).

## What is a plain line?
- `appLine()` in `assets/js/workspace.js`: `sender_kind` `app`, kind `message`, `sender.app_id` set, payload `one_line` true and `mentions_me` false.
- It stays out, and ends the run, with a file, poll, approval, read receipt, status card, checklist (`has_list`, `list_tasks`), task, thread replies, `parent_id`, scheduled card, note card, flow run, change/draft/design proposal, or when it is the channel's pin.
- Claude (`sender.claude`), Pinegrap AI (`sender.ai`), MCP agents (`sender.mcp`) and registered agents (`sender.agent`: any `ws_agents` row with that `app_id`, app agents and model assistants) never fold.
- A deleted message leaves no trace and does not break a run.

## Where does the server help?
- `ws_message_payload()` sets `one_line` and `mentions_me` for an application's message only, from `ws_app_line_flags()` (pure: no line break after trimming; `<@user:me>` or `<@dept:N>` of a department the reader is in, `ws_app_line_viewer_departments()` cached per request).
- `ws_app_sender()` adds `agent` from `ws_app_is_agent()`, cached with the sender.

## How is the fold drawn?
- `foldAppLines()` runs after `drawMessages()`, a live arrival, `replaceMessage()` and `removeMessageNode()`: it removes its rows, clears `.ws-msg-folded` / `.ws-msg-fold-head`, then folds again from `appLineRuns()`. The stored messages, the API, unread counts and the bell do not change.
- Opened runs are kept in `appLinesOpen` by message id, so a line arriving live joins an open run open; switching channel or choice clears it.
- `jumpTo()` and `open()` with a message id call `revealAppLine()`, which opens the run that holds it; the arrow keys skip folded lines.
- The choice is `localStorage` `ws-app-lines:<channel id>` (`all` / `hidden`; Folded removes the key); it does not follow the reader to another device.

## What is tested?
- `tests/workspace_app_lines_fold_test.php` runs `appLine()` and `appLineRuns()` under node and checks `ws_app_line_flags()`; `tests/workspace_channel_menu_test.php` lists the three menu keys.

## What was left for later?
- Gathering the lines of one pull request under its newest state needs a subject key on the message, a schema question of its own.
