---
name: image-editor-warp
description: The image editor's Warp tool: presets and an envelope mesh kept as l.warp, drawn by mapping the flattened outline; Expand bakes it into the path
module: image_editor
---

## Where does it live?
- Its own part `pg_image_editor_warp.js` (bundled last), tool `warp` after `scissors` in the photo and canvas profiles; labels in `pg_image_editor_strings_warp()` (`tool_warp` in the core's); its maths in the model's WARP section, tested in `tests/image_editor_warp_test.php`.

## What is kept in the document?
- `l.warp` on a path or a drawn shape (not a line or an arrow, whose heads do not bend; `warpable()`): a preset `{ style, bend, h, v }` (style one of `WARP_STYLES`: arc, arch, bulge, flag, wave, fish, rise, squeeze, twist; bend and the horizontal and vertical distortion each -1 to 1), or `{ style: 'mesh', cols, rows, pts }` (3 to 6 each way, `pts` row by row as parts of the box, may lie outside it).
- The shape keeps its own fields under the warp; nothing on the server reads it (the document is stored as it comes).

## How is it drawn?
- `shapePath()` and `outline()` answer `warpShape()` / `warpOutline()` when `warpOn(l)`: the contours (a path's knots and its `subs`, a shape's as Convert to path gives them, `shapeKnots()`) are flattened in about 3 px steps (straight sides too) and each point mapped by `warpPoint()` in the box's frame before the mirror. So the stage, the save's canvas and the hits all follow the warp; the selection box stays the unwarped box.
- The presets bend around the box's middle (formulas in `warpPoint()`); distortion scales one side first. The mesh is a Catmull-Rom surface through its points: an even mesh changes nothing, and a shape's outline on the box's edge moves only with the edge points.

## What does the tool do?
- With one unlocked warpable layer selected: a grid of the styles (None drops the warp), Bend and the two distortions for a preset (a preset picked keeps the bend it had), Columns, Rows (`meshResize()` keeps the shape) and Reset mesh for the mesh, whose points are dragged on the stage (`.pgie-wpt`, own drag kind `warpmesh`; a press elsewhere selects and moves as Select).
- Expand (`warpExpand()`): the drawn points become corner knots of a path (other contours as `subs`), box refitted, angle and mirror kept; the warp, the free deformation's pins (`image-editor-puppet.md`) and a shape's own fields go. One undo step.
- A live text gets a note; where the text-to-outlines part is bundled (`convertToOutlines()`), its button too.
