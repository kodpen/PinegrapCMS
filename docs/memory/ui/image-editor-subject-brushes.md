---
name: image-editor-subject-brushes
description: The image editor's Keep and Remove brushes on a selected subject (E28b, #6889): strokes as hard labels of a new cut, and the guided-filter soft edge of the layers made from it
module: image_editor
---

## How do the Keep and Remove brushes work?
- With a subject op in the selection (`M.subjectIndex()`: the last one) the Select area panel shows "Refine the subject": Keep or Remove (they set `areaDefaults.kind` to `keep`/`remove`, so no shape tile is on), the brush size in screen pixels, and the soft edge.
- A stroke (`M.subjectStroke()`: `{ keep, r, pts }` in the turned space, points a quarter radius apart) goes to the cutter with all the op's earlier strokes (at most 60), carried into its pixels; the cut runs again from the op's `box` (`strokeLabels()` in the worker): the box, grown to take in each Keep stroke with twice its radius, is left to the cut, the rest is background, stroke pixels are hard labels, a later stroke over an earlier one. The new rings and the strokes go in as one undo step; a selection changed meanwhile drops the answer. Each cut starts from the box, not the last mask, so the result depends only on picture, box and strokes.
- The strokes show on the picture (green Keep, red Remove) while a brush is picked; the one being cut shows till the answer.

## What is the soft edge?
- `edge` on the subject op (units of the turned space, 0-20; a new subject gets 4, one made before E28b none, so hard). A layer made from the selection (`pushArea()`: Erase, Keep only, Cut, Adjust inside) takes it as `matte` (`M.selectionMatte()`); Redact does not, a redaction stays hard.
- At full size, never on the stage: the save's mask (`areaMasks()`) and Copy or Cut to a layer draw the shape, then `M.matteMask()` runs a colour guided filter (He, Sun, Tang; eps 1e-4) with the picture under the mask as guide, radius `matte` at that scale. It works in 256 px tiles and skips a tile whose alpha is one value 2r round it, so a large mask costs its edge only. A picture that cannot be read back keeps the hard edge. No server change: the mask PNG carries the alpha.
- The filter brings a hard mask of a soft (blurred) picture edge close to it (the test: error a third or less) and leaves a mask on a sharp colour edge sharp; it cannot move an edge that is wrong by more than r, which is the brushes' work.

## How was it checked?
- Node (`tests/image_editor_subject_test.php`): Keep brings back a background-coloured hole, Remove a joined bar, a Keep stroke outside the box grows the cut; the labels; the session; the subject index, matte and stroke; the filter on a soft and a sharp edge and on a mask without edge.
- E28b (static page, 1280×800, 390×844 and 844×390 with CDP touch): a ring with a background-coloured hole and a joined bar; Keep over the hole and Remove over the bar brought the area to the disc's within 1 %; undo and redo; Erase's mask with the soft edge in about 0.2 s.
