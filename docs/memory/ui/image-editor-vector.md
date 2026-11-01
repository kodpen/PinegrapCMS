---
name: image-editor-vector
description: The image editor's vector pen: path layers as boxes with knots inside, drawing point by point, editing points and handles, smooth and corner, open and closed, convert to path
module: image_editor
---

## What is a vector path?
- A layer of type `path`: a box (`x, y, w, h, rot`, `mirror`) like a drawn shape (`image-editor-draw.md`), with `knots` as parts of the box (`[{ x, y, ix, iy, ox, oy, s }]`: the point, the handle in, the handle out, `s` for smooth; handles may lie outside 0-1) and `closed`. Its style is a drawn layer's: `color`, `stroke`, `dash`, `opacity`, and `fill` when closed.
- The model's drawing functions know it: `shapePath()` gives its cubic `d` (`knotsD()`), `outline()` its sampled curve for `hitShape()`, `fillFor()` a fill only when closed (`isClosed()`). So it draws on the stage and in the save like the shapes, and follows the picture's turns and flips (`mapLayer()` mirrors it).
- After its knots change, `refitPath()` fits the box to the curve again (`knotBounds()`) and keeps the angle and mirror, so the rest of the path stays where it was.

## How is a path drawn?
- With the Vector tool a click places a corner point; a drag from a new point pulls its handles (a smooth point). A click on the first point (with three or more) closes the path; a double click (a double tap), Enter or the panel's Finish ends it open; Escape or Cancel drops it. Switching tools keeps the path drawn so far.
- The path being drawn is an SVG over the stage (`.pgie-vover`), not a layer: one undo step adds the finished path. A double click's second point on top of the first is dropped (`draftKnots()`). Fewer than two points draw nothing.
- New paths take the Draw tool's style (`newShape('path')`, the same defaults).

## How are points edited?
- Edit points (the panel button, or a double click on a path) shows its points as square handles (`k<i>`) instead of the box's handles (the type's `box` is false while `nodeEdit` is set); the chosen point also shows its handles (`i<i>`, `o<i>`) and their lines.
- Dragging a point carries its handles (`moveKnot()`); dragging a handle of a smooth point turns the other in line, keeping its length (`moveHandle()`); Alt breaks it into a corner. A double click on the path adds a point without changing the curve (`nearestOnPath()`, `splitAt()`); Delete removes the chosen point (a path keeps two; under three it opens). Enter, Escape or a press away from the path ends the editing.
- Smooth and Sharp act on the chosen point, or on every point when none is chosen (`smoothKnot()`: handles along the neighbours, a sixth of their distance; `cornerKnot()`). Closed toggles `closed` (three points or more).

## What does convert to path do?
- With drawn shapes selected, the Vector panel offers Convert to path (`shapeKnots()`): an ellipse becomes four smooth points (kappa 0.5523), a rounded box eight, a square box, triangle, polygon, star and bubble their corners (a bubble's rounding is not kept), a line or arrow its two ends open (the head is dropped), a pen stroke its smoothed points made smooth. Style, angle and mirror stay; the shape's own fields go.

## Which core hook did it add?
- `Editor.escapes` (`pg_image_editor.js`): Escape reaches a tool before it clears the selection. The pen's key handler goes first in `Editor.keys` (`unshift`) so Delete removes a point, not the path.
