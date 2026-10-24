---
name: workspace-voice-recording
description: Voice messages (workspace_voice.js): the microphone in place of Send, the 5-minute cap, .weba/.ogg/.m4a names (never .webm), the player and Chrome's Infinity duration, what is not built
---

## What does a voice message do?
- The writing box's microphone (`assets/js/workspace_voice.js`, in `workspace.min.js`) records with MediaRecorder: mono, 32 kbit/s, echo cancellation, noise suppression, gain control. A red dot and the time stand in for the box; Stop sends, Discard or Escape drops it, under 1 s is dropped. Cap 5 minutes (`LIMIT`): a warning toast 30 s before, then it stops and is sent. Every track is stopped afterwards (the browser's microphone light goes off); opening another channel drops a running recording.
- It leaves as an ordinary file: a `File` handed to `uploadFiles()` (`workspace.js`: `BOOT.upload.max`, then `ws_attach` → `ws_message_send_file()`). No server action, no schema; `ws_upload_inspect()` checks it like any upload and the channel's read rules decide who hears it. `POST /workspace/channels/{id}/files` takes such a file already.
- Texts: `ws_voice_js_strings()` (`includes/workspace/voice.php`), merged in `ws_screen_config()`; mostly the chat bubble's keys.

## Why .weba, .ogg or .m4a and never .webm?
- `ws_file_kind()` (`includes/workspace/files.php`) draws a `.webm` as a video. A WebM recording is named `voice-YYYYMMDD-HHMMSS.weba`, Ogg Opus `.ogg`, MP4/AAC (Safari) `.m4a`. The extension follows `recorder.mimeType` (what was made), not the type asked for; asked in order: `audio/webm;codecs=opus`, `audio/ogg;codecs=opus`, `audio/mp4`, else the browser chooses.
- `get_file.php` serves `weba`, `webm`, `ogg`, `oga`, `opus`, `aac` with their own type; before they went out as attachments.

## How is a sound drawn?
- `filePreview` (`workspace.js`) draws every `audio` file with `PGWsVoice.player()`: play/pause, current/total time, a line to click to a point, speed 1×/1.5×/2× (kept in `localStorage` `ws-voice-speed`, taken by the next player started), the download link. One player sounds at a time. A name starting `voice-` is labelled "Voice message". Without `PGWsVoice` the browser's `<audio>` is drawn as before; video is unchanged.

## Why does the player seek to 1e101?
- Chrome's MediaRecorder WebM, in older builds still in use, has no Duration: `duration` is `Infinity`. On `loadedmetadata` the player sets `currentTime = 1e101`, reads the length on `durationchange`, and goes back to 0. It gives up after 5 s or when Play is pressed; the length then comes once the file was played to its end. Recent Chromium writes Duration and Cues itself.
- Checked in headless Chromium on a file served without `Range` (as `get_file.php` does), with a recording whose Duration and Cues were removed.

## Where does the microphone show?
- In place of Send (the whole `.ws-send-group` when scheduled messages are on) while the box is empty: `voiceSwap()`, called from `autosize()` and `drawReplyBar()`. Only with `getUserMedia` and MediaRecorder (a secure context), in a channel the person may post in.
- Hidden while a message or a scheduled message is changed, in an earlier version of the conversation (`era`), and while a reply bar is open, because `uploadFiles()` sends no `parent_id`.
- `Permissions-Policy: microphone=(self)` (`waf.php`) allows it; keep `(self)`.

## What is not built yet?
- `Range`/206 in `get_file.php` (Safari seeks with it), a voice reply with `parent_id` (`ws_attach` and `files.create` take none), a stored duration or waveform, transcripts, assistants hearing it.
- Not tried on Safari, Firefox or a running site.
