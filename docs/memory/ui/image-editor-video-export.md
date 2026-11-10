---
name: image-editor-video-export
description: The image editor's MP4 and WebM output (AN6): WebCodecs with own MP4 and WebM muxers, the MediaRecorder fallback, the limits, the estimate from the bit rate
module: image_editor
---

## Which formats and limits?
- `M.ANIM_FORMATS.mp4` and `.webm` (`video: true`): edges 480-1920 (default 1280, 480 on a small-memory device), 24 or 30 fps, 60 s, even sides (`M.animSize(out, edge, even)`, rounded down, at least 2). No repeat field: a video's loop is its player's. The document's sound goes in as an AAC or Opus track (`ui/image-editor-mix.md`).
- Switching from GIF snaps an edge another format lists to the largest video edge under it; the picture's own long edge is kept (`M.animSwitch()`).

## How does the dialog know what the browser can make?
- `animVideoCaps()` (render part) runs once per editor when the dialog opens: per format `codecs` when `VideoEncoder.isConfigSupported()` takes one of `f.codecs` at 640x360, else `recorder` when `MediaRecorder.isTypeSupported()` takes one of `f.recorder` and canvases have `captureStream`, else false. The answer (`this.animVia`) redraws the settings unless a file is being made.
- Codec order: H.264 Constrained Baseline first (`avc1.42E028`, then Main, High): baseline has no reordered frames, so the muxer needs no composition offsets. WebM is VP9 profile 0 (`vp09.00.40.08`).

## How is a video encoded?
- In the worker (or on the page, `ui/image-editor-render.md`): `begin` carries `bitrate` (`M.animBitrate()`: 0.1 bit per pixel, at least 250 kbit/s) and `codecs`. `VideoEnc` picks the first codec supported at the real size, a key frame every 2 s, waits while more than 3 frames queue, and flattens see-through pixels over white (`flatten()`; frames go in as `RGBX`).
- MP4 (`mp4File()`): ftyp, moov before mdat (a player starts early), one track and one chunk, 90 kHz timescale and a constant sample duration, `stss` for the key frames, the avcC from the encoder's first `decoderConfig.description`.
- WebM (`webmFile()`): EBML (doctype `webm` with a one-byte size, which `pg_image_editor_animation_type()` reads), Info with Duration, Tracks (`V_VP9`, DefaultDuration), Cues before the Clusters (8-byte positions so their size is known first), a Cluster per key frame or 30 s (16-bit block times).
- No samples and no worker for the estimate: header 1000 plus the bit rate's share per frame.

## And without WebCodecs?
- `recordVideo()`: the frames are drawn on a `captureStream(0)` canvas and handed over with `requestFrame()` at their time from the start, so it takes the animation's length and a slow frame holds the one before longer. Cancel stops the recorder; nothing is kept. Its WebM has no cues (the browser's muxer).

## How was it checked?
- `tests/image_editor_video_test.php`: a stand-in encoder under Node, the MP4 parsed box by box and the WebM element by element, both typed by `pg_image_editor_animation_type()`.
- By hand in headless Chromium (secure origin needed for WebCodecs): 320x240, 75 frames gave H.264 Constrained Baseline and VP9 files that ffprobe reads as 2.5 s with 75 frames and ffmpeg decodes without an error, pixels in place. The MediaRecorder path and the dialog itself were not run in a browser.
