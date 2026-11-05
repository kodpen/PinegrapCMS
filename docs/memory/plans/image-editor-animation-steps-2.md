---
name: image-editor-animation-steps-2
description: Steps AN12 on of plans/image-editor-animation.md (owner 2026-11-05): a move recorded by dragging, its path and speed kept as keyframes; claim as [plan:image-editor-animation] AN<n>
---

## Which steps are there?
- Sizes, files, review and side by side as in `image-editor-animation-steps.md`; AN11 (access and close-out) comes after these. The holder marks a line `free` once its screens are in `plans/image-editor/` (`image-editor-animation-visuals.md`).

- AN12 (M) Record a move (owner 2026-11-05). `free`. Screens 33-motion-record and 34-phone-record (`image-editor-animation-visuals.md`).
  - In the Animate tool a Record button (a red dot beside Play) arms the selected layer. While a finger or the mouse drags that layer, the clock runs in real time from the playhead and the layer's place is read every frame; letting go stops the recording. The play bar shows the time running, the layer's track a red bar, the stage the path drawn so far (AN9's path).
  - The samples become AN9 keyframes of position, linear between them, thinned in place and time (Douglas-Peucker, about 1 px and one frame), so the stage, Present, GIF, APNG, WebP and video repeat the same path at the same speed. Keyframes inside the recorded span are replaced, the ones before and after stay; one undo step.
  - A recording past the document's end lengthens it, up to the longest animation the document may have. The two-finger twist on a phone records the angle too, a corner handle the size.
  - Recording speed 0.5×, 1× or 2× for careful moves: the clock runs at that rate, playback at 1×.
  - Phone: Record sits on the sheet's play bar; the sheet folds while recording so the whole stage is free.
  - Over a video (the video plan's V6) the same recording moves a layer over the clip.
  - Tests: thinning keeps the path within its tolerance and the samples' times; the span replaced and the rest kept; the end lengthened; a recording at 0.5× plays twice as fast.
