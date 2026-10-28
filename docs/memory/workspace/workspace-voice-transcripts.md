---
name: workspace-voice-transcripts
description: Voice message transcripts (V3a #2674): ws_voice, the settings card with the site's own OpenAI-compatible key, ws_channels.voice_access, the 5-minute cap
---

## What does the schema step bring (11.0.89)?
- `ws_voice`: one row per recording, `UNIQUE file_id` (a discussion copy shares the file and reads the same row), `message_id`, `channel_id`, `duration_ms`, `peaks` (one character per level), `status` ('' never asked, queued, running, done, failed, skipped), `transcript` (MEDIUMTEXT), `language`, `provider`, `model`, `attempts`, `error`, times. In `get_tables()`.
- `ws_channels.voice_access` in the `ai_access` shape: 0 by kind (public yes, private no), 1 allowed, 2 closed.
- `config.ws_voice_enabled`, `config.ws_voice_hold_until`; the texts are `config_text` rows `ws_voice_url`, `ws_voice_model`, `ws_voice_key` (`<ciphertext>:<iv>` of `encrypt_string_with_iv()`), `ws_voice_error`: the config row has no room for more long columns (`data/config-table-no-varchar.md`; `tests/innodb_test.php` counts the two short columns).

## Why a table and not the body? (owner, #2417)
- The body stays the person's own words (editable, 4000 chars); the transcript is machine text, marked as such, never passing as theirs. Search and recaps will read both (V4).

## Which service, and how is it named?
- The site's own key for any OpenAI-compatible `/audio/transcriptions` (owner 2a, #2417); a Pinegrap AI gateway preset may come later. Empty address = `https://api.openai.com/v1`, empty model = `whisper-1` (`ws_voice_default_url()`, `ws_voice_default_model()`).
- A pasted full endpoint is cut to its base (`ws_voice_url_clean()`); only HTTPS without user, query or fragment (`ws_voice_url_valid()`): the key travels in a header.
- The consent line names the provider (`ws_voice_provider_name()`): OpenAI, Groq by host suffix, any other host by itself, so a look-alike host is never shown as OpenAI. Azure OpenAI does not fit: its address needs `?api-version=`, which the check refuses.

## Who sets it, and what is logged?
- Card `#ws-voice` on workspace_settings.php (`ws_voice_settings_card()`, action `voice` → `ws_voice_settings_post()`): role 0 only; role-3 settings holders see it disabled without the key field. The key is written, never shown; empty keeps it, a checkbox removes it.
- Site Log line and `pg_audit('update', 'settings', 0, …, 'workspace_voice')` with `api_key` masked by `pg_audit_secret_key()`.

## Where may a recording be sent?
- `ws_voice_channel_allowed()` (rule `ws_voice_channel_rule()`, tests/workspace_voice_transcripts_test.php): public channels, private ones only with `voice_access = 1` (owner 3b), never a guest room or a channel shared with a guest; a discussion follows its channel.
- At most `WS_VOICE_MAX_SECONDS` (300, owner 5b), the recorder's own cap.

## What is not built yet?
- Search and push hearing it: workspace-voice-assistants.md; a recording asking an assistant: workspace-voice-asks.md; answers read aloud: workspace-voice-read-aloud.md; a recording in a note (never transcribed): workspace-notes-voice.md. The panel side: workspace-voice-recording.md, the job: workspace-voice-transcript-job.md.
- Never checked with a real service (no key in the sandbox).
