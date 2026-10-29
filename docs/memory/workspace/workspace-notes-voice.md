---
name: workspace-notes-voice
description: A recording made in a Workspace note (V6): the note toolbar's microphone, kept as a note file through ws_note_file and linked at the caret, players under the text, no transcript
---

## How is a recording made in a note? (V6, #2871)
- The note's toolbar has a microphone ("Record a voice note") beside Attach, only with `PGWsVoice.supported()` (secure context, MediaRecorder) and only on a note the person may change. `recordVoice()` runs the same recorder as the channel box (`PGWsVoice.start()`): the red dot, time, Stop and Discard in a row (`.ws-nb-voice`) above the text, the 5-minute cap, the `voice-YYYYMMDD-HHMMSS.weba/.ogg/.m4a` name.
- Stop hands the file to the note's `attach()`: saved first, then `ws_note_file` → `ws_note_file_store_bytes()` keeps it in the note's folder like a dropped file (same refusals, size cap, readers), and `insertLink()` writes `[voice-….weba](url)` where the caret was. Leaving the note or opening another drops a running recording (its row left the page).

## How is it played?
- Under the text, "Recordings in this note" with a `PGWsVoice.player()` per recording (play, time, speed, download), in the editor and in the read-only view. `voiceLinks()` finds Markdown links whose words are a recorder's name (`voice-`, `isVoice()`) to an http(s) address, once each; the list is drawn again only when the addresses change (`drawVoices()`, 400 ms after typing), so typing never stops a player.

## Why no transcript or waveform?
- `ws_voice` rows belong to a message (`message_id`), and the recorder's measure is stored by `ws_attach` only: a note's recording would need a schema change, so it has neither. Assistants asked in the note read the link, not the sound.
- The browser's own model may write one out for the person who asks, never stored: voice-browser-to-text.md.

## How was it checked?
- `tests/workspace_notes_voice_test.php`: the three extensions are kept and drawn as sounds, `voiceLinks()` under Node, the wiring by source. Not with a real microphone in a browser, nor on a running site.
