---
name: designer-canvas-guides-measure
description: Measure on the designer canvas (CG2 #11570): Alt held with one element selected draws the distances to the element pointed at, in the part guides, outside the page
---

## What does Measure do?
- One element selected, Alt held, pointer on another element: rose lines with caps and the distance in whole page pixels between the facing edges, plus how far the starts are apart (`PGCanvasGuides.offset()`, the tops side by side), as board G1 of `designer-canvas-guides-drawing.md`. Off diagonally: the gap line at the selection's middle and a dashed guide to the other's edge.
- Pointing at an element that holds the selection (its parent): the four inner distances; pointing inside the selection: the child's four. A side of 0 draws the shared edge, no number; a number that does not fit its line sits beside it.
- The element pointed at gets a dashed frame and its name on white. Measure changes no value.
- Nothing selected, several selected, or the Prototype view open: Alt does nothing. Letting Alt go, a press (Alt and a drag copies), a window blur or the pointer leaving the canvas ends it.

## How is it built?
- Core (`style_designer.src.js`, section `// ── Canvas guides: Measure`): `_sdGuidesSync()` at the end of `render()` and `renderSelectionOnly()` binds a capture `keydown` on the editor window and the canvas window only while one element is selected; the first Alt runs the part with `_sdPartCall('guides', 'measure', [host])`, host = `{ doc, selected(), rectOf: _sdBoxRect, nameOf }`.
- Part `guides` (`assets/js/style_designer_guides.js`, after `pg_canvas_guides.js` in `style_designer_guides.min.js`): listens to the pointer, `keyup`, `blur` and scroll only while measuring; one draw per animation frame.
- The layer `.sd-gm-layer` goes into `.sd-canvas-wrapper` over the part of the frame in view (as the link marks), never into the canvas document: nothing reaches the tree, the saved markup or the Preview. Styles in `assets/css/style_designer.css` (`.sd-gm-*`).
- Zoom: modern Chromium answers rectangles inside the zoomed canvas already zoomed; the part tells it from the `<html>` box (`getBoundingClientRect().width` against `clientWidth × zoom`), so the numbers stay page pixels at every zoom.

## What was measured?
- Chromium 158, a canvas of 1,500 elements, 400 draws: one draw with its layout 0.10 ms median, 0.5 ms p95, 3.7 ms worst; frames stayed at 60 per second. Numbers right at zoom 1 and 1.5.
- The core bundle grew by about 0.7 KB (built `style_designer.min.js`); the part, 11.8 KB built, loads on the first Alt. `node tests/js/designer_render_timing.js` the same before and after (within noise).

## Where is it tested?
- `tests/designer_canvas_guides_test.php` (`offset()`), `tests/designer_guides_part_test.php` (bundle, hand-over, listener bound with one selection, layer outside the page).
