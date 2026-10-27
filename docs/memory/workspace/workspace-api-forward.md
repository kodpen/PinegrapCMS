---
name: workspace-api-forward
description: Forwarding messages over the Workspace API and MCP (POST /workspace/channels/{id}/forwards): the check shared with the panel, access, whose name it carries, why it is a tool
---

## What does the route do?
- `POST /workspace/channels/{id}/forwards` (`ws_api_messages_forward()`, api_resources.php): `id` is the channel the messages go to, `message_ids` (1-20, one source channel) what is forwarded, `text` the words above them. It writes what the panel's Forward writes (`ws_forward_to_channel()`, forward.php): a new message with a frozen copy of each in `ws_message_forwards`. Answers the new message (201).
- Forwarding into a note stays in the panel only; an application writes notes with the notes routes.

## Which checks does it meet?
- `ws_forward_to_channel_check()`, called by the panel's `ws_forward_to_channel()` and by the route before `api_dry_run_stop()`: the pick (`ws_forward_pick()`: one channel the person reads with `ws_can_read_channel()`, not deleted, people's or applications' messages), the target (`ws_can_post_channel()`, not archived, no guest room), then `ws_message_send_check()` on the words (length, the person's rate limit).
- Both channels also go through `ws_api_channel_or_404()`, so Claude's key forwards only between channels Claude may be asked in.
- Refusals: target not writable or a guest room 403, the messages or the words 422 on `message_ids` / `text`, tables missing 503.

## Why is it an ordinary write and an MCP tool?
- It changes nobody's words: the copies are frozen as they were, and the new message carries the writer's name like `workspace.messages.create`. So no `ws_api_own_words_only()`, no `ws_api_person_only()`: an application key or an OAuth connection forwards as the application (`ws_api_writer_app_id()`), a team devices session as the person. Scope `workspace:write`, already in `ws_owner_scopes()`.
- Webhook: `ws_message_send()` announces `workspace.message.created` with `channel_id` for a public target; no new event.
- Test: `test_ws_api_messages_forward_route` and the order row in `test_ws_api_rehearsal_stops_before_the_write` (tests/workspace_api_test.php).

## What is missing?
- `ws_api_message_present()` does not show a message's forwarded copies yet, so an application reading the channel sees the forward's own words only.
