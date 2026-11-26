---
name: designer-options-layers-drawing-shape
description: Clip shape and Mask as drawn for DO14, with the canvas and the keys of DO13 (options-layers.html, boards C1 to C4, Z1): the two rows, each shape's rows, what is written, the dashed outline
---

## Where are the two rows?
- Style tab, Shadow and effect, under "More": a sub-head "Shape" after "Blend", with "Clip shape" and under it "Mask". An element may have both. Each is a list whose lines carry a small sign of the shape.

## What does "Clip shape" offer?
- None, Circle, Ellipse, Rounded inset, Slant, Arrow. Each shape brings its own rows:
- Circle: "Size", "Center" (the nine places). Ellipse: "Width", "Height", "Center".
- Rounded inset: "Inset", "Corner".
- Slant: "Edge" (Top, Bottom, Both), "Amount", "Direction" (Up to the right, Up to the left).
- Arrow: "Points" (Right, Left, Down, Up), "Point".
- It writes `clip-path` on the element's own class; under a state the shape is that state's, as every row of the section. A hand-written shape reads "Custom" and is kept until another is chosen.
- The first time a shape is chosen, one line: "What the shape cuts away cannot be clicked, and a shadow is cut with it."

## What does "Mask" offer?
- None, Fade, Picture.
- Fade: "Edge" (Top, Right, Bottom, Left) and "Length", how much of the element the fade takes.
- Picture: "Picture" with the folder button, "Size" (Contain, Cover, Original), "Position", and the line "The picture's solid parts show the element; its clear parts hide it." With none chosen yet: "Nothing is hidden until a picture is chosen."
- It writes `mask-image` with its size and position, and the `-webkit-` twin, on the element's own class.

## What does the canvas show?
- The real background and the real shape. While a row is dragged (a stop, an angle, a size, a shape's amount) the selected element shows the value at once, as the Filter sliders do, and it is written when the handle is let go.
- With a Clip shape or a Mask the selection's outline is dashed and follows the element's whole box, so what is cut away shows as the gap between the outline and the shape.
- Nothing is dragged on the canvas: stops, layers and shapes are changed in the panel only.
- On an image, "Clip shape" cuts the element as drawn; the picture's Focus and crop stay in the Content tab.

## Which keys, and what is read out (DO13)?
- Tab reaches the list as one stop, then the open layer's rows; the strip is one stop. Up and Down go from layer to layer, Alt with them moves the layer. Enter opens or closes a layer; on the strip it adds a stop in the widest gap. Left and Right go from stop to stop, with Shift they move the stop by 1 %. Delete removes the layer or the chosen stop.
- Read out: "Background layers, 3"; a line "Gradient, linear 180 degrees, 2 stops, layer 1 of 3"; a stop "Stop 2 of 3, Primary, 45 per cent opacity, at 55 per cent". A move, an add and a removal are announced.
- Each layer or stop added, removed or moved is one undo step; a drag is one step from press to release.
- Measures: the strip 18 px high with a 6 px corner, a handle 12 px with its point, a layer's line 34 px, its small picture 22 px.

Related: docs/memory/designer/designer-options-layers-drawing.md, docs/memory/designer/designer-options-effects.md, docs/memory/designer/designer-options-transform.md
