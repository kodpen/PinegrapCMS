---
name: image-editor-clip-bar
description: A video clip's bar on the tracks (V3b): the filmstrip of several frames from a second decoder, the clip's sound as a waveform under the frames
module: image_editor
---

## Where is it?
- In the `clip` part (`pg_image_editor_clip.js`, "the filmstrip and the waveform"), `M.clipStripTimes()` in the model's `CLIP` section, the rules after `.pgie-clipquiet` in `pg_image_editor.src.css`, tests in `tests/image_editor_clip_test.php`. Added in V3b of `plans/image-editor-video-steps.md` (screen 35, owner 2026-11-10 on #8521). The row and its drags: `image-editor-clip-timeline.md`.

## What does a bar show?
- The filmstrip: as many frames as fit the bar's width at the video's ratio (`clipStripFill()`, once the bar is laid out), each the source at the middle of its part of the cut (`M.clipStripTimes()`: 0.1 s grid, at most `M.CLIP_STRIP_MAX` 24). A frame not decoded yet shows the stage's last frame.

## What is under the frames?
- A clip with a sound track: a 12 px band under the frames with its cut's waveform (the sound part's `soundWave()` over `M.clipSound()`, no volume line), faded when the sound does not play (muted, hidden, off 1×). The sound part decodes the file once; `clipsKey()` carries its state, so the row is drawn again when it is ready.

## Where do the frames come from?
- From a second source on the same bytes (`clipThumbs()`), one request at a time in time order, kept per file at most 160 px: a source answers only its newest `frameAt()`, so sharing the stage's would stall playing and scrubbing.
- A source that cannot be opened a second time stops asking; its bars keep the stage's frame. `finish()` closes these sources with the stage's.
