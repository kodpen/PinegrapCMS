---
name: image-editor-puppet
description: The image editor's free deformation (puppet): pins on a path, shape or outlined text, rigid moving least squares, kept as l.puppet, drawn after the warp; Expand bakes it
module: image_editor
---

## Where does it live?
- Its own part `pg_image_editor_puppet.js` (bundled after warp), tool `puppet`, the last mode of the Vector rail item (`M.RAIL_GROUPS.vectors`, `image-editor-rail.md`), after Warp in the photo and canvas profiles. Labels in `pg_image_editor_strings_puppet()` (`tool_puppet` in the core's); its maths in the model's PUPPET section, tested in `tests/image_editor_puppet_test.php`.

## What is kept in the document?
- `l.puppet = { pins: [{ rest: [u, v], at: [u, v] }] }` on a layer `M.warpable()` takes (a path or a drawn shape, not a line or an arrow), each point as parts of the box before the mirror, at most `PUPPET_MAX` (32) pins. `puppetOn()` is false with no pins or a broken one, and the drawing ignores it then. Nothing on the server reads it.

## How is it drawn?
- `warpContours()` flattens the outline, applies the warp when `warpOn()`, then moves each point by `mlsRigid()`: the rotation and shift that best carry the pins (weights 1 / distance squared, in the box's pixels) to their moved places (rigid moving least squares, Schaefer et al. 2006, in its 2D closed form: the angle from the weighted dot and cross sums). `shapePath()` and `outline()` go through it when `warpOn(l) || puppetOn(l)`, so the stage, the save's canvas and the hits follow the pins; the selection box stays the unbent box.
- Selecting follows the drawing (#7001): `hitLayer()` and the marquee's `layersInBox()` test a warped or pinned layer on `drawnExtent()` (its box joined with the drawn outline's extent, mirrored), so a part bent out of the box is hit; `layerRings()` (the shape builder, a press inside an unfilled shape) gives the drawn contours from `warpContours()`. Warp's mesh gets the same.
- One pin, or pins on one spot, only shift the shape: the turn is skipped when the pins' weighted spread is near zero, else rounding picked a random angle.
- `warpSource()` drops `puppet` as well as `warp` before taking a shape's knots: `shapeKnots()` reads `outline()`, which would loop.

## What does the tool do?
- With one unlocked warpable layer selected: a press on the shape (inside, or within 6 screen px of its line) adds a pin and drags it, one undo step; a press on a pin drags it; Alt and a press, or Delete/Backspace on the chosen pin, remove it, and the arrows move the chosen pin 1 px, with Shift 10 (document pixels through the turn and mirror, `M.puppetNudge()`; a run of presses is one undo step); its key runs before the layer keys, which move or delete the layer again once no pin is chosen. On a phone the selection bar puts Remove pin first while a pin is chosen (`chosenPin()`), its Delete still removing the layer (E29, #7622). A press elsewhere selects and moves as Select. The panel shows the count, Remove pin, Remove all pins and Expand.
- A pin added to a bent shape gets its rest place from `puppetRest()`: the bend undone by fixed-point steps, so it sits where it was pressed; the rest of the shape still shifts a little, as moving least squares refits around the new pin (up to 13 px under a 140 px bend).
- While the tool is on, the selection box gets `.pgie-pinning`, which hides its resize, rotate and type handles (they looked like pins, and a press there adds one); the chosen pin has a white edge so it shows on a Blue shape.
- Expand is the warp part's `expandWarp()`: the drawn outline becomes corner knots of a path, warp and pins dropped. The context menu's Expand shows for a pinned shape too. A live text gets a note and Convert to outlines.
