---
name: image-editor-builder
description: The image editor's Shape builder: pathfinder buttons, the drag that merges or removes overlap regions, and the model's own path booleans (flatten, Martinez-Rueda, refit)
module: image_editor
---

## Where does it live?
- Tool `builder` (label "Shape builder") after `scissors` in the photo and canvas profiles; part `builder` (`pg_image_editor_builder.js`, bundled last), labels in `pg_image_editor_strings_builder()`, maths in the model's BUILDER section, tests in `tests/image_editor_builder_test.php`. Steps: E24 (`plans/image-editor-steps-3.md`).

## What do the buttons do?
- With two or more selected closed shapes or paths (visible, unlocked; open paths and pen strokes are not areas): Unite, Subtract front (the bottom one without the rest), Intersect (the area all share), Exclude (odd overlaps), Divide (every piece the outlines cut). One undo step; the result is selected.
- Results are path layers (rot 0, no mirror) in the style of the top layer (Subtract front: the bottom one; Divide: each piece's top cover), placed where the top one of the replaced layers was. Strokes do not count as area.

## What does the drag do?
- The panel shows the regions as dashed outlines: those of the selection when two or more are selected, else of every visible area (the top 12). A drag fills the regions it passes; on release they merge into one path in the style of the first, or with Alt or the panel's Remove switch (for touch) they are removed. Every layer covering a touched region is replaced by its untouched regions, as separate paths.
- A click (one region touched, merging) unites the shapes stacked over that region whole, in the top one's style (`M.builderStack()`); on a region of one shape it does nothing; Alt-click removes the region. Before #6613 a click merged just that region and cut the shapes into it and the rest, which read as the merge tool cutting them. With fewer than two selected the panel offers Select all shapes.

## How are the booleans built?
- `layerRings()`: the outline (`shapeKnots()`, or the path's knots and `subs`) mapped into the rotated space and flattened, 0.25 px from the curve, points on a 1/1024 px grid.
- `clipRings(a, b, op)`: Martinez-Rueda sweep (union, intersect, difference, xor; even-odd inputs). Crossings go on a 2^-20 px grid and b's points within 1e-5 px of a's are welded to them: the same corner found from pieces in another run differed in its last digits and broke shared edges. Rings are joined edge by edge and split where they meet themselves; `nestRings()` winds outer rings positive and holes negative, so nonzero and even-odd fill alike.
- `builderRegions()` cuts each region from the items' own outlines (overlap of its members less the others) rather than pieces from pieces, which shared near-coincident edges.
- `ringsPath()`: corners (turns over 0.6 rad) stay sharp knots, straight runs lines, curved runs Schneider-fitted cubics within 0.5 px; the largest ring is `knots`, the rest `subs`, the box by `refitPath(l, knots, subs)`.

## How was it checked?
- Node tests: the five operations on squares and circles (areas against the true lens), holes, a circle inside a hole, edges and corners that touch, regions, merge and remove. A random fuzz (area identities over 400 pairs, regions of 5 shapes, merges) passed before the PR; it is not in the suite.
