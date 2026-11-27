---
name: designer-canvas-guides-zoom
description: How Measure, the layout lines and the spacing bands scale the canvas at a zoom (PR #12003): rectangles and CSS lengths take two scales, read from the canvas' <html> box
---

## Why two scales?
- Chromium 158 answers rectangles inside the zoomed canvas already zoomed (an element at 100 CSS px reads left 200 at zoom 2), while computed margins and paddings stay in CSS pixels (100px).
- `documentElement.clientWidth` is the viewport's width and a zoom does not change it, so it cannot tell the two kinds of browser apart. The `<html>` box can: `getBoundingClientRect().width` (1200) against `offsetWidth` (600 CSS px) at zoom 2.

## What does each part use?
- Measure and the layout lines: `PGCanvasGuides.zoomScales(cssZoom, fit, rectWidth, cssWidth)` gives `k` (rectangle to layer), `zoom` (CSS px to layer, used for the numbers) and `rect` (rectangle px per CSS px, the lines' page scale). The lines' page width is the `<html>` box's `offsetWidth` times `rect`.
- Bands: the core's `_sdBandsGeo()` gives `k` for rectangles; the part adds `g.z = PGCanvasBands.lengthScale(g.k, rectWidth, cssWidth)` and uses it for the bands' widths, a drag's travel, the hold, equal gaps and the columns' gutter.
- A browser that answers the page's own pixels gets the ratio 1 and the zoom through `k`, as before.

## What was wrong before?
- Up to PR #12003 Measure's rule (`clientWidth × zoom`) misread Chromium: everything doubled at 200 %. The bands used `k` for lengths: half wide, a drag twice as fast. Checked in Chromium 158 at zoom 1 and 2.

## Where is it tested?
- `tests/designer_canvas_zoom_test.php`.

Related: docs/memory/designer/designer-canvas-guides-overview.md, docs/memory/designer/designer-canvas-guides-measure.md
