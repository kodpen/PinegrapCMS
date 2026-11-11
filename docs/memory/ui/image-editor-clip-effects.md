---
name: image-editor-clip-effects
description: A video clip's effects (l.fx) and its transition into the next clip on its row (l.transition), V5 #8667: numbers, stage and save, held frame, layer order, split, panel
module: image_editor
---

## What does a clip keep?
- `l.fx` { brightness, contrast, saturation, temperature, blur, vignette } (`M.clipFx()`: whole numbers; the first four −100..100 with the Adjust tool's ranges, blur and vignette 0..100; 0 or no field is none). Reset sets `fx: null`.
- `l.transition` { kind, ms } on the clip leading out (`M.clipTransition()` through `sceneTransition()`: the scenes' kinds none, fade, slide, zoom, wipe, 0.1-2 s); none or null is none. Added in V5 (#8667).

## Where do the numbers come from?
- Colour: `M.clipFxColor()` = `colorMatrix(colorValues({ adjust }))`, the Adjust tool's numbers; vignette: `vignetteStops()`; blur: `M.clipFxBlur()`, a standard deviation of 2 % of the box's shorter side at 100, in the layer's units.
- Transition: `sceneTransitionAt()` per clip, each on its own box (two clips may differ in size), `easeAt('in-out')` as `sceneAt()`.

## How do the stage and a save draw the same?
- Stage (`clipNode()`): an SVG `feColorMatrix` filter per layer (`<fxId>-clipfx-<id>`) and CSS `blur()` on the canvas, a radial gradient div over it; `clipShowNode()` puts a transition's move, scale, opacity and wipe (`clip-path`) on the node.
- Save and export (`clipPaint()` → `clipFxPaint()`): `applyColor()` on the frame at the pixels it lands on, the canvas's `filter: blur()` scaled by `g.getTransform()`, a radial gradient scaled to the box (`ellipse farthest-corner`). The transition's state is multiplied into the motion's transform and `globalAlpha`; `renderFrame()` gives the moment as `this.frameTime` (render.js).
- Blur is cut to the box on both: its edges fade inward, nothing spills out.
- A browser without the canvas's `filter` (Safari before 18) saves the clip unblurred; the stage still blurs.

## When does a transition play?
- Only into the next shown clip on the same row that starts where this one ends (`M.clipTouching()`, within 1 ms). It runs over the first ms of that clip, never longer than it (`M.clipTransitionMs()`); no clip's span and no document length change.
- The clip leading out holds its last frame, source time `to - 1` as `clipAt()` ends (`M.clipShowAt()`); `M.clipFramesAt()` asks it, so the export gets both frames.
- Kept but not drawn when the clips no longer meet (moved, trimmed, deleted, another row, hidden): it plays again once they meet.
- A split leaves it on the second part (`left.transition = null` in `clipSplit()`'s patch); both parts keep `fx`; the new cut has none.

## What if the clip leading out is the upper layer?
- The scenes' states draw the new one over the old. When the leading clip is above (`doc.layers` order), `clipTransitionStates()` takes it away instead: fade and zoom fade it out, wipe uncovers the clip under it from the left; slide is the same. Tried both orders in Chromium (#8667).

## Where is it in the panel and the row?
- Animate with one clip: tabs Video clip | Effects | Motion; Effects has the six sliders two a row (one drag one undo step), Reset, "Transition to the next clip" (tiles and Transition time, or a note when no clip meets this one) and the note. Select's clip panel has no effects: they are part of the video's timeline work.
- Screen 38's labels and order since #8733 (owner, #8676): "Blurriness" (Bulanıklık), "No transition" (Yok), tiles `M.CLIP_TRANSITIONS` (scenes keep theirs), note plus `clipFxNoteSound`, rows 6 px apart.
- The bar: a speed badge off 1×, an effects mark. Between two clips that meet, a round button (the transition's icon, filled when set): it selects the leading clip and opens Effects; it sits mid-height, the split's scissors at the top. Footer hint `hintClips`.

## Tests?
- `tests/image_editor_clip_effects_test.php` (Node). Tried in Chromium (#8667): every kind in both layer orders, stage against the saved frame, WebM (VP9) and MP4 (H.264) exports, 390×844 and 844×390.
