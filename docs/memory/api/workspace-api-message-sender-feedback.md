---
name: workspace-api-message-sender-feedback
description: Workspace API for a team device client: app_id, agent, claude, ai on an app sender; feedback on an agent's reply; POST /workspace/messages/{id}/agent-feedback
---

## What does an application's sender carry?
- On `sender_kind` app, `WorkspaceMessageSender` is `{user_id: null, name, app_id, agent, claude, ai}` (`ws_api_message_app_sender()`, pure, from the cached `ws_app_sender()`; #10075, PR #10385). Every other sender keeps `{user_id, name}`; the schema's extra keys are optional.
- Why: the panel folds a run of an application's one-line messages by `sender.app_id` and never folds a registered agent, Claude or Pinegrap AI (`workspace/workspace-app-lines-fold.md`). Without these keys a client could only key runs by the display name.
- Only the message presenter (`ws_api_message_present()`: channel messages, thread routes, `/workspace/sync`) gives them; a forward's sender, a decision's sender and `marked_by` stay `{user_id, name}`.

## What is feedback on a message?
- `feedback` on `WorkspaceMessage` (`WorkspaceMessageFeedback?`): `{verdict, reason}` exactly as `ws_agent_feedback_offer()` gives it through `ws_agent_feedback_map()`, `verdict` '' while unmarked; null for anybody but the asker and the channel's managers, on every other message and on a deleted one (#10340, PR #10385).
- `ws_api_messages_present()` reads the map once for a page and hands it to the presenter; a single message reads its own.

## How does a person mark an agent's answer over the API?
- `POST /workspace/messages/{id}/agent-feedback` (`workspace.messages.agent_feedback`, `workspace:write`, `'mcp' => false`, `dry_run`; row beside the readers route in `api.php`, handler `ws_api_messages_agent_feedback()` in `api_resources.php`).
- Body: `verdict` right / wrong, nullable and required (null or '' takes the mark back); `reason` at most `WS_AGENT_FEEDBACK_REASON_MAX`.
- Order: `ws_api_own_words_only()` (a team devices session only: key and OAuth get 403, a mark is a person's judgement), 503 before step 11.0.218, `ws_agent_feedback_check()`, `api_dry_run_stop()`, then the one writer `ws_agent_feedback_mark()`. `ws_api_agent_feedback_refusal()` answers the writer's 404 / 403 / 422 (with field) / 503 as they are.
- Answer `{feedback: {verdict, reason}}`, as the panel action does.

## Tests?
- `tests/workspace_agent_feedback_test.php` (route, order, refusals, the message key), `tests/workspace_api_message_people_test.php` (`test_ws_api_message_app_sender`).
