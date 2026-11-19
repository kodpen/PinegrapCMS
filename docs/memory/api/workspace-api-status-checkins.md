---
name: workspace-api-status-checkins
description: Workspace API for a channel's status update and check-ins - POST …/status, POST …/checkins, POST /workspace/checkins/{id}/answer - who may call each and the checks shared with the panel
---

## Which routes are there?
- `workspace.channels.status`, `POST /workspace/channels/{id}/status` (`ws_api_channel_status_post()`, api_threads.php). It writes Status update from the channel menu: `health` (on_track, at_risk or off_track) and `text`. It answers the message with 201 (#9825).
- `workspace.checkins.start`, `POST /workspace/channels/{id}/checkins`. It asks the channel `question`; when that is left out, the panel's own question (`ws_checkin_default_question()`) is used. It answers a WorkspaceCheckin with 201.
- `workspace.checkins.answer`, `POST /workspace/checkins/{id}/answer`. `text` replaces the person's answer, and an empty text takes it back. It answers the WorkspaceCheckin.
- WorkspaceCheckin is shaped from the card the channel shows under the message (`ws_status_cards_map()`, shaper `ws_api_checkin_shape()`): {id, message_id, channel_id, question, answers [{user_id, name, text}], waiting (names), count, total, mine, can_answer}. The message object itself carries no check-in card.

## Who may call them?
- The status update and starting a check-in are ordinary writes and MCP tools, like `workspace.messages.create`. `ws_api_message_writer()` passes `app_id` / `via_client_id` into `ws_status_post()` and `ws_checkin_start()`. So an application key writes as the application, and a team devices session writes as the person.
- An answer is person-only: `ws_api_person_only()`, `'mcp' => false`. An application key gets 403; a device session or an OAuth connection may answer.

## Which checks do they share with the panel?
- `ws_status_post_check()`, `ws_checkin_start_check()` and `ws_checkin_answer_check()` (status.php) hold the rules. Each panel write calls its check before writing, and the API calls it before `api_dry_run_stop()`.
- The status and start checks refuse a discussion with `ws_status_thread_refusal()`, which the channel-screen actions (`ws_status_action()`) also use, then run `ws_message_send_check()` on the message that would be written. The API gives a discussion 422.
- `ws_checkin_answer_check()` returns `reason`: missing (404), forbidden (may read but not post, 403) or invalid (an earlier version of the conversation, or longer than `WS_CHECKIN_ANSWER_MAX`; 422).
- The flows' check-in box calls `ws_checkin_start()` too, with the same signature plus an optional `$via_client_id`.
- Not built: the draft the status form offers (`ws_status_facts()`, `ws_status_draft()`); only the write is in the API.
- Tests: tests/workspace_api_status_checkins_test.php.
