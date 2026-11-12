---
name: image-editor-clip-over
description: Layers over a video (V6, #8733): a layer's own end on the timeline (anim.out.at), picture in picture with corners and border (M.clipFrame()), drawn the same on the stage and in a save
module: image_editor
---

## How does a layer keep its end on the timeline?
- An exit used to end its `delay` before the document's end (`M.motionSpans()`), so a clip edit that changed the length moved every text's exit. A dragged exit bar now also writes `anim.out.at`: the exit's end in ms on the open document's timeline (`M.trackEdit()` answers `{ delay, ms, at }`).
- With `at`, `motionSpans()` ends the exit there (inside the timeline, `M.layerUntil()`), `motionAt()` draws nothing from then on, `clipMotionNeed()` keeps the document at least that long, and the tracks' line under the bars stops there. A layer without `at` works as before; the exit's Delay slider in the Motion panel sets `at` to 0, counting from the end again.
- The start was already the entrance's `delay`. A layer with no exit still lasts to the end.

## How is picture in picture drawn?
- A second video layer already had its own row (`clip-timeline`), box and stage handles. V6 adds `l.radius` (px in the layer's units) and `l.border` (one of `M.CLIP_BORDERS`: white, black, red, blue; '' none).
- `M.clipFrame(l)`: radius at most half the shorter side, border from the list, width 1.5 % of the shorter side, at least 2.
- Stage (`clipFrameStage()`): the `.pgie-clipbox` rounded with `overflow: hidden`, a `.pgie-clipedge` div with the CSS border inside. Save (`clipPaint()`): the frame clipped to a rounded path (`roundPath()`, arcTo), then the border stroked on a path inset by half its width, so its outer edge is the box's.
- Panel (Video clip tab, `clipFramePanel()`): Corners (0 to half the shorter side, 1 px steps) and Border (four swatches; the chosen one again takes it off), labels `clipCorners`, `clipBorder`, `clipBorder_0`-`_3`.

## What was tried?
- Sandbox (#8733), screen 39's scene: a 12 s base video, a second one 2-9 s with 24 px corners and a white border, a title with an entrance and its own end at 6 s, a looping badge. Stage at 1.2, 4, 5.75, 7, 9.5 s against the frames of the WebM (VP9 + Opus) the editor saved: the same. Desktop 1440 × 900, 390 × 844 and 844 × 390 (touch).
- Not tried: MP4 from the editor (no AAC encoder in headless Chromium).
- Tests: `tests/image_editor_clip_over_test.php`; the exit drag's patch in `tests/image_editor_tracks_test.php`.
