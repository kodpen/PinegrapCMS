---
name: palette-component-drawings
description: The Components tab's hover card drawings (_sdPalDrawing): which rows have one, the drawing key v in _sdPalInfo(), the drawing style, the test that fails for a row without one
---

## Which Components rows have a drawing?
- Grid, Bootstrap components, HTML elements (section to select, the lone inputs, video) and Content (heading to icon): each row's entry in `_sdPalInfo()` names a drawing key `v`, drawn by `_sdPalDrawing(v)` above the name, the markup chip and the description in the tip card (`_sdPalTipHTML()`).
- Two rows may share a drawing: Content's Carousel, Divider and Breadcrumb use the component drawings `carousel`, `divider`, `breadcrumb`.
- Form fields, Regions and the Lists in a record rows have none yet: P8 of [[designer-palette-drawings]] adds them.
- Ready blocks are drawn from their tree instead ([[palette-block-sketch]]); a row with its own `v` keeps it.

## How is a drawing made?
- One SVG, viewBox 200×110, in Bootstrap's own colours on a light page; text is grey bars, so nothing is translated (only symbols: `H1`, `</>`, `<?php`, `/`, `16:9`, language codes).
- An HTML element is the dashed blue frame, the page around it grey: `header` is the top band, `aside` the right column, `nav` the row of blue links.
- Helpers inside `_sdPalDrawing()`: `R` box, `L` text line, `Hd` heading bar, `C` circle, `Tx` symbol, `Pa` path, `Btn` button, `Chev` caret, `Pic` picture with a mountain. A symbol with `<` or `&` is written escaped.

## How is it tested?
- `tests/designer_pal_drawing_test.php` cuts `_sdPalInfo()` and `_sdPalDrawing()` out of the core and runs them under Node: every row but `f:` and `r:` has a `v`, and every `v` draws a whole SVG with at least three marks; an unknown key draws nothing. P8 drops the `f:`/`r:` exception.
- Added in P7 of [[designer-palette-drawings]] (#8833).
