---
name: designer-canvas-guides-geometry
description: Canvas guides' pure geometry (CG1 #11517, PGCanvasGuides): box distances, inner distances, nearest spacing step, column under a pointer, equal sibling gaps
---

## What is it?
- `window.PGCanvasGuides` in `pinegrap/assets/js/pg_canvas_guides.js`: the numbers behind Measure, the spacing bands, the column handles and the equal-gap marks (plan `designer-canvas-guides`). Pure: plain boxes and numbers in, plain objects out; no element, no listener.
- Bundled first in the part `bands` (CG3, `designer-canvas-guides-bands.md`); a later guide's part lists it too.

## What does it take and answer?
- A box: `{ left, top, right, bottom }` or DOMRect-like `x / y / width / height`, all in the canvas frame's zoomed space. `zoom` (default 1) turns lengths back into page CSS pixels for the numbers; positions stay in canvas space.
- A line: `{ axis, from, to, at, value }`; axis `x` runs along at height `at`, `y` down at `at` across; `value` rounded page pixels.
- Sides as in the Spacing box: `t`, `e`, `b`, `s`; `rtl` puts the start on the right.

## Which functions are there?
- `distances(a, b, zoom)`: apart along an axis, one gap line at the middle of where they face; off diagonally, drawn at a's middle with `reach` (b's near edge, for the dashed guide). Overlapping on both axes (nested, crossing): start-to-start and end-to-end lines with a length. Apart on one axis only: no line on the other.
- `inner(child, parent, zoom, rtl)`: `{ t, e, b, s }`, each `{ value, line }`; a side sticking out reads negative.
- `scale(root)`: Bootstrap 5.3's spacers 0, .25, .5, 1, 1.5, 3 rem in px (root 16). `nearestStep(px, root|scale)`: `{ step: '0'..'5', px }`, a tie takes the smaller. `dragStep(from, delta, root|scale)`: from a class value plus the pointer's page pixels; `auto` and none start at 0.
- `columns(row, { count, gutter, root, zoom, rtl })`: twelve equal boxes with `content` half a gutter (1.5rem) in. `columnAt(x, row, opts)`: 1..12, outside the row the nearest end. `spanAt(startCol, x, row, opts)`: `{ span, of }` ("6 of 12"), nearest column line, at least 1, at most what is left.
- `gaps(boxes, axis, zoom)`: neighbour gaps sorted by place with `between` (indexes); overlapping boxes give none. `equalGaps(boxes, value, axis, zoom, tol)`: gaps within 0.5 page px of `value`. `snap(value, targets, threshold=4)`: where a handle holds.

## Where is it tested?
- `tests/designer_canvas_guides_test.php` (#11517; Node, skipped without it): both box shapes, apart, touching, diagonal, nested and crossing boxes, zoom, inner distances LTR/RTL, every scale step and tie, drag steps, columns LTR/RTL and spans, gaps, equal gaps and snapping.
