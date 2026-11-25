---
name: designer-canvas-guides-bands
description: The spacing bands and handles over the designer canvas (CG3 #11568, part bands): when they show, what a drag or arrow writes, the core's stub and host, the first-use line, the numbers measured
---

## What does the operator see?
- One selected element: its margin as a hatched amber band outside the frame, its padding as a plain teal band inside; each band's edge is a white pill handle (22 by 6 px, the same at every zoom). A side with no spacing has a faint handle on the frame's edge (inner ones one handle inside), so spacing starts from nothing.
- Dragging a handle steps that side through the spacing scale (0 to 5), the element following on the canvas, the value beside the pointer ("5 48 px", "4 24 px · top and bottom"). Shift adds the opposite side, Alt all four, as the Spacing box. Escape during a drag puts it back.
- Tab reaches a handle; the arrows along its axis step it (an outer handle grows outwards, an inner one inwards); Enter or leaving the handle writes, Escape puts the value back.
- First use: one line under the canvas bar, "Drag the edge of a tinted band…", with "Got it"; kept as the tour tip `canvas-guides` (`PGDesignerTour.tipSeen()`, `pg.designer.<user id>.tour`), so CG2's Measure shares it.

## When are there no bands?
- Nothing or several selected, the root, Prototype open, an element being dragged (the part hides on the canvas's `dragstart`), a design without Bootstrap, view mode, or a node `_sdPanelLockReason()` refuses (a content editor's closed parts, a closed shared component): the frame only.
- No bar button and no menu row yet: CG5's "Canvas guides" switch turns them off.

## What does a change write?
- What the Spacing box writes: `PGSpacingBox.write()` with `B.sides(side, shift, alt)`, through `_sdSpBoxHost()`'s commit (one `saveState()`, `_sdRenderNode()`), one undo step per drag or key change.
- For the screen size the canvas shows: `PGCanvasBands.sizeFor(canvas width)` (xs below 576 … xxl from 1400), not the Spacing box's own chooser.
- The start step is the nearest scale step of the computed value. While dragging, the canvas element carries the value inline with `!important` (utilities are `!important`), taken off before the commit; nothing enters the tree.

## How is it built?
- Pure: `assets/js/pg_canvas_bands.js` (`window.PGCanvasBands`): `layout(box, margin, padding, k, rtl)` bands and handles, `growth()`, `keyStep()`, `reach()`, `labelAt()` (flips at the layer's edge), `sizeFor()`; tests `tests/designer_canvas_bands_test.php`.
- Part `bands`: `assets/js/style_designer_bands.js`, bundle `style_designer_bands.min.js` = `pg_canvas_bands.js`, the part. Its first-use line's state comes with the part `tour` (#11646).
- A source sits in one bundle only (`tests/js_bundles_test.php`): `PGSpacingBox` comes with the part `spacing`, loaded first (`_sdPartLoad('spacing').then(…)`); `pg_canvas_guides.js` is Measure's part `guides` (CG2 #11570), so the bands keep their own `scale()` and `nearestStep()`. CG4 kept the two parts apart; its column handles and equal gaps live in this part (`designer-canvas-guides-columns.md`). Handed to every editor (`designer_screen.php`).
- Core (`// ── Spacing bands` in `style_designer.src.js`): `_positionSizeBadge()` (selection, render, canvas scroll) calls `_sdBandsSoon()`, one rAF, then `_sdBandsDraw()`: the `spacing` part, then `_sdPartCall('bands', 'draw', [_sdBandsHost(node)])` or, once loaded, `_sdPartApis.bands.hide()`. `_sdBandsGeo()` gives the layer's place as the link marks read it.
- The layer `.sd-cgb-layer` sits in `.sd-canvas-wrapper` (z-index 21, clicks pass through but on handles). While hidden the part holds no listener; shown, only the canvas area's scroll and the canvas's `dragstart`.

## What was measured?
- Chromium (sandbox), 1,500 cards in the frame, 1200 px: a draw 0.08 ms; dragging a handle 120 pointer moves, frames median 16.7 ms, worst 16.8 ms (60 fps). Core bundle +1.1 KB minified; the part 26.6 KB, loaded on the first selection.
