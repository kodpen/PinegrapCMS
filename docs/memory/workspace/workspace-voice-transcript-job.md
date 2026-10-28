---
name: workspace-voice-transcript-job
description: Voice message transcripts at work (V3b #2696, V3c1 #2744): queue, ws_voice_kick(), answers and holds, delete for everyone, file.voice, channel switch, workspace.message.transcribed
---

## How is a recording written out? (V3b, #2696)
- Both uploads (`ws_message_send_file_bytes()`, the API's files.create) call `ws_voice_queue()`: only a recorder name (`ws_voice_is_recording()`: `voice-….weba/ogg/m4a…`) gets a row, `queued` when transcripts are on and the channel allows them, else '' (never asked).
- `ws_voice_carry_on()` runs `ws_voice_kick()` after the answer went back where PHP-FPM can (`fastcgi_finish_request()`, session closed first); elsewhere (IIS, mod_php) `ws_voice_job()` in workspace_recurring_job.php, hourly, until V3c gives the panel a kick.
- `ws_voice_kick()`: one call at a time (`GET_LOCK('pg_ws_voice_run')`), budget 60 s (job 300 s), watchdog (queued over a day → failed, running over 15 min → queued again or failed after 3 attempts). `ws_voice_run()` re-checks the message, the channel rule and the size (`WS_VOICE_MAX_BYTES` 6 MB), then `ws_voice_call()`: multipart (`ws_voice_multipart()`) through `api_http_request()` (pinned address, no internal hosts), `response_format=json`, 120 s.
- `ws_voice_answer()`: 200 with `text` → done (text, language, provider, model), `ws_message_touch()`; 401/402/403 → the whole queue held an hour, the card shows the error, the attempt not counted; 0/408/429/5xx → queued again with a 1 min (429: 5 min) hold, failed after 3 attempts; any other answer → failed with the service's words.

## What does delete for everyone do? (owner 4a)
- `ws_voice_message_deleted()` after `ws_message_delete()` (and the discussion copy's sync): a recording's row and file (`ws_upload_discard()`) go; while another message still carries the file (a forward, a copy kept) the row moves to it and the file stays.

## How does the API show it?
- `file.voice` (`WorkspaceMessageVoice`, `ws_voice_api_present()`) on audio files with a row: `status` (none, queued, running, done, failed, skipped), and once done `transcript`, `language`, `provider`, `model`, `transcribed_at`; MCP reads the same message answer. Same read rules as the file.

## What does the panel get? (V3c1)
- `file.voice` in the message payload (messages.php) for `audio` files with a row: `ws_voice_panel_present()` (status, `duration_ms`, `peaks`, and once done transcript, language, provider, model).
- `channel.voice` in `ws_channel_detail()`: `ws_voice_channel_state()` (allowed, provider), null while transcripts are off, in a guest room, a channel shared with guests or a discussion (`ws_voice_channel_switchable()`).
- `ws_voice_channel_set()` as `ws_ai_channel_set()`: a manager (`ws_can_manage_channel()`), `voice_access` 1 or 2, a system line. Transcripts already written stay.
- What the recorder measured: `$options['voice']` of `ws_message_send_file_bytes()` → `ws_voice_queue(..., $measure)`, cleaned by `ws_voice_measure_clean()` (length within the cap + 1 s, at most `WS_VOICE_PEAKS` 120 digits 0-9, anything else dropped).

## Which event says it was written out?
- `workspace.message.transcribed` (`ws_voice_announce()`, from `ws_voice_run()` once done): public channels only, never a private channel or a discussion, as `workspace.message.created`. Carries id, `channel_id` (narrowed subscriptions), sender_id or app_id, file_id, file_name, duration_ms, transcript, language, provider, model.
- A webhook event only (`pg_announce()`), not a Flows trigger: no `ws_event()` call, so no line in `workspace_flows_parity_table()`.

Schema, settings and the channel rule: workspace-voice-transcripts.md.
