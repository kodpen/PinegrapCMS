---
name: workspace-voice-transcript-job
description: How a voice message is written out (V3b): the queue on upload, ws_voice_kick() and the hourly job, answers and holds, delete for everyone, file.voice in the API
---

## How is a recording written out? (V3b)
- Both uploads (`ws_message_send_file_bytes()`, the API's files.create) call `ws_voice_queue()`: only a recorder name (`ws_voice_is_recording()`: `voice-….weba/ogg/m4a…`) gets a row, `queued` when transcripts are on and the channel allows them, else '' (never asked).
- `ws_voice_carry_on()` runs `ws_voice_kick()` after the answer went back where PHP-FPM can (`fastcgi_finish_request()`, session closed first); elsewhere (IIS, mod_php) `ws_voice_job()` in workspace_recurring_job.php, hourly, until V3c gives the panel a kick.
- `ws_voice_kick()`: one call at a time (`GET_LOCK('pg_ws_voice_run')`), budget 60 s (job 300 s), watchdog (queued over a day → failed, running over 15 min → queued again or failed after 3 attempts). `ws_voice_run()` re-checks the message, the channel rule and the size (`WS_VOICE_MAX_BYTES` 6 MB), then `ws_voice_call()`: multipart (`ws_voice_multipart()`) through `api_http_request()` (pinned address, no internal hosts), `response_format=json`, 120 s.
- `ws_voice_answer()`: 200 with `text` → done (text, language, provider, model), `ws_message_touch()`; 401/402/403 → the whole queue held an hour, the card shows the error, the attempt not counted; 0/408/429/5xx → queued again with a 1 min (429: 5 min) hold, failed after 3 attempts; any other answer → failed with the service's words.

## What does delete for everyone do? (owner 4a)
- `ws_voice_message_deleted()` after `ws_message_delete()` (and the discussion copy's sync): a recording's row and file (`ws_upload_discard()`) go; while another message still carries the file (a forward, a copy kept) the row moves to it and the file stays.

## How does the API show it?
- `file.voice` (`WorkspaceMessageVoice`, `ws_voice_api_present()`) on audio files with a row: `status` (none, queued, running, done, failed, skipped), and once done `transcript`, `language`, `provider`, `model`, `transcribed_at`; MCP reads the same message answer. Same read rules as the file.

Schema, settings and the channel rule: workspace-voice-transcripts.md.
