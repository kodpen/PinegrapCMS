---
name: image-editor-video-steps-2
description: More steps of plans/image-editor-video.md, beside image-editor-video-steps.md: V7b the clip's frames in the export; claim as [plan:image-editor-video] V<n>
---

## Which steps are there?
- Steps added after `image-editor-video-steps.md` reached its size limit. The sizes (S, M), what a step owns and the `free` mark are as that file says.

- V7b (S) The clip's frames in the export. In progress; one at a time with V5 and V10 (the `clip` part) and before them: until it lands an exported video shows a still.
  - Today a save and every frame of an animation's export paint the clip's source in (`clipReady()`, `clipPaint()`: `ui/image-editor-clip.md`, `ui/image-editor-mix.md`).
  - The export's frame loop (`renderFrame()`, the `render` part) waits for each video layer's frame of that moment (`M.clipAt()`, the source's `frameAt()`) and paints it: the clip's trim and speed, nothing off its span. A still save keeps the source in.
  - Touches `render` beside V8's save hook. Tests: the source time of an export frame (trim, speed, off the span). In a browser: an exported MP4 and WebM plays the clip's own frames in step with its sound.
