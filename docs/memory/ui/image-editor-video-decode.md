---
name: image-editor-video-decode
description: How the image editor reads a video: own MP4 and WebM demuxers in the model, WebCodecs decoding, the frame cache, seeking, the messages when a browser cannot decode
module: image_editor
---

## Where is it?
- The VIDEO section of `assets/js/pg_image_editor_model.js` (pure, Node-tested in `tests/image_editor_video_decode_test.php` over `tests/fixtures/video/`) and the part `assets/js/pg_image_editor_video.js` (labels `pg_image_editor_strings_video()`). Added in V1 of `plans/image-editor-video-steps.md` (#7658).

## What does the model give?
- `videoDemux(bytes)` → `{ kind: 'mp4'|'webm', duration (ms), tracks }`; a track has `kind`, `codec` (the WebCodecs string), `description`, `width`/`height` or `rate`/`channels`, `samples` in decode order as `{ at, size, ts, dur, key }` (times in microseconds, presentation time). The type is told by the bytes (`videoKind()`), never by the name.
- MP4: stsd (avcC, hvcC, vpcC, av1C, esds, dOps), stts, ctts (v1 signed), stss (absent: all key), stsz, stsc, stco/co64, the edit list's first real edit shifts times (so B-frame files start at 0). A fragmented MP4 (mvex + moof) is refused.
- WebM: Info (TimecodeScale, Duration), Tracks, Clusters with SimpleBlock and BlockGroup (key = no ReferenceBlock), Xiph, fixed and EBML lacing; a Segment or Cluster of unknown size (a recorder's file) ends at the next id a Cluster cannot hold; no Duration → the last sample's end.
- Errors are `Error(code)`: `video-format`, `video-broken` (also samples past the file's end), `video-fragmented`; the part adds `video-webcodecs`, `video-notrack`, `video-codec`.
- `videoSeek(track, us)` → `{ frame, from }`: the sample with the latest presentation time not after `us` (the first before the start) and the key frame before it in decode order. `videoCache(max)` keeps decoded frames by sample index and closes the least recently used; `videoCacheSize(w, h)` is about 192 MB of RGBA, 8 to 90 frames.

## How does a source decode?
- `X.video.open(data)` (Blob, ArrayBuffer or Uint8Array) → a source after `VideoDecoder.isConfigSupported()`; `frameAt(ms)` gives a copy of the frame (the caller closes it). A cached frame comes at once; a frame still inside the decoder is waited for; one ahead within 30 samples of its key frame is decoded on; else reset and decode from the key frame. Four samples past the frame are fed so a reordering decoder gives it out; when it does not come within 250 ms the decoder is flushed (then it needs a key frame again).
- The cache keeps a copy of each decoded frame (drawn on an OffscreenCanvas, `own()`) and closes the decoder's at once: Chrome's hardware H.264 decoder stops after nine held output frames, so a 720p MP4 hung at its tenth frame until #8483.
- Requests run one at a time; one overtaken by a newer request answers from the cache only, so scrubbing decodes where the playhead stops.
- `X.video.message(e)` gives the label; `editor.videoOpen(data)` shows it as an error toast. A browser with no decoder for the codec (H.265 in Firefox, in Chrome without hardware support) is named in the message.

## What is left to later steps?
- Sound decoding (V4, V7), reading a large file in pieces (V8 sends it in parts, `image-editor-upload.md`; the source still reads the whole file into memory). The video layer that asks for the frames: `image-editor-clip.md`.
