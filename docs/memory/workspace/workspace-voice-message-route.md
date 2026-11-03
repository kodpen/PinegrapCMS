---
name: workspace-voice-message-route
description: "Send a voice message" from a text (VA5): POST /workspace/messages/voice and its MCP tool, ws_voice_message_check() and _send(), refusal codes, Pinegrap AI tool, Claude rule
---

## What does the route do?
- `POST /workspace/messages/voice` (`workspace.messages.voice`, `workspace:write`, handler `ws_api_messages_voice()` in api_resources.php, `'dry_run' => true`, answers the `WorkspaceMessage`, 201). Body: `channel_id` or `thread_id` (one of them, each of its kind: `ws_voice_message_target_rule()`, pure, 422 on the wrong one), `text` (required, at most 4000), `reply_to` (a message of that channel or discussion, `ws_message_parent_check()`), `voice` (a voice of the service in place of the card's, `ws_voice_speech_voice_valid()`).
- The site speaks the text with the speech service (workspace-voice-speech.md) and posts the sound as a voice message with no words of its own, as a recording is posted; the text is its `done` `ws_voice` transcript, so the player, search, recaps and bells read it. Who writes follows messages.create (`ws_api_writer_app_id()`: the application, or the person from a team devices session with their limit).
- As an MCP tool it is `workspace_messages_voice`, made from the route (`api/api-mcp-server.md`): the Claude and ChatGPT connectors get it with the write scope.

## Which checks, and which refusals?
- `ws_voice_message_check($viewer, $channel, $text, $options)` (voice.php; options `app_id`, `parent_id`, `voice`), shared by the route, its dry run and Pinegrap AI's tool, in this order: the service ready (`ws_voice_speech_missing()`), else code `voice_unavailable`; the channel not keeping its recordings (`ws_voice_channel_allowed()`), else `voice_closed`; the voice's name (422 `voice`); something to say once tags, code blocks and marks are out (`ws_voice_answer_text()` then `ws_voice_speech_cut()`, 422 `text`); `ws_message_send_check()` with the file placeholder (the person's limit, 422 `text`); the answered message (422 `reply_to`). Answers `text` (what will be spoken) and `voice`.
- `ws_voice_message_status($code)` (pure): `voice_unavailable` and `voice_closed` 409, `voice_failed` 503, anything else 422. `ws_api_voice_message_refuse()` turns a refusal into the API answer.
- `ws_voice_message_send()`: the check, `ws_voice_speak($text, locale, $voice)` (the locale from `ws_voice_answer_locale()`; a refusal is `voice_failed`, nothing posted), `ws_voice_speak_store()` (the file in the channel's folder, owner the writer), `ws_message_send()` with the file, `parent_id` and `app_id`, then `ws_voice_speak_row()` (the done row, the message touched). `ws_voice_speak_keep()` (VA4) is now store + the message's file + row.
- The dry run stops after the check, before the service is called: its detail gives `characters`, `voice` and `provider`.

## Who offers it?
- Pinegrap AI: tool `send_voice_message` (`text`, optional `voice`) in `ws_ai_tools()`, only for a channel request while `ws_voice_speech_ready()`; handled in `ws_ai_tool_run()` through `ws_voice_message_send()` with the assistant's writer (`ws_assistant_writer()`), the application's `app_id` and the request's message as `parent_id`. Its description asks for it only when the request asks for a voice message in so many words, and for a text answer still.
- Claude's routine instructions (`ws_claude_routine_prompt()`, the Rules): the route with `channel_id`/`thread_id`, `text` and `reply_to`, plain sentences, then the answer as usual; a 409 `voice_unavailable` means no speech service, then words. The bootstrap version is unchanged (the routine text is served by the site).

## What was checked?
- tests/workspace_voice_message_test.php: the route and its tool arguments, the target rule, the statuses, the handler's order (check, stop, write), the shared write, Pinegrap AI's tool named/offered/handled, Claude's rule. Not run against a speech service, and no connector or routine tried it live.
