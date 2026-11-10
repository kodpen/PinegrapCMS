---
name: image-editor-clip-timeline
description: Editing clips on the tracks (V3): one row per source cut, trim, split, move, snapping, ripple, the length following the clips but never cutting another layer's motion (V3b)
module: image_editor
---

## Where is it?
- In the `clip` part (`pg_image_editor_clip.js`, "the clip rows on the tracks"), the model's `CLIP` section (`clipRow()` … `clipJoins()`), labels in `pg_image_editor_strings_clip()`, rules after the clip's in `pg_image_editor.src.css`, tests in `tests/image_editor_clip_test.php`. Added in V3 of `plans/image-editor-video-steps.md` (#8510); the length rule, filmstrip and waveform in V3b. The layer itself: `image-editor-clip.md`.
- The tracks part draws a row through `Editor.trackRowMakers` (fn(l, end): a node, false for none, undefined for the usual row): a clip gives its row, drawn where the topmost clip of the row is in the stack.

## Which clips share a row?
- `l.row`: a split gives both parts the first one's row key (`M.clipRowKey()`: `row`, else the id). A clip with no `row` has a row of its own; a later picture in picture (V6) gets one too.
- A bar per clip: a filmstrip and the clip's sound as a waveform (`image-editor-clip-bar.md`), "name (n) · source in → source out" (`M.clipPart()`: the n-th part of the same file by its start); a dashed cut with scissors where one source goes on in the next clip with no gap (`M.clipJoins()`).

## What does a drag do?
- The middle moves the start (`M.clipMove()`): either end snaps; never before 0, over a neighbour on its row or past 60 s.
- The start grip trims the source in and keeps the end; the end grip trims the source out (`M.clipTrim()`): inside the file, at least 0.1 s of source, the speed counted (at 2× 0.5 s on the timeline is 1 s of source). The start stops at the clip before it on the row. A trimmed end moves the clips after it on the row by as much (`M.clipRipple()`), all inside 60 s.
- Snapping: within 6 px of the playhead, another clip's end, any layer's motion bar end, 0 or the end; else 0.1 s steps; Shift: 0.01 s, no snap. An amber line shows a snap. While an end is dragged the stage shows that end's frame and a pill names its source time and the clip's length; Escape cancels; one `change()` on release.

## How does the length follow?
- `M.clipApply(doc, patches)` puts the patches on the clips and sets `doc.motion.duration` with `M.clipLength()`: the last clip's end, but never before what a shown layer that is not a clip needs for its motion (`M.clipMotionNeed()`), a tenth up, 1-60 s.
- A layer's need: its entrance (delay + length), the loop's delay and 0.1 s, its exit (delay + length), in a row, as the exit runs back from the end. A layer with no motion, a hidden one, a clip's own motion and doc.sounds hold nothing.
- Owner (2026-11-10, #8521): a clip edit never ends the document before another layer's motion (an end card after the video); with the clips last the length follows them, shorter too. Before V3b the next clip edit cut such a layer.

## What does the panel add?
- "From the source" and "On the timeline" (in → out and the length), "Same source" beside the size when another layer plays the same file, Split clip (`M.clipSplit()` at the playhead, 0.1 s of source on both sides, else a message; the second part right above the first, selected; the first keeps the entrance, the second the exit), and in Animate a note on dragging.

## What is left?
- Keys and captions (V11), touch-sized handles and the phone's rows (V10: no tracks under 600 px).
