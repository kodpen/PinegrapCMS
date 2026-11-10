---
name: image-editor-video-steps
description: Steps V0-V11 of plans/image-editor-video.md: screens, decoding, video layer, timeline, sound, effects, overlays, export, large files, reopening, phone, access; claim as [plan:image-editor-video] V<n>
---

## Which steps are there?
- S under 400 product lines, M about 1,000. Each step owns its part `assets/js/pg_image_editor_<part>.js`, its model section, labels, styles, test and memory topic. The holder marks lines `free`.

- V0 (S) Screens. Done (#7619, screens 35-42, `image-editor-video-visuals.md`).

- V1 (M) Demux and decode. Part `video`. Done (#7658, `ui/image-editor-video-decode.md`).
  - Own MP4 (moov, trak, stsd, stts, ctts, stss, stsz, stco/co64) and WebM (EBML, Cluster, SimpleBlock) readers; WebCodecs decoders; a frame cache by time; seeking from the nearest key frame; what the browser cannot decode (HEVC on some) gets a message.
  - Tests: sample tables of small MP4 and WebM fixtures under Node; the seek point for a time.

- V2 (M) The video layer.
  - `type: 'video'` with its file, source in and out, place on the timeline, speed, volume, muted. Drawn at the playhead under or over other layers; playing in step with the play bar; added from Files or this computer through E31's Import menu (`ui/image-editor-import.md`).

- V3 (M) Timeline editing.
  - Clip rows on the tracks (AN2): trim the ends, split at the playhead (two layers on one source), move, snap; the document's length follows its clips.

- V4 (M) Sound. Done (#7642, `ui/image-editor-sound.md`; from this computer left to V8). Screens 37-sound, 35-video. Needs no video layer: may run right after V0, before V1-V3 (owner 2026-11-05: sound in an animation's video too).
  - An audio layer on any canvas: music and sounds from Files (MP3, AAC, WAV, Ogg), its start, trim, volume, fade in and out, mute; its row with a waveform on the tracks; Web Audio playback in step with the play bar, so an animation gets its music.
  - With V2: a clip's sound as its own row (Detach sound mutes the clip and adds an audio layer on the same source).

- V5 (M) Effects and transitions.
  - The Adjust filters per clip (brightness, contrast, saturation, warmth, blur, vignette) drawn per frame; transitions between clips (AN7's fade, slide, wipe); speed 0.25-4× (sound muted off 1×).

- V6 (S) Pictures and animations over video.
  - Layers keep their start and end on the timeline; picture in picture (a second video layer, its size and corners).

- V7 (M) Export with sound. Done (#8484, `ui/image-editor-mix.md`).
  - Frames as AN6; the sound mixed in an `OfflineAudioContext`, AAC or Opus through `AudioEncoder`; both interleaved in AN6's muxers; estimate, progress, Cancel.
  - An animation's MP4 or WebM carries its sound; GIF, APNG, WebP and PDF cannot, and the dialog says so when the document has sound.

- V8 (M) Large files. `free` after V7 and V2. One at a time with the save-path steps.
  - Chunked, resumable upload of a source video into Files; chunked save of the export (AN4's action in parts); type told by its bytes (AN4); the size limits as settings.

- V9 (S) Save and open again. `free` after V8 and V3.
  - The project document (clips, sound, layers) kept in the export's version, as AN4 keeps an animation's; the same-format replace.

- V10 (S) Phone.
  - One list of rows, touch-sized trim handles, the sound rows folded.

- V11 (S) Access and close-out. The holder.
  - Keys (J, K, L, I, O), names, captions of the controls; lasting decisions to topic files; these plan files, `image-editor-workers.md`, `image-editor-visuals.md` and `plans/image-editor/` (the screens and the prototype) deleted, the image editor's own plan files having gone in E20 (#7695).
