---
name: channel-api-census-channel-gaps
description: CA1a gap list, part 1: a channel's messages, threads, approvals and the channel itself - each panel action with no route, the route it would be and the function that already does the write
---

## How is this read?
- `panel action -> the route it would be (function that writes)`. Route shapes are proposals for the architect, who cuts the steps; every gap is from the census parts (`channel-api-census-channel.md`). "person" = the person's own answer (`ws_api_person_only()`), "own words" = `ws_api_own_words_only()`, no tool (`workspace/workspace-api-callers.md`).

## Messages?
- G1 Forward to a note -> `note_id` on `workspace.messages.forward` (`ws_forward_to_note`).
- G2 Post a file of the file manager -> `POST /workspace/channels/{id}/library-files` (`ws_library_send`).
- G3 Who has seen / acknowledged a message -> `GET /workspace/messages/{id}/readers` (`ws_message_seen_by`, `ws_ack_people`).
- G4 Bring the mentioned people in -> `POST /workspace/messages/{id}/mention-invite` (`ws_message_mention_invite`).
- G5 Saved blocks: search and pull -> `GET /workspace/blocks`, `POST /workspace/blocks/pull` (`ws_blocks_search`, `ws_block_pull`).

## Scheduled messages?
- G6 Change one -> `POST /workspace/scheduled-messages/{id}`, own words (`ws_scheduled_message_save` with `id`).
- G7 Schedule a reply -> `parent_id` on `workspace.scheduled_messages.create` (`ws_scheduled_message_save`).
- G8 Send now -> `POST /workspace/scheduled-messages/{id}/send`, own words (`ws_scheduled_execute`).

## Threads, approvals, status?
- G9 Conclude a discussion -> `POST /workspace/threads/{id}/close` (`ws_thread_close`).
- G10 Rename a discussion -> `POST /workspace/threads/{id}` (`ws_thread_rename`).
- G11 Take back or close an approval -> `POST /workspace/approvals/{id}/close` (`ws_approval_close`).
- G12 Post a status update -> `POST /workspace/channels/{id}/status` (`ws_status_post`; draft `ws_status_facts`).
- G13 Start a check-in, answer one -> `POST /workspace/channels/{id}/checkins`, `POST /workspace/checkins/{id}/answer`, person (`ws_checkin_start`, `ws_checkin_answer`).

## The channel itself?
- G14 One channel's detail -> `GET /workspace/channels/{id}` (`ws_channel_detail`).
- G15 Make private / public -> `POST /workspace/channels/{id}/kind` (`ws_channel_make_private`, `ws_channel_make_public`); an access write, so it names who leaves and comes in (`workspace/workspace-api-layout-writes.md`).
- G16 Claude, Pinegrap AI and voice-transcript switches -> fields on `workspace.channels.update` and the channel object (`ws_claude_channel_set`, `ws_ai_channel_set`, `ws_voice_channel_set`).
- G17 Records watch switch -> `watch` on `workspace.channels.update` (`ws_channel_update`, `ws_watch_channel_state`).
- G18 Summary, read and write -> `summary` on the channel, `POST /workspace/channels/{id}/summary` (`ws_channel_set_summary`).
- G19 Versions: list, clear the conversation, rename one -> `GET /workspace/channels/{id}/eras`, `POST …/clear`, `POST …/eras/{n}` (`ws_channel_eras`, `ws_channel_clear`, `ws_channel_era_rename`).
- G20 Join a public channel -> `POST /workspace/channels/{id}/join` (`ws_channel_join`).
- G21 Save a channel as a template -> `POST /workspace/channels/{id}/template` (`ws_template_from_channel`).
- G22 Customer follow-up -> `POST /workspace/channels/{id}/customer/followup` (`ws_customer_followup_open`).
- G23 Files tab, Board tab -> `GET /workspace/channels/{id}/files`, `…/board` (`ws_channel_files`, `ws_channel_board`).
- G24 Export -> `GET /workspace/channels/{id}/export` (`ws_channel_export_collect`, `ws_channel_export_markdown`).
- G25 An administrator opens a private channel -> `POST /workspace/channels/{id}/audit` (`ws_channel_audit_open`, list `ws_private_channels_for_audit`): it grants reading, so whether it is built is the architect's.
