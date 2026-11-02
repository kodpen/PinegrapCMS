---
name: image-editor-motion
description: The image editor's Animate tool: layer anim and doc.motion fields, M.motionAt() and its spans, the play bar and playhead, how the stage previews a moment, what a still save sees
module: image_editor
---

## What does a document hold?
- A layer's `anim`: `{ in, loop, out }`, each absent or `{ kind, ms, delay, ease, dir }`. In kinds and out kinds come from `M.MOTION_IN` (fade, slide, rise, pop, zoom, wipe, spin, type, draw), loop kinds from `M.MOTION_LOOP` (pulse, float, spin, blink, wiggle, swing). `dir` is one of `M.MOTION_DIRS`, the way the layer moves. `ease` is one of `M.MOTION_EASES`.
- `doc.motion`: `{ duration, loop, base }`, read through `M.docMotion()`. It defaults to 5 s, repeat forever (0) and no base move, and clamps the duration to 1-60 s. `base` is one of `M.MOTION_BASES`: a slow zoom or pan of the picture across the whole duration (`M.baseAt()`).
- Both ride in the document a version row keeps (`image-editor-versions.md`). A save draws every layer at rest.

## When does each phase run?
- `M.motionSpans(l, end)` answers `inStart/inEnd`, `loopStart/loopEnd` and `outStart/outEnd`, with null for a phase the layer lacks.
  - In starts at its delay.
  - Out ends its delay before the end.
  - The loop runs from the end of in, plus its own delay, to the start of out (or to the end).
- `M.motionAt(l, t, end)` returns `{ opacity, x, y, scale, rot, clip, draw, chars }` and composes the three phases: opacities and scales multiply, offsets and angles add, the later clip wins, `draw` and `chars` take the smaller value.
  - A null `t`, or a layer without anim, gives the rest state.
  - Out is its in kind played backwards with the direction turned, so "slide out right" leaves to the right.
  - Slide hides the layer until its start. Type rounds the letters down (`chars` null once all are shown).
- Tested under Node in `tests/image_editor_motion_test.php`.

## How does the stage preview a moment?
- `renderLayers()` puts each layer's node in a `<g>` (`this.layerGroups[id]`). `applyMotion()` sets each group's transform (around the layer box's centre), its opacity and a wipe's clip rect (over `M.bounds()`). It also moves the picture for the base move (`motionBase()`, the same matrix as `renderView()` plus the move).
- Only a cut drawing is drawn again:
  - Typewriter draws the node from a copy whose `text` is cut to `chars`.
  - Draw on sets `pathLength=1` and a dash offset on every stroked element, with the fill fading in.
- `this.time` is null except while playing or scrubbing in the Animate tool, so editing, the selection box and a save always see the layers at rest.
  - Leaving the tool sets the time back to null (`renderMotion()`).
  - A press on the stage does the same (`motionDown()`) before it selects or drags.
  - The editor never plays by itself.

## Where is the play bar?
- Play/Pause, the clock (m:ss.d, the panel's decimal separator), the scrub with a tick at each layer's start, Repeat (preview only, not saved) and the length.
- On desktop and on a phone held sideways it runs along the stage's bottom (`this.stageBar`), up to an open sheet's edge (`panelCover()`); under 480 px wide it drops the length. `stageInset` is its height and `stageSize()` subtracts it, so a fitted picture stays above the bar.
- On a phone held upright (`max-width: 599.98px`) it comes first in the sheet instead.
- Rebuilt only when what it shows changes (`stageBarKey`): a zoom or a scrub keeps it.
- requestAnimationFrame advances `this.time` and calls only `applyMotion()` and `updateBar()`.
- In the Animate tool K plays or pauses (the video players' key; the button's label says so). Space pans as in every tool; Escape pauses first. M picks the tool (`KEY_TOOLS`). The access part loads after this one, so its key handler runs first and an open `?` sheet takes K.

## What is left out, and what never moves?
- Redaction layers never move: one moving away in an export would show what it hides.
- Not here yet: tracks (AN2), frames, exports and the server's base (AN3 on), timeline keys other than K (AN11).
