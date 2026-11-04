---
name: image-editor-area
description: The image editor's Select area tool (E27a): shapes, magic wand, add and subtract, invert, feather, marching ants, doc.selection, outline from pixels; the actions of E27b
module: image_editor
---

## Where does it live?
- Tool `area` ("Select area"), in the photo profile after Redact (not the canvas: it works on the picture). Part `area` (`pg_image_editor_area.js`, bundled after the redact part), labels in `pg_image_editor_strings_area()`, maths in the model's SELECTION section, tests in `tests/image_editor_area_test.php`. Steps E27a (#6611) and E27b (#6678, `plans/image-editor-steps-4.md`); the engine's mask step is `ui/image-editor-area-pixels.md`.

## How is a selection kept?
- `doc.selection = { ops, invert, feather }`, saved with the document (the server keeps the JSON as it is) and undone like any change. An op is `{ op: 'add'|'sub', kind, ring }` for rect, ellipse (96 points), lasso and polygon (rings in the turned space, 0.01 px), or `{ op, kind: 'wand', at, tolerance, contiguous }`: a wand keeps only its seed, its outline is worked out from the picture as it opens.
- `selectionRings()` folds the ops with the shape builder's `clipRings()` (union, difference) and inverts within `M.pictureBox()`. At most `AREA_MAX_OPS` (40) ops; the panel says when it is full.
- On an inverted selection Shift (add) stores a `sub` and Alt an `add`, so the inverted area grows or shrinks as asked. New (the panel's default) starts again and clears invert; a click without a drag deselects.

## How does the wand find its area?
- The picture read back at most 1024 px a side (`areaPixels()`; a picture that cannot be read back shows a note). `wandMask()`: every channel within tolerance × 2.55 of the seed's; contiguous by four neighbours. `maskRings()` walks the edges between pixels in and out with the pixel on its right, turning right where two pixels touch only at a corner (simple rings), straight runs as one edge; `mapRings()` carries them by `placedMatrix × scale`.

## What shows on the stage?
- Marching ants (`renderArea()`, a view hook): a white line under a dark dashed one that moves (still under reduced motion), with every tool but Crop. With Select area also the shape being drawn and the polygon's corners (the first one larger: a click on it closes; double click closes; Escape drops it).

## What does Redact the selection do?
- A redaction layer `shape: 'area'` (`areaLayer()`): the box of the outline and the rings in parts of it (`area`), so it moves, stretches and turns with its box (`areaPoints()`); `feather` from the selection. The stage blurs its mask by feather / 2 (SVG), the save's mask PNG by the same at the output scale (canvas filter), and the region grows by 1.5 × feather (`redactGrow()`); the server burns the soft mask in as any redaction (`image-editor-redact.md`). The tool switches to Redact with the area selected.

## What does the panel do with a selection?
- "With the selection": Erase, Keep only, Copy to a layer, Adjust inside, Redact; each needs an area (`ui/image-editor-area-pixels.md`).

## How was it checked?
- Node tests: each shape, lasso thinning, add, subtract and invert, the wand's tolerance and contiguous fill, outlines of a block, a ring and pixels touching at a corner, a wand inside a selection, the redaction area moved, stretched and turned.
- Browser (sandbox, 2026-11-04): desktop 1280×800 (each shape, Shift and Alt, wand, polygon, invert, undo, Redact, a solid feathered redaction saved over the file and burned in, the selection back on reopening) and phone 390×844 and 844×390 with touch (the sheet, an ellipse drawn by touch).
