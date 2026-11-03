---
name: workspace-voice-browser-route
description: Voice to assistants (#5390, VA1-VA6) and VA2: without a site key the sender's browser writes a recording out before the upload, kept as a done ws_voice row with provider browser; what is still open
module: voice_text
---

## What did voice to assistants bring? (owner 2026-11-02, #5390)
- Recordings reach Claude, Pinegrap AI and MCP agents in channels and discussions; an ask that was a recording is answered by voice; any caller can post a voice message. Steps: VA1 hint (`workspace-voice-unheard.md`), VA2 below, VA3 speech service (`workspace-voice-speech.md`), VA4 spoken answers (`workspace-voice-spoken-answers.md`), VA5 route (`workspace-voice-message-route.md`), VA6 MCP agents (`workspace-voice-mcp-agents.md`).
- Rules kept: the body is the person's words, a transcript is machine text (#2417); `ws_voice_channel_allowed()`, the 5-minute cap and delete-for-everyone unchanged; settings in the panel, not `data/config.php`; no schema change (a spoken answer is a `done` `ws_voice` row, speech settings are `config_text`).
- Claude's routine and the Messages API take no sound, so a recording is written out before the request: by the site's service, or by the sender's browser.

## When does the sender's browser write a recording out? (VA2, #6026)
- `channel.voice_browser` (`ws_voice_browser_channel()`, pure `ws_voice_browser_rule($schema, $enabled, $allowed)`): transcripts switched off on the site and the channel allows them. A channel that keeps its recordings stays `skipped`.
- And the browser's model already on the device: `PGVoiceText.downloaded(locale)` is true only for `availability()` `available`, so sending never starts a model download. `voiceModelHere()` (workspace.js) asks once, when the mic first shows.
- Then the recorder's hint reads "This browser writes the recording out on this device…" instead of VA1's; a browser without the model keeps VA1's hint.

## How does it travel?
- `voiceSend()` → `PGVoiceText.transcribe(blob, CFG.locale, progress, abort)` (the "Convert to text" pieces, factored into `run()`), shown as an upload card with Cancel → `voicePost()` → `uploadFiles()` with `transcript` and the channel it was recorded in → `ws_attach` `voice_transcript` → `ws_message_send_file_bytes()` → `ws_voice_queue()`.
- A failure, nothing heard or Cancel posts the recording without a text, as before VA2.
- `ws_voice_queue()` keeps it only for a recorder-named file where the rule holds: `ws_voice_browser_transcript()` (one line, control characters out, at most `WS_VOICE_BROWSER_MAX_CHARS` 100,000), row `done`, `provider` `browser`, no language or model. It returns false, so `ws_voice_posted()` asks the tagged assistants at once and they read the transcript.
- The player names it "Machine transcript by the sender's browser" (`voice_machine_browser`). The API's files.create takes no browser text.

## What is still open?
- The Turkish check the owner asked for first: a Turkish recording in a real Chrome with the model. Not done (no Prompt API in the sandbox). If the text proves unusable, `ws_voice_browser_rule()` returning false switches the route off and VA1's hint stays.
- (b) a "Pinegrap AI" preset on the subscription key in the voice card: only once the gateway offers `/audio/transcriptions` and `/audio/speech` (owner, #5390). Not built.
- Tests: tests/workspace_voice_browser_test.php (rule, cleaning, transcribe on a Blob, downloaded, wiring). Not run in a browser.
