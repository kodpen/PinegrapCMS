---
name: image-editor-video
description: Plan (owner 2026-11-03): video editing inside the image editor: clips on the timeline, trim, split, effects, sound added or detached, layers over video, export with sound; held with image-editor.md
---

## What is the status?
- Held by the holder of `image-editor.md`, run as `image-editor-workers.md` says; claimed as `[plan:image-editor-video] V<n> — <topic>`. Video comes last (owner, 2026-11-03): V0 starts once E21-E28 and the animation steps through AN8 have landed; the holder marks lines `free`.

## What is it?
- A video is a layer of the editor's document, on the animation timeline (AN1, AN2, AN9) and in scenes (AN7): trim, split, move, speed, volume. Pictures, texts, stickers, shapes and their motion lie over it; effects and transitions apply to clips.
- Sound: the sound of a clip, music or sounds from Files, detached into its own row, volume and fades, muted. An animation without a video takes music and sounds the same way, and its MP4 or WebM carries them (owner 2026-11-05).
- Export to MP4 or WebM with sound through the animation export (AN6's muxers), saved into Files (AN4); its document is kept, so the project opens again.

## Which decisions bind it?
- Owner, 2026-11-03 (`workflow/decisions-made-product-24.md`): inside the image editor, not a separate editor; sound allowed. This widens 2026-10-31 (no video) and #4716 (silent videos).
- From `image-editor.md`: own code (demuxers, muxers, filters), labels through `lang()`.

## How does it work?
- Decoding and encoding in the browser with WebCodecs (`VideoDecoder`, `AudioDecoder`, `VideoEncoder`, `AudioEncoder`); own MP4 (ISO BMFF) and WebM (Matroska) demuxers feed them. A browser without WebCodecs gets a message instead of the video tools.
- The preview draws the decoded frame at the playhead under the layers; sound is played through Web Audio in step with the playhead.
- The export draws each frame (AN3's `renderFrame()` with the video frame as its base), mixes the sound in an `OfflineAudioContext`, encodes AAC (MP4) or Opus (WebM) and interleaves both in the muxers.
- Files larger than one request: uploaded and saved in chunks (V8); the limits are settings.

## Which files hold the rest?
- `image-editor-video-steps.md`: steps V0-V11. The screens: 35-42 in `plans/image-editor/`, read with `image-editor-video-visuals.md`.
