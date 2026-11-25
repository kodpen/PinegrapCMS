---
name: designer-canvas-guides-columns
description: Column handles and equal gaps on the designer canvas (CG4 #11603, part bands): the end-edge handle and twelve-column grid, what it writes, equal gaps, why two parts, the numbers
---

## What does the operator see on a column?
- A selected column of a row (a `col` node whose parent is a `row`) has a pill handle on its end edge (right, left in RTL), in place of the outer start and end spacing handles. Its other bands stay.
- Dragging it steps the width from column line to column line, at least 1, at most 12; while it is held the row's twelve columns are drawn over the row (accent blue) and "6 of 12" with the screen size's name ("XL") sits beside the pointer. A width that pushes the column to the next line is allowed and drawn as it falls.
- Tab reaches it; Left and Right step one column (mirrored in RTL), Enter or leaving writes, Escape puts it back.

## What does it write?
- The Column options' width (`propsCol()`): `node.props[size] = '<n>'` for the screen size the canvas width stands for (`PGCanvasBands.sizeFor()`), `xs` included; one `saveState()`, then `_sdRenderNode(node, render)`. Nothing when the span did not change. Core: `_sdBandsCol(node)` in `style_designer.src.js`, handed as `host.col`.
- While dragging, the column's wrapper carries `flex: 0 0 auto; width: n/12 %` inline with `!important`, taken off before the commit.

## What are equal gaps?
- While a margin handle moves (drag or arrows), the gap it changes between the selection and its neighbour is compared with the other gaps among the selection's siblings on the dragged axis (boxes that share its span across that axis). One within 0.5 page px gets the same rose line and number as the dragged gap, and the label adds "= the gap above" (below, on the left, on the right).
- The step with a match holds: the drag leaves it only once the next step is nearer by 8 screen px more (`PGCanvasBands.hold()`).
- The siblings come from `host.siblings()` (core, `_sdAnyParent(node)`), asked for once per change, never while nothing moves.

## How is it built?
- Pure, in `pg_canvas_bands.js`: `colGrid(row, count, gutter)`, `colSpan(col, x, row, count, gutter, rtl)`, `colSpanOf(width, rowWidth, count)`, `equalGap(sel, others, edge, k, tol)`, `hold(raw, held, nearest, extra)`; tests in `tests/designer_canvas_bands_test.php`.
- Drawn by the part `bands` (`style_designer_bands.js`): `.sd-cgb-col`, `.sd-cgb-grid` / `.sd-cgb-gc`, `.sd-cgb-gl` / `.sd-cgb-gn` (`style_designer.css`). No listener while nothing moves.

## Why are bands and guides still two parts?
- CG4 kept them apart: `bands` loads on the first selection, `guides` (Measure) on the first Alt. Folding would add Measure's 11.8 KB to the first selection. The column and gap maths sit in `pg_canvas_bands.js` because a source lives in one bundle only and `pg_canvas_guides.js` is Measure's.

## What was measured?
- Bundles built: core `style_designer.min.js` 3,032,897 → 3,033,412 bytes (+0.5 KB); part `bands` 16,972 → 23,989 bytes (gzip 6,570 → 8,736); `guides` unchanged. Nothing selected: no new work (`_sdBandsDraw()` unchanged; host built only with a selection).
- Chromium (sandbox) harness, 1,500 cards on the canvas, 1200 px: a draw 0.1 ms median, 0.8 ms worst; dragging the column handle 120 pointer moves, frames median 16.7 ms, worst 16.8 ms (60 fps). `node tests/js/designer_render_timing.js` the same before and after (within noise).
