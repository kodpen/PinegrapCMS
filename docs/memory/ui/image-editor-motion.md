---
name: image-editor-motion
description: The image editor's Animate tool: anim and doc.motion, M.motionAt() and its spans, the play bar, the playhead and previews, the empty panel, what a still save sees
module: image_editor
---

## What does a document hold?
- A layer's `anim`: `{ in, loop, out }`, each absent or `{ kind, ms, delay, ease, dir }`. In and out kinds are `M.MOTION_IN`, loop kinds `M.MOTION_LOOP`. `dir` (`M.MOTION_DIRS`) is the way it moves, `ease` from `M.MOTION_EASES`.
- `doc.motion`: `{ duration, loop, base }` through `M.docMotion()`: 5 s, forever (0) and no base move by default, 1-60 s; `base` (`M.MOTION_BASES`) slowly zooms or pans the picture over the whole duration (`M.baseAt()`).
- Both ride in the document a version row keeps (`image-editor-versions.md`). A save draws every layer at rest.

## When does each phase run?
- `M.motionSpans(l, end)` answers `inStart/inEnd`, `loopStart/loopEnd` and `outStart/outEnd`, with null for a phase the layer lacks.
  - In starts at its delay, out ends its delay before the end, the loop runs from the end of in plus its delay to the start of out (or the end).
- `M.motionAt(l, t, end)` returns `{ opacity, x, y, scale, rot, clip, draw, chars }` and composes the three phases: opacities and scales multiply, offsets and angles add, the later clip wins, `draw` and `chars` take the smaller value.
  - A null `t`, or a layer without anim, gives the rest state.
  - Out is its in kind played backwards with the direction turned, so "slide out right" leaves to the right.
  - Slide hides the layer until its start. Type rounds the letters down (`chars` null once all are shown).
- Tested under Node in `tests/image_editor_motion_test.php`.

## How does the stage preview a moment?
- `renderLayers()` puts each layer's node in a `<g>` (`this.layerGroups[id]`); `applyMotion()` sets its transform (about the box's centre), opacity and a wipe's clip (over `M.bounds()`), and moves the picture for the base move (`motionBase()`).
- Only a cut drawing is drawn again: typewriter from a copy whose `text` is cut to `chars`; draw on with `pathLength=1` and a dash offset, the fill fading in.
- `this.time` is null except while playing, previewing or scrubbing in the Animate tool, so editing, the selection box and a save always see the layers at rest.
  - Leaving the tool sets it back to null (`renderMotion()`); so does a press on the stage (`motionDown()`) before it selects or drags.
  - It plays on Play or K, or as the preview of a phase just picked (`motionPreview()`, #5062): from the phase's start to its end (one cycle of a loop), then every layer at rest. Before, a pick only moved the playhead to the phase's start, where an entrance hides the layer.

## Where is the play bar?
- Play/Pause, the clock (m:ss.d), the scrub with a tick at each layer's start, Repeat (preview only, not saved), the length.
- On desktop and on a phone held sideways it runs along the stage's bottom (`this.stageBar`), up to an open sheet's edge (`panelCover()`); under 480 px wide it drops the length. `stageSize()` subtracts its height (`stageInset`), so a fitted picture stays above it.
- On a phone held upright (`max-width: 599.98px`) it comes first in the sheet instead.
- Rebuilt only when what it shows changes (`stageBarKey`): a zoom or a scrub keeps it.
- Each frame calls only `applyMotion()` and `updateBar()`.
- Timeline keys (K, arrows, Home, End): `image-editor-animation.md`. Escape pauses first; M picks the tool (`KEY_TOOLS`); an open `?` sheet takes the keys.

## What does the panel offer with nothing to move?
- With nothing selected, the layers to pick (`layerList()`); with no layer that may move, Add text and Add a sticker (`motionEmpty()`, #5062). The whole animation's fields follow.

## What is left out, and what never moves?
- Redaction layers never move: one moving away in an export would show what it hides.
- Tracks `image-editor-tracks.md`, keyframes `image-editor-keyframes.md`, frames `image-editor-render.md`. Names and the 5-second note: `image-editor-animation.md`.
