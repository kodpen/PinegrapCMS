---
name: image-editor-clip-export
description: A video layer in an animation's export (V7b): each frame waits for the clip's own frame of that moment from a decoder of its own, nothing off its span; a still save keeps the source in
module: image_editor
---

## Where is it?
- `renderFrame()` of the `render` part (`pg_image_editor_render.js`) asks `clipFramesAt(time)` of the `clip` part (`pg_image_editor_clip.js`, "the frames of an animation's export") beside `frameCutsAt()`; the model's `M.clipFramesAt(doc, t)` in the `CLIP` section; tests in `tests/image_editor_clip_test.php`. Added in V7b of `plans/image-editor-video-steps-2.md` (#8629).

## Which frame does an export frame take?
- `M.clipFramesAt(doc, t)`: by layer id, each visible video layer's source time at document time t (`M.clipAt()`: the trim and the speed), rounded to whole ms; `null` while t is off the clip's span (before its start, from its end on), so the layer paints nothing there. Hidden clips and other layers are left out.
- A still (t `null`: a still save, the scene thumbnails of Present) asks for no frame: each clip paints its source in, as `clipReady()` decoded it.
- In a scene, `renderFrame()` gets the scene's document (`M.sceneView()`) and its local time, so a clip's time is the scene's.

## Where do the frames come from?
- `clipFrameSource(src)`: a second `X.video.open()` on the same bytes per file (`clipFrameSets`), so the stage's and the bars' requests never overtake the export's (a source answers only its newest request). Requests of one file are chained one after another, so two clips of one file both get their frame.
- The frame is a `VideoFrame`: `clipPaint()` draws it in the layer's box (turned, with the layer's motion) while `this.clipFrames` holds it; `renderFrame()` closes every frame after painting.
- A frame that does not decode leaves the clip out of the map and it paints its source in, never a gap. The decoders close with the editor (`finish()`).

## Is it in step with the sound?
- Yes by construction: the mix (`image-editor-mix.md`) places a clip's sound with `M.clipSound()` from the same start, source in and speed that `M.clipAt()` uses.
- Tried in Chrome (#8629): a clip trimmed to 2-6 s of its source from 1 s on, MP4 and WebM at 10 fps: each frame the source's own, nothing before 1 s or from 5 s, the beeps where the source has them; at 2x the frames twice as fast and no sound (a clip off 1x carries none).
- Every export path waits for the frames: the encoder's frames, the estimate's samples, the real-time recorder (a slow decode holds the frame before longer there) and Present's playback (it draws the next moment when the last is done).
