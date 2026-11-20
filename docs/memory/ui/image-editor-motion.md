---
name: image-editor-motion
description: The Artifact Canvas's Animate tool: anim and doc.motion, M.motionAt() and its spans, the play bar, the playhead and previews, the Motion popover, the empty panel
module: image_editor
---

## What does a document hold?
- A layer's `anim`: `{ in, loop, out }`, each absent or `{ kind, ms, delay, ease, dir }`; kinds `M.MOTION_IN` (in and out), `M.MOTION_LOOP`; `dir` from `M.MOTION_DIRS`, `ease` from `M.MOTION_EASES`.
- `doc.motion`: `{ duration, loop, base }` through `M.docMotion()`: 5 s, forever (0), no base move by default, 1-60 s; `base` (`M.MOTION_BASES`) zooms or pans the picture over the whole duration (`M.baseAt()`).
- Both ride in the document a version keeps (`image-editor-versions.md`). A save draws every layer at rest.

## When does each phase run?
- `M.motionSpans(l, end)`: `inStart/inEnd`, `loopStart/loopEnd`, `outStart/outEnd`, null for a phase the layer lacks. In starts at its delay, out ends its delay before the end, the loop runs between.
- `M.motionAt(l, t, end)`: `{ opacity, x, y, scale, rot, clip, draw, chars }`, the three phases composed (opacities and scales multiply, offsets and angles add). A null `t` gives the rest state. Out is its in kind played backwards; Slide hides the layer until its start. Tests: `tests/image_editor_motion_test.php`.

## How does the stage preview a moment?
- `applyMotion()` sets each layer group's transform, opacity and a wipe's clip, and moves the picture for the base move. Typewriter and draw on redraw from a cut copy.
- `this.time` is null except while playing, previewing or scrubbing in Animate, so editing, the selection box and a save see the layers at rest. Leaving the tool sets it to null.
- A click on a layer keeps the playhead; only a drag that moves or sizes a layer drops it first (`motionDown()`, `image-editor-select.md`, #9965).
- Play or K plays; a phase just picked plays as a preview (`motionPreview()`, #5062) from the phase's start to its end, then every layer at rest.

## Where is the play bar?
- Play/Pause, Record, the clock (m:ss.d), the scrub with a tick at each start, Repeat (preview only), the length. Split on it: `image-editor-clip-keys.md`.
- On desktop and a phone held sideways along the stage's bottom (`this.stageBar`), up to an open sheet (`panelCover()`); `stageInset` keeps a fitted picture above it. On a phone held upright first in the sheet.
- Rebuilt only when its key changes (`stageBarKey`, which holds every layer's `anim`); each frame calls only `applyMotion()` and `updateBar()`. Timeline keys: `image-editor-animation.md`.

## What is the Motion popover?
- `openMotionPop(opener, refind)` (#10003): rows In, Loop and Out (`motionIn`, `motionLoop`, `motionOut`), each None and that phase's kinds as the panel's own tiles (`motionKinds()`), the kind in use marked.
- Opened by the selection bar's Motion (`image-editor-actions.md`) or a track row's button (`image-editor-tracks.md`); never for a redaction.
- A pick: `setMotion()` and `motionPreview()`, one undo step; it stays open. Direction, length, delay and ease stay in the panel; More options opens the panel on the phase last picked (a video's clip panel, Motion tab).
- Escape or a press outside closes it, the focus back on its button; also outside Animate, on another selection, a drag, playing (not a preview), recording.
- From the bar it opens on the side away from the object; from a row above its button. A redraw that replaces the button finds it again (`refind()`).

## What does the panel offer with nothing to move?
- With nothing selected, the layers to pick; with no layer that may move, Add text and Add a sticker (`motionEmpty()`). The whole animation's fields follow.

## What never moves?
- Redaction layers: one moving away in an export would show what it hides. Tracks `image-editor-tracks.md`, keyframes `image-editor-keyframes.md`, frames `image-editor-render.md`.
