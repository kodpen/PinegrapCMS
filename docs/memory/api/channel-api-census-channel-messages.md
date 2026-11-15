---
name: channel-api-census-channel-messages
description: CA1a census, part 1: a channel's messages, polls, threads, scheduled and forwarded messages - each panel action (actions.php case) beside its /workspace/ route and MCP tool, or none
---

## How is this read?
- `panel action (write function) -> route id`, from `includes/workspace/actions.php` and the route rows of `includes/workspace/api*.php` on 2026-11-15. "no tool" = the row carries `'mcp' => false`. Entry: `channel-api-census-channel.md`.

## Messages?
- `ws_send` (`ws_message_send`) -> `workspace.messages.create`; a reply through `parent_id`; a / command -> `workspace.commands.run` (no tool).
- `ws_edit` (`ws_message_edit`) -> `workspace.messages.update`, no tool, own words.
- `ws_delete` (`ws_message_delete`; "for me" `ws_message_hide`) -> `workspace.messages.delete` / `_post`, `workspace.messages.hide`; all no tool.
- `ws_react` (`ws_reaction_set`) -> `workspace.messages.react`. `ws_check` (`ws_check_set`) -> `workspace.messages.check`.
- `ws_mark` (`ws_message_mark`) -> `workspace.messages.mark`.
- `ws_pin` (`ws_channel_pin_message`) -> `workspace.messages.pin`; `ws_pin_hide` (`ws_channel_pin_hide`) -> `workspace.messages.pin_close`; both no tool.
- `ws_attach` (`ws_message_send_file_bytes`) -> `workspace.files.create`; voice -> `workspace.messages.voice`; download -> `workspace.files.get` (no tool) / `files.content`.
- `ws_library_attach` (`ws_library_send`, a file of the file manager): none.
- `ws_forward` to a channel (`ws_forward_to_channel`) -> `workspace.messages.forward`; to a note (`ws_forward_to_note`): none.
- `ws_ack_request` -> `workspace.messages.ack_request`; `ws_ack` -> `workspace.messages.ack`; `ws_ack_people` (`ws_ack_people`, who has read it and who not): none.
- `ws_check_task` (`ws_checklist_item_task`) -> `workspace.messages.item_task`.
- `ws_mention_invite` (`ws_message_mention_invite`): none. `ws_message_seen` (`ws_message_seen_by`): none.
- `ws_messages_before` -> `workspace.messages.list` (`before_id`). `ws_edit_hold` (`ws_edit_hold_beat`): none (screen lock).
- `ws_block_search` / `ws_block_pull` (`ws_blocks_search`, `ws_block_pull`): none; saved replies have `workspace.saved_replies.*`.

## Scheduled messages?
- `ws_scheduled_messages` -> `workspace.scheduled_messages.list`; `ws_scheduled_message_save` new -> `workspace.scheduled_messages.create` (no tool, own words); `ws_scheduled_message_delete` -> `workspace.scheduled_messages.delete`.
- Changing one (`ws_scheduled_message_save` with `id`), one in a reply (`parent_id`), "Send now" `ws_scheduled_message_send` (`ws_scheduled_execute`): none.

## Polls?
- `ws_poll_create` (`ws_poll_create`) -> `workspace.polls.create`: option parts (description, picture `file_id`, links, recommended, "Other") are in its `options`.
- `ws_poll_vote` (with "Other" text) -> `workspace.polls.vote`; `ws_poll_edit` -> `workspace.polls.update`; `ws_poll_withdraw` -> `workspace.polls.withdraw`; `ws_poll_again` (`ws_poll_ask_again`) -> `workspace.polls.again`; `ws_poll_close` -> `workspace.polls.close`. All five no tool.

## Threads (discussions)?
- `ws_thread_start` -> `workspace.threads.start`; list -> `workspace.threads.list`; `ws_thread_join` / `_leave` / `_follow` -> `workspace.threads.join` / `leave` / `follow` (no tool, person only).
- conclude `ws_thread_close` (`ws_thread_close`) and `ws_thread_rename` (`ws_thread_rename`): none.

## Approvals and status in a channel?
- `ws_approval_create` -> `workspace.approvals.create`; `ws_approval_decide` -> `workspace.approvals.decide` (no tool); take back or close `ws_approval_close` (`ws_approval_close`): none; `ws_approval_guest_resend`: none.
- `ws_status_post` (`ws_status_post`), `ws_checkin_start` (`ws_checkin_start`), `ws_checkin_answer` (`ws_checkin_answer`), all through `ws_status_action()`: none (a flow's checkin box asks one).
