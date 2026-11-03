---
name: image-editor-keyframes
description: The Animate tool's keyframes (AN9): anim.keys and M.keyAt() composed in M.motionAt(), the Path tab, dragging a layer at the playhead, diamonds on the tracks, the path on the stage
module: image_editor
---

## What does a layer hold?
- `anim.keys`: `[{ t, x, y, scale, rot, opacity, ease }]` beside `in`, `loop`, `out` (section `KEYS` of the model). `x`, `y` move the layer in document units, `scale` and `opacity` multiply, `rot` adds degrees: the same state `M.motionAt()` composes, so keys add to the presets and reach the stage, the frames and every export unchanged.
- `M.keyList()` cleans them: objects only, numbers, scale 0.05-10, opacity 0-1, ease from `M.MOTION_EASES` (default in-out), in time order. A layer with only keys keeps its `anim` (`setMotion()`, `editKeys()`); `M.hasMotion()` counts keys.
- Nothing on the server reads them: they ride in the document a version row keeps. A still save sees the rest state.

## How does a key play?
- `M.keyAt(l, t)`: before the first key and after the last the state holds; between two, the later key's ease runs from the earlier to the later. No keys or a null playhead: null.
- `M.keySet(l, t, patch, end)`: times round to 10 ms and stay in 0..end; a key within 5 ms is changed (`M.keyIndex()`), else a new one from the state there. The first key away from 0 brings a key at rest at 0, so the layer moves from where it was put.
- `M.keyMove()` (a key already at the target gives way), `M.keyDrop()`, `M.keyPath(l, n)` (a dot per key, the centre sampled n steps between the first and last).
- Tested under Node in `tests/image_editor_keyframes_test.php`.

## How is a key set?
- Part `keyframes` (`pg_image_editor_keyframes.js`, bundled after `tracks`, labels `pg_image_editor_strings_keyframes()`).
- Stage: with the playhead standing away from 0 (scrubbed, not playing) a press on a layer where it shows at that moment (`keyHit()`: its motion offset taken off the press) drags its place there: one change on release (`'drag'` key, one undo step). The rest place never moves.
  - Shift, the turning handle, a box handle not well inside the layer, or the playhead at 0 or at rest: the old press (`motionDown()`: back to rest, select, move or resize).
  - On touch a box handle's 44 px target covers a small layer's middle: a press 10 px inside the box sets a key.
- Path tab (the motion part's `Editor.motionPanes`), one layer selected: the keys as chips (a press seeks), at the playhead either Add a keyframe or the key's fields (across, down, size %, turn °, opacity %), its ease (not on the first key), Remove; Remove all keyframes.
  - The tab follows the playhead (`keyFollow()` from the dock's `update()`), except while playing and while a phone's scrub in the sheet is held.

## What shows on the stage and the tracks?
- A diamond per key on the layer's lane (the tracks part's `Editor.trackMarks`): a press seeks there and opens the Path tab, a drag moves the key (0.1 s steps, Shift 0.01 s, one change on release), Delete or Backspace on a focused diamond removes it. No tracks on a phone: the Path tab does it.
- `.pgie-keypath` over the stage, under the selection: a dashed line through the selected layer's keyed places, a dot per key, a ring where it is now.
- The selection boxes follow where their layers show at the playhead (a CSS `translate`) and hide their handles while moved: a handle resizes at rest, which would jump to the pointer.
- Tried in the sandbox at 1280×800 and 390×844 (touch): drag at 2 s, the path, a diamond moved to 3 s, Delete, undo, the Path tab's fields.
