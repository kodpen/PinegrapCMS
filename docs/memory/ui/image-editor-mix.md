---
name: image-editor-mix
description: An animation's sound in its MP4 or WebM (V7, #8484): the mix plan, OfflineAudioContext, AAC or Opus via AudioEncoder, the muxers' second track, the dialog's note, Sound field and steps
module: image_editor
---

## What carries sound?
- Only MP4 (AAC-LC `mp4a.40.2`) and WebM (Opus), 48 kHz stereo at 128 kbit/s (`M.MIX_CODECS`, `M.mixConfig()`). GIF, APNG, WebP and PDF carry none: while the document has a heard sound the dialog says so under the formats (`mixNote()`, a muted speaker).
- A sound is heard when it is not muted, its volume is above 0 and it starts before the end (`M.mixPlan()`); one running past the end is cut there.
- A picture with music and nothing moving gets Save animation too (`animMoves()` wrapped in `pg_image_editor_mix.js`).

## How is it mixed?
- `M.mixPlan(sounds, total)`: per heard sound its file, `when` (output ms), `offset` into the file, `dur`, and `gains` as [time, gain] corners: the start, the fade in's end, the fade out's start, the stop. The same corners as the play bar's ramps (`ui/image-editor-sound.md`).
- `mixRender()` (page): each file through `soundLoad()` (decoded once), an `OfflineAudioContext(2, frames, 48000)`, a buffer source per sound ramped over the corners, `start(when, offset, dur)`. A file that does not decode fails the export with its name (`mixFileError`), never a silent gap. A copy of each channel goes to the encoder.
- The length is `M.sceneTotal(doc)`, the sounds' timeline.

## How is it encoded and muxed?
- `begin` carries `audio` (the config) and, after the last frame, message `audio` carries the channels; the session answers `audioed`, then `end` gives the file. `VideoEnc.sound()` feeds `AudioData` (`f32-planar`) a second at a time, at most 3 queued, and keeps the packets with their timestamp and duration (AAC 1024, Opus 960 samples when none).
- MP4 (`mp4File()`): a second trak (`soun`, timescale 48000, `smhd`, `mp4a` with `esds` holding the encoder's AudioSpecificConfig, or one built by `aacConfig()`); with sound mdat holds a chunk of each track per second side by side (`interleave()`), `stsc` runs and `stco` per track; mvhd's length is the longer track. Without sound still one chunk, as before.
- WebM (`webmFile()`): a second TrackEntry `A_OPUS` with the encoder's `OpusHead` or its own (`opusHead()`: pre-skip 312), CodecDelay (pre-skip in ns), SeekPreRoll 80 ms, rate and channels; packets as SimpleBlocks of track 2 among the frames by time; clusters still start at video key frames, the Duration is the longer track.

## What does the dialog show?
- The Sound field after the size, for a video format: `AAC · 128 kbps · stereo` (or Opus), "Add sound: " and the heard files' names (`M.mixNames()`), on by default (`set.sound`). Where this browser cannot (no `AudioEncoder` for the codec, `mixCaps()` asked once per editor, or the MediaRecorder fallback) a note says the video is saved without sound.
- The estimate adds `M.mixBytes()` (bit rate plus 8 bytes per packet) and says "sound {size}"; the foot reads "Frames and sound are drawn, mixed and encoded in this browser."
- Working: base, Mixing the sound, frames, Encoding the sound (AAC), Encoding the file, Saving into Files; the foot adds "AAC 128 kbps". Cancel during the mix or the frames writes nothing.

## How was it checked?
- `tests/image_editor_mix_test.php`: the plan's corners and cuts, names and sources, the estimate's share, an MP4 and a WebM with a stand-in AudioEncoder parsed box by box and element by element, the session's `audioed`.
- Browser pane on a sandbox (2026-11-10): two WAV sounds on a picture saved as MP4 and WebM; `decodeAudioData()` gave 2 channels, 48 kHz, 5.0 s with both sounds, a `<video>` decoded audio bytes; the MP4 had two traks of five chunks. No ffprobe on that machine. 390x844 and 844x390: no sideways scroll, saves worked.

## What is left?
- No edit list for AAC's encoder delay (tens of ms). A video layer's own sound is mixed as the play bar hears it (`soundsMore()`), untried in a browser; its frames in the export are still the source in.
