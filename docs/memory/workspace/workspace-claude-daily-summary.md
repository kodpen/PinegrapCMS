---
name: workspace-claude-daily-summary
description: The daily summary Claude writes for the dashboard's Assistant card: its setting, how its request is queued and told apart, where the card finds the message, why config holds no message id
---

## What is it? (N11c, #5080)
- At an hour an administrator picks on the "Claude in the channels" card (Workspace settings), Claude writes three lines on what is waiting into a chosen channel; the new dashboard's `ai` card shows them above the proposals, each line opening the message. Off by default (`config.ws_claude_summary_hour` = 255, `WS_CLAUDE_SUMMARY_OFF`); architect issue #5014.
- Only channels Claude may be asked in are offered (`ws_claude_summary_channels()`); the card shows the summary only to people who read its channel.

## How is it queued and answered?
- `ws_claude_tick()` (hourly job and screen opens) calls `ws_claude_summary_queue()`: once the hour has passed today and no summary request exists since midnight, it inserts one `ws_ai_requests` row: the channel, `message_id` 0, `note_text` = `WS_CLAUDE_SUMMARY_MARK`, `requested_by` = the owner of Claude's application. It runs through `ws_claude_dispatch()` like any request, so the daily allowance and the hold apply.
- `ws_claude_is_summary_request()` tells it apart (no message, note, task or page, and the mark). The API shows it with `summary: true` and `message.text` = `ws_claude_summary_instruction()`; the routine's instructions say to answer with text only. The answer mentions nobody and refuses tasks and changes.

## Why is there no message id column?
- #5014 decided three `config` columns; the third (`ws_claude_summary_message_id`) took `config` past the room `tests/innodb_test.php` keeps for MySQL 8.0 (ten long columns). The card reads the latest answered summary request's `reply_message_id` in the chosen channel instead (`ws_claude_summary_config()`), so a summary from a channel chosen before is not shown. After 11.0.145 the room left is 1 byte: the next `config` column has to go to a table of its own.
