---
name: image-editor-motion-tracks
description: The Animate tool's tracks: a row per layer under the play bar, dragging a bar or an end, M.trackSet(), snapping to 0.1 s and to other ends, where tracks are hidden, why the dock keeps its presses
module: image_editor
---

## What are the tracks?
- Under the play bar, in the same dock along the stage's bottom (`this.stageBar` is `.pgie-mdock`: `motionBar()` and `motionTracks()`), there is a row per layer, the top layer first. Hidden layers and redactions get no row (redactions never move).
- On the left is the layer's name, a button that selects it; a press on the lane beside the bars also selects it. On the right, the lane is drawn in seconds:
  - the entrance and the exit are bars, the exit faded;
  - the loop is hatched, so it differs by more than colour;
  - a thin line runs under them for the whole length.
- A ruler of seconds sits on top, and a press on it moves the playhead there. The playhead line over the lanes follows `updateBar()` (`motionEls.head`).
- The selected row shows the grips at its bars' ends. Each bar has a title with its kind and times ("Typewriter · 1.6 → 2.4 s").

## How does a drag change the motion?
- A press on the entrance or exit bar moves it; a press on a grip moves that end. The loop has a grip at its start only: its end is where the exit starts.
- `M.trackSet(l, phase, part, at, end)` turns the dragged edge's new time into the phase. It returns a copy and never changes the layer.
  - In: delay and length.
  - Out: length, and its delay before the end.
  - Loop: its delay after the entrance.
  - A bar keeps its length, an end keeps the other end.
  - Nothing gets shorter than 0.1 s or leaves the timeline.
- Snapping (`M.snapTime()`): to the nearest of `M.trackTargets()` within 6 px, else to the 0.1 s step. The targets are 0, the end, and every phase end of the other layers. A moved bar also snaps by its far end. Shift turns off the step.
- While dragging, the lane's bars move in place and a tip shows the new times. Drawing the lane again would drop the pointer the bar holds, which is why `move` and `up` listen on `window`.
- Letting go makes one `change()`, so one undo step. Then the panel's sliders show the new delay and length.

## Why does the dock stop its presses?
- The dock lies inside `.pgie-stage`, whose `pointerdown` runs the tool's press: Animate's `motionDown()` brings the layers to rest and starts a select drag. A press on a row's name lost the selection to it, and a press on the scrub first reset the playhead.
- `renderMotion()` stops `pointerdown` on the dock; its own controls handle the press before that.

## Where are tracks not shown?
- On a phone held upright the play bar is in the sheet and there are no tracks.
- Below 600 px of height (a phone held sideways) the tracks are hidden by CSS. The bar stays, and the panel's fields set the same numbers.
- When the dock is cut short by an open sheet (`.pgie-mshort`), the name column narrows to 112 px.
- The panel says how tracks work under the timing sentence (`motionTracksNote`), only where they show.
- Keys on the timeline (moving a bar by keyboard) are AN11's.

## Where is it tested?
- `tests/image_editor_motion_test.php`: `trackSet()` for every phase and part, with its clamps, and the snap order and targets.
