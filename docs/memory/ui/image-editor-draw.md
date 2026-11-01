---
name: image-editor-draw
description: The image editor's Draw tool: nine kinds and the eraser, drawn layers as boxes with their drawing inside, line ends, corner radius, style fields, turns and flips
module: image_editor
---

## What does the Draw tool draw?
- Nine kinds (screen 04): arrow, line, rect (labelled Box), ellipse, triangle, polygon (3-12 sides), star (4-12 points, inner ratio 20-80 %), bubble, pen; plus the Eraser tile. A caller's mini bar tools `arrow`, `line`, `rect`, `ellipse`, `pen` each draw one kind with the same panel minus the grid.
- A drag draws; Shift makes a square (from the corner the drag began at) or snaps a line to 45°. A click without a drag selects the layer under it (or clears the selection); a click with the pen leaves a dot. A press on a selected layer or its handle moves or resizes it: Draw is one of `Editor.selectTools`.

## How is a drawn layer stored?
- A box like every layer (`x, y, w, h, rot`, `opacity`) with its drawing in the box's own frame; `mirror: true` mirrors it inside the box. So Select, align, snapping, duplicate and the layer keys work unchanged.
- A line or arrow runs along its box's middle from the left end to the right (the head); the box is as long as the line and as thick as its stroke or arrow head (`lineBox()`, `lineThickness()`). Its handles are its two ends (`box: false`); dragging one rebuilds the box from the ends (`lineEnds()`).
- A pen stroke keeps its points as parts of its box (`trace`, 0-1, four decimals; `penLayer()`), so resizing the box scales it. `smooth` (0-10) is a moving average applied each time the stroke is drawn (`smoothTrace()`); the raw points stay, so it can change later.
- Style fields: `color`, `stroke` (picture pixels; Thin, Medium, Thick are 0.22, 0.42, 0.85 % of the longer side, `strokeFor()`), `fill` (none, solid, soft = the colour at 28 %; closed kinds only), `dash` (missing = solid, `dashed`, `dotted`), `radius` (rect and bubble, at most half the shorter side, a bubble less: `maxRadius()`), `sides`, `points`, `inner`, `heads` (end, both) and `head` (fill, open, dot).
- `shapePath()` gives one `d` (and an arrow's head) in the box's frame; the stage's SVG and the save's canvas (`Path2D`) both draw it. `hitShape()` hits the outline within half the stroke plus slack, the inside only when filled.

## What does the corner-radius handle do?
- A rect or bubble shows a dot inside its top-left corner (at least 12 screen px in), the panel the value in px. Dragging it sets `radius` to the dot's distance along the diagonal, cut to `maxRadius()`. A bubble is drawn with its largest radius unless one was set.

## What does the eraser do?
- It cuts visible, unlocked pen strokes where it passes (`eraseTrace()`): the points under it go, each remaining run becomes its own pen layer with the same angle, mirror and style (the first keeps the id). Shapes are deleted with Delete, not erased. Its size is Thin/Medium/Thick × 2.5, at least 8 screen px; a ring shows it while dragging.

## How do the picture's turns and flips carry a shape?
- `mapLayer()` moves every non-upright box by its centre, keeps its size and adds the turn to its angle; under a mirror it negates the angle and toggles `mirror`. So an arrow points the other way after a flip and a triangle points down after a vertical one. Before this a box swapped its sides and kept its angle, which turned a drawing the wrong way.

## What does the panel offer?
- The grid (Draw only), colour (eight swatches and a picker), line width, fill, corner radius, sides, points and inner ratio, arrow heads and head style, smoothing, line style, opacity; with drawn layers selected also duplicate, forward, backward, delete. A change goes to the selected drawn layers (where the field applies) and to what is drawn next; a slider drag is one undo step. Select shows the same controls for a selection of one kind (`layerTypes[kind].panel`).
