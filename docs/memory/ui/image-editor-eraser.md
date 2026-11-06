---
name: image-editor-eraser
description: The image editor's eraser (Draw panel): pen strokes cut, shapes and paths masked (l.erase), locked layers kept, the mask on the stage, in a save and in the SVG, refits that keep it in place
module: image_editor
---

## What does the eraser take away?
- What it passes over from every visible, unlocked pen stroke, drawn shape (box, ellipse, triangle, polygon, star, bubble, line, arrow) and path, outlined text included (owner, 2026-11-05: in the same panel as the shapes, an eraser that left them alone looked broken). A locked or hidden layer stays; a text, a sticker or a picture is not erased.
- A pen stroke is cut (`eraseTrace()`): the points within reach of the eraser's whole move since the last one go (`from`, so a fast drag leaves no dots), each run left is a pen layer of its own, the first keeping the id.
- A shape or a path keeps its type, style and size; what the eraser took is a mask: `l.erase = [{ w, h, r, pts }]`, a stroke per drag over the layer, its points in the layer's frame before the mirror, in pixels of the `w x h` box the layer had then, `r` the radius (`M.erasable()`, `M.eraseOn()`, a broken stroke left out by `M.eraseStrokes()`). A resize stretches the mask with the drawing; a move, a turn or a mirror carries it.

## How does a drag erase?
- `eraseAt()` (draw part) tests each erasable layer with `M.eraseTouches()`: steps of half the radius along the move from the drag's last point, each as `hitShape()` tests (inside only when filled, else on the line), on the outline drawn once (`hitLines()`); a layer without a warp is passed over when the move stays away from its box. A layer it touched takes the drag's later points too (`d.open[id]`), so its stroke follows the eraser; `M.eraseAdd()` skips a point within a quarter of the radius of the last and goes on in a new stroke past `ERASE_POINTS` (4000). One drag is one undo step.
- A press with the eraser erases even on a selected layer's handle: on touch their 44 px targets covered a small shape, and the press resized it instead (the owner's report, 2026-11-05).

## How is the mask drawn?
- Stage and SVG: `drawnNode()` puts a `<mask>` (white area well past the box, the strokes as black round polylines, scaled by `l.w / w`, `l.h / h`) and the drawing under it, both in the box's frame, so the saved SVG (`svgText()`) carries it. The draw-on effect (motion part, `drawCut()`) skips what is inside a `<mask>`.
- Canvas (the save's overlay, frames, thumbnails): `erasedPaint()` draws the layer on a canvas of its own with the same transform, cuts the strokes out (`destination-out`), then puts it onto the target, whose opacity, fade and clip apply. A shadow falls from what is left.

## What keeps the mask in place?
- `refitPath()` (Convert to path, a moved, added or deleted knot, the scissors' pieces) and `warpExpand()` give the new box's mask (`M.eraseShift()`): the strokes stay where they were on the document. The shape builder makes new layers from the outlines and drops the mask.
