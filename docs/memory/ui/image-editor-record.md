---
name: image-editor-record
description: The Animate tool's recorded move (AN12): Record beside Play, the clock at 0.5x-2x while a layer is dragged, M.recordThin()/recordApply() into linear keyframes, the phone's pill
module: image_editor
---

## What does it do?
- Part `record` (`pg_image_editor_record.js`, bundled after `access`, labels `pg_image_editor_strings_record()`). Record (a red dot beside Play, and the Path tab's "Recording a move" button) arms the one selected movable layer; the button turns into a stop square and the clock reads REC.
- A press on that layer where it shows at the playhead starts the clock there; the place is read every animation frame, letting go stops. Escape cancels (the document as it was), another tool ends it unsaved. One undo step (`history.push()` of the document before, on release).
- A box corner (when the box sits at rest) records the size around the layer's centre; a second finger (`startTwist()` wrapped) records the turn and the fingers' middle as the place.
- While recording the samples are the keys raw (no thinning per frame), the path red, the span red on the lane (`.pgie-trrec`); `keyFollow()` leaves the Path tab alone.

## How are samples turned into keys?
- Model section `RECORD`: `M.recordClock(from, elapsedMs, rate)` (rate 0.5, 1 or 2, else 1: at 0.5x two real seconds are one second of animation, so it plays twice as fast).
- `M.recordThin(samples, px, frame)`: times on the 10 ms grid, then Douglas-Peucker where a sample stays if the straight even move between its neighbours is further than px from it within a frame (1000/60 ms) of its time; turn 0.5°, size 0.005. Keys are linear.
- `M.recordApply(l, samples, end, px)`: keys inside the recorded span replaced, the others kept; size, turn and opacity not recorded come from the old keys at that moment; past the end the document lengthens to the next 0.1 s, up to `MOTION_MAX` (samples past it dropped). The editor passes px = one screen pixel (`1 / view.k`, at least 1).
- Tested under Node in `tests/image_editor_record_test.php`.

## What does a phone show?
- Record sits on the sheet's play bar; arming folds the sheet (`sheetOpen`, restored after), a pill over the stage's top holds Stop, REC, the clock and the speed (a tap cycles it), a hint sits at the stage's bottom, the selection bar hides.

## What was tried?
- Sandbox at 1280×800 and 390×844 (touch): arm, drag, path, undo and redo, 0.5x (2.3 s of drag became 1.1 s), Escape. Not tried: a corner's size, a two-finger twist, over a video (V6 not built).
- AN9's diamonds all sat at 0 s: `keyframes.js` had a local `pct()` that `X.pct` (the percentage label) overwrote; now `lanePos()` (#7447).
