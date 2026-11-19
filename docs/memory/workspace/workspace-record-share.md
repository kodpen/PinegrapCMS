---
name: workspace-record-share
description: The record drawer's "Share in a channel" posts its line as a message (ws_send as_text), never a / command, and reports a share only on a message id (#9983)
---

## What does "Share in a channel" in a record's Workspace drawer send?
- `openRecord()` in assets/js/workspace.js calls `ws_send` with `body` = the line, a space and the record's token, and `as_text: 1`.
- "Shared in the channel." shows only when the answer carries `message_id`; any other answer shows the generic error.

## What does as_text do in ws_send?
- includes/workspace/actions.php: with `as_text` the body is posted as written. The /claude and /ai rewrites, `ws_command_run()` and the "//word" unescape (`ws_command_unescape()`) are skipped. @mentions in the line still ask assistants, as in any message.
- Without it the composer's path is unchanged (workspace-command-registry.md).

## Why?
- #9983: a line starting with "/word" ran as a command with the record's token as its last word. Nothing was posted (a `/görev …` line made a task), yet the drawer said "Shared in the channel.".

## Tests?
- tests/workspace_record_share_test.php.
