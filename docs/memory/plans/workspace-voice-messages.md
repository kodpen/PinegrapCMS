---
name: workspace-voice-messages
description: Plan (2026-10-23, V1 done #1457, V2 done #1494, V1b done #1717, answers #2417, V3a #2674, V3b #2696): voice messages in the Workspace, a quick voice reply, transcripts the assistants read
---

## What is asked? (owner, 2026-10-23)
- Answer a voice recording with a quick voice recording; the assistants use voice messages too.

## What is there today?
- Recording, playing, the quick voice reply (#1457, #1494, #1717: workspace/workspace-voice-recording.md) and transcripts on the server side (#2674, #2696: workspace-voice-transcripts.md, workspace-voice-transcript-job.md).
- Assistants, webhooks, push, search and recaps (`ws_recap_transcript()`: `[file: name]`) read only `body`. Live dictation belongs to plans/workspace-composer-tools.md; erasure (plans/privacy-data-pack-audit-log.md step 4) covers recordings and transcripts. No new API scope.

## Slices
1. Done: V1 record, send, play (#1457); V1b `Range` 206 (#1717); V2 quick voice reply (#1494).
4. V3 transcripts (schema approved in #2417), three PRs, each after the previous merges:
   - V3a (#2674): schema 11.0.89 (`ws_voice`, `ws_channels.voice_access`, two config columns, texts in `config_text`), the settings card, `ws_voice_channel_allowed()`: workspace/workspace-voice-transcripts.md.
   - V3b (#2696): the row on a voice upload, `ws_voice_kick()`/`ws_voice_job()` (pinned HTTPS call, size cap, holds), delete for everyone, `file.voice` in the API and MCP. Server side only.
   - V3c (waits for #2545, which edits `assets/js/workspace.js`, `workspace.min.js` and `includes/workspace/actions.php`; claim once it has merged), about 400-600 product lines:
     - Channel switch: `ws_voice_channel_set($viewer, $channel, $allowed)` in voice.php as `ws_ai_channel_set()` (ai.php) does it (`ws_can_manage_channel()`, `voice_access` 1/2, a system line), action `ws_channel_voice` in actions.php beside `ws_channel_ai`, a `voice` part (allowed, can be asked) in the channel payload beside `channel.ai`, and the menu item in workspace.js beside `ai_allow` with a private-channel confirm that names `ws_voice_config()['provider']`; texts through `ws_voice_js_strings()`.
     - Transcript under the player: `PGWsVoice.player()` in `assets/js/workspace_voice.js` draws the text (marked as machine text, with provider) when the message's file carries it; the message payload needs it (the panel's file part, as `file.voice` in the API: `ws_voice_api_present()`).
     - Panel kick for servers without PHP-FPM: an action `ws_voice_kick` (add it to the session-free list in `includes/panel/actions.php` beside `ws_ai_kick`) the screen calls after a recording is sent and while a transcript is queued; returns `ws_voice_kick(1)`'s status.
     - Duration and peaks from the recorder: `ws_attach` takes them (bounded), `ws_voice_queue()` stores them; the player draws peaks.
     - `workspace.message.transcribed` (public channels only, with `channel_id`) from `ws_voice_run()` once done; its line in `workspace_flows_parity_table()` (tests/workspace_flows_parity_test.php) and the webhook catalogue: read `workspace-flows-parity-test.md` and `workspace-webhook-channel-groups.md` first. If that needs a `workspace_flow*` file, check no open PR holds it.
5. V4 assistants hear it: transcript in Pinegrap AI's context, `ws_api_claude_request_present()`, `ws_api_channel_context()`, recaps, push, search; a recording with typed tags (mic kept while the box holds only tags) or answering an assistant asks once transcribed.
6. V5 (optional) answers read aloud by `speechSynthesis`; V6 a recording in a note (`ws_note_file`).

## Which questions are answered?
- #2417: transcript in `ws_voice`; the site's own OpenAI-compatible key (a gateway preset later); private channels off until a manager allows; delete for everyone removes recording and transcript; cap 5 minutes.

## Where to start?
- V3c (above) once #2545 has merged; memory: workspace/workspace-voice-transcripts.md and workspace-voice-transcript-job.md.
