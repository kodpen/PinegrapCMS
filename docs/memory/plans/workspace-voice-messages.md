---
name: workspace-voice-messages
description: Plan (2026-10-23, V1 done #1457, V2 done #1494, V1b done #1717, answers #2417, V3a #2674, V3b #2696, V3c1 #2744): voice messages in the Workspace, a quick voice reply, transcripts the assistants read
---

## What is asked? (owner, 2026-10-23)
- Answer a voice recording with a quick voice recording; the assistants use voice messages too.

## What is there today?
- Recording, playing, the quick voice reply (#1457, #1494, #1717: workspace/workspace-voice-recording.md) and transcripts on the server side (#2674, #2696: workspace-voice-transcripts.md, workspace-voice-transcript-job.md).
- Assistants, push, search and recaps (`ws_recap_transcript()`: `[file: name]`) read only `body`. Live dictation belongs to plans/workspace-composer-tools.md; erasure (core/personal-data-erasure.md) covers recordings and transcripts. No new API scope.

## Slices
1. Done: V1 record, send, play (#1457); V1b `Range` 206 (#1717); V2 quick voice reply (#1494).
4. V3 transcripts (schema approved in #2417), four PRs, each after the previous merges:
   - V3a (#2674): schema 11.0.89 (`ws_voice`, `ws_channels.voice_access`, two config columns, texts in `config_text`), the settings card, `ws_voice_channel_allowed()`: workspace/workspace-voice-transcripts.md.
   - V3b (#2696): the row on a voice upload, `ws_voice_kick()`/`ws_voice_job()` (pinned HTTPS call, size cap, holds), delete for everyone, `file.voice` in the API and MCP. Server side only.
   - Done: V3c1 (#2744): server side of V3c (`channel.voice`, `ws_voice_channel_set()`, `file.voice` in the panel payload, the recorder's measure stored, `workspace.message.transcribed`): workspace/workspace-voice-transcript-job.md.
   - V3c2 (panel; waits for V3c1 and for #2730, which holds `assets/js/workspace.js`, `workspace.min.js`, `includes/workspace/actions.php` and `includes/panel/actions.php`), about 300-450 product lines:
     - Action `ws_channel_voice` in actions.php beside `ws_channel_ai` (`ws_action_channel(..., 'manage')`, then `ws_voice_channel_set()`); the menu item in workspace.js beside `ai_allow`, shown while `channel.voice` is set and the reader `can_manage`, a private-channel confirm naming `channel.voice.provider`; texts through `ws_voice_js_strings()`.
     - Action `ws_voice_kick` (in the `pg_panel_actions()` list of `includes/panel/actions.php` beside `ws_ai_kick`) returning `ws_voice_kick(1)`'s status; the screen calls it after `voiceSend()` posted and from `schedule()` while a shown message's `file.voice.status` is queued or running (throttled like `kickAi()`), then `sync()`.
     - `ws_attach` passes `voice` (`duration_ms`, `peaks` from the request) in the options of `ws_message_send_file()`; the recorder (`workspace_voice.js`) measures levels with an `AnalyserNode` and sends at most `WS_VOICE_PEAKS` (120) digits 0-9.
     - `PGWsVoice.player()` draws the peaks as the seek line, the stored length before `loadedmetadata`, the transcript under it marked as machine text with its provider; queued/running says so. Then `php tools/build_js.php`.
   - The event is not a Flows trigger: that would need `ws_events_catalog()`, a `ws_event()` call and its `workspace_flows_parity_table()` line (tests/workspace_flows_parity_test.php); left for a later step.
5. V4 assistants hear it: transcript in Pinegrap AI's context, `ws_api_claude_request_present()`, `ws_api_channel_context()`, recaps, push, search; a recording with typed tags (mic kept while the box holds only tags) or answering an assistant asks once transcribed.
6. V5 (optional) answers read aloud by `speechSynthesis`; V6 a recording in a note (`ws_note_file`).

## Which questions are answered?
- #2417: transcript in `ws_voice`; the site's own OpenAI-compatible key (a gateway preset later); private channels off until a manager allows; delete for everyone removes recording and transcript; cap 5 minutes.

## Where to start?
- V3c2 (above) once V3c1 and #2730 have merged; memory: workspace/workspace-voice-transcripts.md and workspace-voice-transcript-job.md.
