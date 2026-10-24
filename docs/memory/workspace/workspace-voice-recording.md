---
name: workspace-voice-recording
description: Voice messages (workspace_voice.js): the mic in place of Send, a sound's reply mic (parent_id on ws_attach and files.create), the 5-minute cap, .weba/.ogg/.m4a names, the player, what is not built
---

## What does a voice message do?
- The box's microphone (`assets/js/workspace_voice.js`, in `workspace.min.js`) records with MediaRecorder: mono, 32 kbit/s, echo cancellation, noise suppression, gain control. A red dot and the time stand in for the box; Stop sends, Discard or Escape drops it, under 1 s is dropped. Cap 5 minutes (`LIMIT`), a toast 30 s before. Every track is stopped afterwards; opening another channel drops it.
- It leaves as a file: `voiceSend()` → `uploadFiles()` → `ws_attach` → `ws_message_send_file()`, without words (the box keeps its text). Checked like any upload; the channel's read rules decide who hears it.
- Texts: `ws_voice_js_strings()` (`includes/workspace/voice.php`).

## How is a sound answered with a voice?
- Every `audio` file's player has a microphone ("Reply with voice"; a tap, a phone's long press opens the menu) in the conversation where the person may post. `voiceReply()` makes it the reply bar's message and records in the box. A recording answers what the reply bar shows when it is sent (`parent_id`); the bar closes once posted, Discard keeps it.
- Not to Claude, Pinegrap AI or an MCP agent (`voiceAnswers()`): a reply asks them, a recording asks nobody yet. While a message is changed it says so; one recording at a time (`PGWsVoice.busy()`).
- `ws_attach` takes `parent_id`, checked by `ws_message_parent_check()` (messages.php; pure part `ws_message_parent_refusal()`): this channel's or discussion's own, not deleted, after the floor (eras). The panel posts the file without a parent it refuses, rather than lose the recording; `POST /workspace/channels/{id}/files` answers 422 on `parent_id`, dry run too. Still `'mcp' => false`.
- A quote or reply bar of a file without words reads "Voice message" for a `voice-` sound, else the file name (`quoteText()`).

## Why .weba, .ogg or .m4a and never .webm?
- `ws_file_kind()` draws a `.webm` as a video. WebM is named `voice-YYYYMMDD-HHMMSS.weba`, Ogg Opus `.ogg`, MP4/AAC (Safari) `.m4a`, after `recorder.mimeType`; asked in order `audio/webm;codecs=opus`, `audio/ogg;codecs=opus`, `audio/mp4`. `get_file.php` serves `weba webm ogg oga opus aac` with their own type.

## How is a sound drawn?
- `filePreview` draws every `audio` file with `PGWsVoice.player()`: play/pause, time, a seek line, speed 1×/1.5×/2× (`localStorage` `ws-voice-speed`), download, the reply microphone. One sounds at a time.
- Older Chrome WebM has no Duration (`Infinity`): on `loadedmetadata` the player seeks to `1e101`, reads `durationchange`, goes back to 0; gives up after 5 s or on Play.

## Where does the box's microphone show?
- In place of Send (the whole `.ws-send-group`) while the box is empty: `voiceSwap()`, from `autosize()` and `drawReplyBar()`. Only with `getUserMedia` and MediaRecorder (secure context), where the person may post. Hidden while a message or a scheduled message is changed, in an earlier version (`era`), beside a reply to several or to an assistant.
- `Permissions-Policy: microphone=(self)` (`waf.php`); keep `(self)`.

## What is not built yet?
- `Range`/206 in `get_file.php` (Safari), `parent_id` in the API's message answer and in `messages.create`, a stored duration or waveform, transcripts, assistants hearing it.
- Checked in headless Chromium with a fake microphone and a stubbed API; not on Safari, Firefox or a running site.
