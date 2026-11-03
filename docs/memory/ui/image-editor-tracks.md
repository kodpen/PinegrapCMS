---
name: image-editor-tracks
description: The Animate tool's tracks under the play bar: a row per layer, the bars from M.trackBars(), dragging a bar or an end (M.trackEdit()), snapping, the dock hook in the motion part, no tracks on a phone
module: image_editor
---

## What do the tracks show?
- Part `tracks` (`pg_image_editor_tracks.js`, section `TRACKS` of the model, labels `pg_image_editor_strings_tracks()`), under the play bar while the Animate tool is open: a ruler with the seconds, then a row per layer, the top layer first (none for a hidden layer or a redaction), its name a button that selects it (Shift adds). Under a phase's fields the panel says how to drag (`tracksNote`).
- A row's bars come from `M.trackBars(l, end)` (`M.motionSpans()`): the entrance solid, the loop hatched up to the exit, the exit faded, a thin line under them while the layer only stays.
- The selected layer's bars get grips at both ends (not the loop) and a tip with the kind and its times ("Typewriter · 1.6 s → 2.4 s"). A red line is the playhead (`--pgie-trat`), faded at rest; a press on the ruler moves it there.

## How does a drag change the timing?
- `M.trackEdit(l, phase, grip, dt, end, { targets, slack, fine })` gives the phase's patch from the layer as it was at the press:
  - `move` shifts the bar: an entrance's `delay`, an exit's `delay` counted back from the end.
  - `start` or `end` moves that end and keeps the other: `delay` and `ms` together.
  - A loop moves only its start (its `delay` after the entrance); it always runs to the exit.
  - Clamped to 0 and the end, and a bar is at least 0.1 s.
- Snapping: an end within 6 px of an end of another layer's bar, 0 or the end takes it (`M.trackTargets()`); else a tenth of a second. Shift: a hundredth, no snapping.
- The drag moves only the bar's element; on release one `change()` (one undo step). While it runs `tracksKey()` keeps its last key, so the play bar is not rebuilt under the pointer.

## How does it hang on the motion part?
- `Editor.motionDocks` in the motion part: `{ key, node, update, note }`. `renderMotion()` puts `node()` under the bar in a `.pgie-mdock` (the absolutely placed box along the stage's bottom, so `stageInset` counts the rows), joins `key()` to the bar's key, and `updateBar()` calls `update()` on each playhead move.
- The dock stops `pointerdown`, `dblclick` and `wheel`: else the stage's tool took a press on a bar as one on the picture (the selection cleared) and a wheel zoomed. A press while playing pauses first.
- The part sets the tool's hint to `hintTracks`; `note()` adds the panel's line.

## Where are there no tracks?
- Under 600 px wide or under 500 px high (a phone either way up): the panel's delay and duration sliders do it (`image-editor-motion.md`).
- The rows scroll past 150 px. Keys on the timeline are AN11's; keyframes on the tracks AN9's.
