---
name: workspace-voice-messages
description: Plan (2026-10-23, V1 done #1457, V2 done #1494, V1b done #1717, answers #2417, V3a #2674, V3b #2696): voice messages in the Workspace, a quick voice reply, transcripts the assistants read
---

## What is asked? (owner, 2026-10-23)
- Answer a voice recording with a quick voice recording; the assistants use voice messages too.

## What is there today?
- Recording, playing and the quick voice reply are built (#1457, #1494): workspace/workspace-voice-recording.md. Uploads never call `ws_message_ask_assistants()`.
- Assistants, MCP, webhooks, push, search and recaps (`ws_recap_transcript()`: `[file: name]`) read only `body`.
- get_file.php answers `Range` with 206 (#1717, platform/get-file-byte-ranges.md).
- Not blocked: `Permissions-Policy: microphone=(self)` (`waf_send_security_headers()`, waf.php), keep `(self)`; CSP `media-src` has `blob:`.

## What is proposed? (answered in #2417)
- A voice message: a message with an audio file, by the existing upload path and read rules (no site member). Mic in place of Send while the box is empty; vanilla `assets/js/workspace_voice.js` (proposed) in `workspace.min.js`; labels via `ws_voice_js_strings()` (proposed), keys added with `php tools/tr_add.php`.
- Transcription: admin switch, off by default, named provider ("audio is sent to OpenAI"), sealed key; a private channel only where its manager allowed (like `ai_access`); `pg_audit()` line. One OpenAI-compatible `/audio/transcriptions` call (`pg_curl_tls()`, `pinegrap_user_agent()`); the provider card of plans/workspace-agent-handles.md step 3 if built first.
- Owns recordings and transcripts; plans/workspace-composer-tools.md (in writing) owns live dictation. Erasure (plans/privacy-data-pack-audit-log.md step 4) covers both.
- Each slice ships its API; no new scope (one would go into `ws_owner_scopes()`).

## Slices
1. Done (#1457): V1 record, send, play (no schema; `files.create` takes them already).
2. Done (#1717): V1b `Range` 206 in get_file.php (no schema, no `lang()`).
3. Done (#1494): V2 quick voice reply (no schema): a mic on a voice message (tap: a phone's long press opens the menu) sends with `parent_id`; `ws_attach` and `files.create` (dry run too) learn it; quote "Voice message".
4. V3 transcripts (schema approved in #2417), three PRs, each after the previous merges:
   - V3a (#2674): schema 11.0.89 (`ws_voice`, `ws_channels.voice_access`, two config columns, texts in `config_text`), the settings card, `ws_voice_channel_allowed()`: workspace/workspace-voice-transcripts.md.
   - V3b (#2696): the row on a voice upload, `ws_voice_kick()`/`ws_voice_job()` (pinned HTTPS call, size cap, holds), delete for everyone, `file.voice` in the API and MCP. Server side only: draft #2545 holds `workspace.js` and `actions.php`.
   - V3c (after #2545 lands or with it): the channel menu switch (`ws_channel_voice`, private-channel consent naming the provider), the transcript under the player, a panel kick for servers without PHP-FPM, duration and peaks from the recorder, `workspace.message.transcribed` with `channel_id`, public channels only.
5. V4 assistants hear it: transcript in Pinegrap AI's context, `ws_api_claude_request_present()`, `ws_api_channel_context()`, recaps, push, search; a recording with typed tags (mic kept while the box holds only tags) or answering an assistant asks once transcribed.
6. V5 (optional) answers read aloud by `speechSynthesis`; V6 a recording in a note (`ws_note_file`).

## Which questions are answered? (#2417)
1. Transcript in `ws_voice`, not in `body`.
2. First provider: the site's own key (OpenAI-compatible), the gateway as a preset later.
3. Private channels: off until a manager allows (a `ws_channels` column, in the V3 step).
4. Delete for everyone removes the recording and the transcript (a discussion copy that shares the id is re-pointed).
5. Cap 5 minutes.

## Where to start?
- V3c once V3b has merged and #2545 no longer holds `workspace.js` and `actions.php`.
