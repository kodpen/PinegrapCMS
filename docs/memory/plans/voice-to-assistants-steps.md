---
name: voice-to-assistants-steps
description: Steps VA1-VA6 of plans/voice-to-assistants.md: deaf hint, keyless hearing, speech service, spoken answers, voice message route, MCP agents; claim as [plan:voice-to-assistants] VA<n>
---

## Which run side by side?
- Wave 1: VA1, VA3 and VA6, which touch different files. VA2 is free (question 1 decided in #5390). Wave 2: VA4 and VA5 after VA3; VA4 also needed question 2, decided in #5390. The last step to land moves the decisions to `workspace/` and deletes both plan files.
- S is under 400 product lines, M about 1,000. Pure parts get `tests/workspace_voice_*_test.php`. Texts go in only through `tr_add.php`. Run `php tools/build_js.php` after editing workspace.js. Changelog: one `[NEW]` line for voice answers and voice messages from assistants, named in VA4's PR body.

## Which steps are there?
- VA1 (S) Say why nobody heard. Done, #5709 (`workspace/workspace-voice-unheard.md`).
  - While transcripts are off or the channel closes them, the recorder says that assistants will not hear the recording. Role 0 gets a link to Workspace Settings › Voice message transcripts.
  - `ws_voice_heard()` writes the reason (off, failed or skipped). Claude's instructions and Pinegrap AI's prompt answer with that reason and the setting's place.

- VA2 (M) Hearing without a key. Question 1 is decided (#5390): the browser route.
  - (c): with no service set, `PGVoiceText` writes the sender's recording out on the device before the upload and sends it as the transcript (`provider` browser). The server keeps it only for a recorder file, as machine text. A browser without the model gets VA1's hint.
  - (b): the voice card offers "Pinegrap AI" on the subscription key.

- VA3 (M) Speech service. Done, #5715.
  - The voice card gets a speech part: OpenAI-compatible `/audio/speech`, with the same address and key by default, a model and a voice from a list, stored as `config_text` rows. The consent line names the provider.
  - `ws_voice_speak($text, $locale)` is a pure request builder plus `api_http_request()`. It returns a file kept like a recording: a `voice-…` name and a `done` `ws_voice` row whose transcript is the text. Text over 1,500 characters is cut at a sentence end.

- VA4 (M) Spoken answers. Done, #5805 (`workspace/workspace-voice-spoken-answers.md`).
  - Where an assistant's answer is written (Claude's answer route, Pinegrap AI, MCP agents), an answer to a recording gets its sound from `ws_voice_speak()` on the same message.
  - Without a speech service, the asker's browser reads the answer aloud (`speakAnswer()`). This applies in channels and discussions alike, and the answer's text stays as it is.

- VA5 (M) "Send a voice message". Done, #5918 (`workspace/workspace-voice-message-route.md`).
  - `POST /workspace/messages/voice` and its MCP tool take `channel_id` or `thread_id`, `text`, and optionally `reply_to` and `voice`.
  - The route has a dry run, a `ws_voice_message_check()` shared with the panel and the `workspace:write` scope. It answers `voice_unavailable` when no speech service is set.
  - Claude's instructions, Pinegrap AI's tools and the site's MCP server (the Claude and ChatGPT connectors) offer it. `workspace-api-endpoint-rules.md` applies.

- VA6 (S) MCP agents by voice. Done, #5802 (`workspace/workspace-voice-mcp-agents.md`).
  - `voiceAnswers()` also answers an MCP agent's message, and `ws_voice_ask()` asks the agent whose request the reply continues. Tests: the microphone on an agent's message, the ask after the transcript, and a discussion.
