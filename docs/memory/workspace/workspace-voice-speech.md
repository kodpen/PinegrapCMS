---
name: workspace-voice-speech
description: The speech service a spoken answer is made with (VA3): the card's Spoken answers part, config_text rows, ws_voice_speak() and ws_voice_speak_keep(), the 1,500-character cut, what VA4 and VA5 call
---

## Where is it set, and what is stored?
- The Voice message transcripts card (`ws_voice_settings_card()`, voice.php) has a "Spoken answers" part (`ws_voice_speech_settings_part()`, status `ws_voice_speech_settings_status()`), posted with the same `voice` action: a switch, an OpenAI-compatible `/audio/speech` address and key, a model and a voice. Address or key left empty means the transcripts' own, so one OpenAI key serves both ways; the consent line names the provider of the address called (`ws_voice_provider_name()`).
- `config_text` rows only, no schema: `ws_voice_speech_enabled` ('1' or absent), `ws_voice_speech_url`, `ws_voice_speech_key` (sealed like `ws_voice_key`), `ws_voice_speech_model`, `ws_voice_speech_voice`, `ws_voice_speech_error` (the last refusal, cleared by the next sound). Read by `ws_voice_speech_config()`; the key by `ws_voice_speech_key()` (own, else the transcripts').
- Model and voice are typed into a box with a datalist (`ws_voice_speech_models()`: gpt-4o-mini-tts, tts-1, tts-1-hd; `ws_voice_speech_voices()`: OpenAI's eleven), any name of the model shape allowed so another service's names fit. Defaults `gpt-4o-mini-tts` and `alloy`.
- Role 0 saves; role-3 settings holders see the part disabled without the key. Audit `workspace_voice` carries `speech_*` fields, `speech_api_key` masked.

## How is a text spoken?
- `ws_voice_speak($text, $locale)`: refuses with the first sentence of `ws_voice_speech_missing()` (off, no key, key unreadable, no cURL; `ws_voice_speech_ready()` is its yes/no); cuts the text (`ws_voice_speech_cut()`: over `WS_VOICE_SPEECH_MAX_CHARS` 1,500 characters, at the last `. ! ? …` followed by white space or a line end that fits, else a word end, else the limit); builds the request (`ws_voice_speech_request()`, pure: `POST <base>/audio/speech`, JSON model/input/voice/response_format mp3, `instructions: Speak in <language>.` only for a model that is not tts-1/tts-1-hd, which refuse it); sends it through `api_http_request()` with the key, 120 s, `agent workspace-voice`; reads the answer with `ws_voice_speech_answer()` (200 with non-JSON bytes = a sound; else the service's words, `refused` for 401/402/403).
- Answers `bytes`, `name` (`voice-YYYYMMDD-HHMMSS.mp3`, `ws_voice_speech_file_name()`, UTC), `mime`, `text` (what was spoken), `language` (the locale's two-letter code), `model`, `voice`, `provider`. Nothing is kept yet.

## How is the sound kept like a recording?
- `ws_voice_speak_keep($channel, $message, $spoken, $user_id)`: for a message already written and carrying no file. `ws_upload_inspect()`, `ws_store_file_bytes()` into the channel's folder (`files.user` = `$user_id`, the person spoken to), `UPDATE ws_messages SET file_id, file_name … AND file_id = 0` (the file discarded when that row was taken meanwhile), then a `done` `ws_voice` row: transcript = the spoken text, `language`, `provider`, `model`, `duration_ms` 0 (the player reads the file's own length), `transcribed_at` now; `ws_message_touch()`. The name passes `ws_voice_is_recording()`, so the player, search, recaps, bells, quotes and delete for everyone treat it as any voice message; nothing is queued.
- VA4 calls both on an assistant's answer message; VA5's route answers `voice_unavailable` when `ws_voice_speech_ready()` is false.

## What is unverified?
- Never run against a real service (no key in the sandbox); the mp3 body and the Content-Type check follow OpenAI's documented answer. Tests: tests/workspace_voice_speech_test.php (lists, the cut, the request, the language, the answer, the name, the wiring).
