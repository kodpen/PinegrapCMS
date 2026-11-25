---
name: designer-canvas-guides-overview
description: Canvas guides on the designer canvas (CG1 #11517 to CG5 #11623): what is built, the rules every guide keeps, the two parts, which file holds what
---

## Why are there canvas guides?
- Owner (2026-11-24): one of seven designer additions before the launch (`workflow/decisions-made-product-106.md`): spacing and alignment set where the eye is, on the canvas, not read off a panel. Client only; no table or column.

## What is built, and where is each written up?
- Pure geometry `PGCanvasGuides` (#11517): `designer-canvas-guides-geometry.md`.
- Measure, Alt held with one element selected (#11570): `designer-canvas-guides-measure.md`.
- Spacing bands and their handles (#11568): `designer-canvas-guides-bands.md`.
- Column handles over the twelve-column grid, equal gaps (#11603): `designer-canvas-guides-columns.md`.
- Layout lines (Shift+G) and the "Canvas guides" switch in the View menu (#11623): `designer-canvas-guides-lines.md`.
- The drawing, boards G1 to G5: `designer-canvas-guides-drawing.md`.

## Which rules does every guide keep?
- A handle writes what the Spacing box and the Column options write: the same classes (`PGSpacingBox.write()`, `node.props[size]`), for the screen size the canvas width stands for, one undo step per drag or key change. It writes classes only, not a design variable; the variables are offered in the Spacing box itself (`design-variables-spacing.md`).
- No handle on a node the operator may not edit (`_sdPanelLockReason()`: a content editor's closed parts, a closed shared component).
- Nothing enters the page's tree or its saved markup: the layers sit in `.sd-canvas-wrapper`, never in the canvas document, and never show in the Preview. A drag's live value is an inline `!important` style taken off before the commit.
- Keyboard too: a handle takes focus, arrows step it, Enter or blur writes, Escape puts the value back.
- No listener or timer while nothing is selected (Measure, bands) or while the lines are off.
- "Canvas guides" off in the View menu turns all of it off, kept per browser (`pinegrap_designer_canvas_guides`).

## How is the code split?
- Two designer parts loaded on first use (`pg_designer_parts()`): `bands` (`style_designer_bands.min.js`: `pg_canvas_bands.js`, `pg_designer_tour.js`, the part) on the first selection, with the column handles and equal gaps; `guides` (`style_designer_guides.min.js`: `pg_canvas_guides.js`, the part) on the first Alt or Shift+G, with Measure and the layout lines. The core `style_designer.src.js` only calls them.
- They stay two parts so the first selection does not load Measure's code; a source sits in one bundle only, so `pg_canvas_bands.js` keeps its own scale and column maths (`designer-canvas-guides-columns.md`).
- Tests: `tests/designer_canvas_guides_test.php`, `tests/designer_canvas_bands_test.php`, `tests/designer_guides_part_test.php`, `tests/designer_canvas_bar_test.php`.
