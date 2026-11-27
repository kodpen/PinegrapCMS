---
name: bug-hunt-areas-canvas-guides
description: Bug hunt of the designer's spacing handles, bands and canvas guides (2026-11-26, PR #12003): six faults fixed, one bug issue, what was read clean
---

## What was the area?
- `assets/js/style_designer_spacing.js` (Style › Spacing box), `style_designer_bands.js` (spacing bands and column handles), `style_designer_guides.js` (Measure, layout lines) with `pg_canvas_bands.js` and `pg_canvas_guides.js`. The architect named it after the owner met dead handles and bands left after a page switch (2026-11-26).
- Those two had landed already that day: #11921 (late `PGSpacingBox`, hit areas) and #11942 (hide on an empty selection), `designer/designer-canvas-bands-drag-start.md`. Re-read, no fault left in them.

## What did it fix? (PR #12003)
- Zoom other than 100 %: Measure and the layout lines drew frames, lines and numbers doubled at 200 % (60 for a 30 px gap); the bands drew margins and paddings half wide and stepped a drag twice as fast. Scales: `designer/designer-canvas-guides-zoom.md`.
- A drag whose release the handle did not hear (let go outside the window, lost capture) kept following the pointer and every handle refused the next drag. A move without the main button ends it as a release; `lostpointercapture` puts the value back. Same in the Spacing box, where it also kept changing the number on hover.
- Spacing box: a `pointercancel` after a move swallowed the next click (the side's field did not open).
- Escape on a stepped handle while the layout lines show turned the lines off and left the value; Alt during a handle drag drew Measure over the bands. The guides now stand back while `.sd-cgb-layer.is-live`.
- A docked panel or resized column moved a fixed-width canvas without a canvas draw: bands and lines stayed in place. Both observe the canvas area's size while shown.

## What became an issue?
- #12004: on the first load of the parts, bands drawn for an element deselected meanwhile (`_sdBandsDraw()` in the core).

## How was it checked?
- Unit tests: `tests/designer_canvas_zoom_test.php`, new cases in `designer_canvas_bands_test.php`, `designer_guides_part_test.php`, `designer_spacing_box_test.php`, each red before its fix.
- Chromium 158 with Playwright, loading the real parts beside a canvas frame (no designer, no database): zoom 1 and 2, missed release, Escape and Alt, a folded side column. The full designer was not run.

## What was read clean?
- Hide on an empty selection, page switch, Prototype, the "Canvas guides" switch; Measure stops when the selection goes (`_sdGuidesSync()`); a re-render during a drag ends it; the breakpoint buttons resize the frame, whose resize redraws through `_sdFxSoon()`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/designer-canvas-guides-overview.md
