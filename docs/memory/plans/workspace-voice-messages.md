---
name: workspace-voice-messages
description: Plan (2026-10-23): voice messages in the Workspace, a quick voice reply, transcripts the assistants read; V1-V3 done, V4a #2798, V4b #PR, V4c next
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
   - Done: V3c2 (#2775): the channel menu switch, the panel kick, the recorder's waveform and length, the transcript under the player: workspace/workspace-voice-screen.md.
   - The event is not a Flows trigger: that would need `ws_events_catalog()`, a `ws_event()` call and its `workspace_flows_parity_table()` line (tests/workspace_flows_parity_test.php); left for a later step.
5. V4 assistants hear it, three PRs, each after the previous merges:
   - Done: V4a (#2798): `ws_voice_transcripts()`, `ws_voice_with_transcript()`; the transcript in Pinegrap AI's context and request (`ws_ai_channel_context()`, `ws_ai_heard_text()`, the MCP ask), `ws_api_claude_request_present()` (`message.transcript`), `ws_api_channel_context()` (`messages[].transcript`), recaps: workspace/workspace-voice-assistants.md.
   - Done: V4b (#PR): push (the line a voice message sends reads its transcript once written out, or "Voice message") and search (`ws_voice.transcript` found as its message, same reach rules).
   - V4c: a recording with typed tags (the mic kept while the box holds only tags; the tags ride the upload as the body) and a voice reply to an assistant's answer: the assistant is asked once the transcript is done (from `ws_voice_run()`), or with "[voice message, not transcribed]" when it failed or transcripts are off.
6. V5 (optional) answers read aloud by `speechSynthesis`; V6 a recording in a note (`ws_note_file`).

## Which questions are answered?
- #2417: transcript in `ws_voice`; the site's own OpenAI-compatible key (a gateway preset later); private channels off until a manager allows; delete for everyone removes recording and transcript; cap 5 minutes.

## Where to start?
- V4c next; memory: workspace/workspace-voice-transcripts.md, workspace-voice-transcript-job.md and workspace-voice-screen.md.
