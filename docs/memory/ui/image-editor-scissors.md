---
name: image-editor-scissors
description: The Design Canvas's Scissors tool: a click cuts a path or shape at a point, a drag cuts every path the line crosses; the model's cut functions
module: image_editor
---

## Where does it live?
- In the vector part (`pg_image_editor_vector.js`), as the tool `scissors` after `vector` in the photo and canvas profiles; its maths in the model's VECTOR section (`nearestCut()`, `lineCuts()`, `cutKnots()`), tested in `tests/image_editor_scissors_test.php` (#6355). Paths themselves: `image-editor-vector.md`.

## What does a click or a drag cut?
- A click cuts the top path or drawn shape whose outline lies within 8 px plus half its stroke, at the nearest point (`nearestCut()`: `nearestOnPath()` refined between its samples).
- A drag of 4 screen px or more cuts every path and shape where the line crosses (`lineCuts()`: a crossing on a knot counts once). The line shows dashed while dragging.
- Hidden and locked layers are not cut. A shape is converted to a path first, its own fields dropped as by Convert to path.

## What pieces come out?
- `cutKnots()`: an open path splits at each cut (a cut on an end does nothing); a closed one opens at its first cut, the cut point being its first and last knot, and further cuts split it more. Cut ends are corners without the handle past the cut. Every piece is open.
- The layer becomes its first piece, the other pieces are copies just above it with the same style; one undo step; the pieces are selected.
