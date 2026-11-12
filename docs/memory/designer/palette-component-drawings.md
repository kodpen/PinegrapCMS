---
name: palette-component-drawings
description: The Components tab's hover card drawings (_sdPalDrawing): every row has one, the drawing key v in _sdPalInfo(), the l: key of the Lists in a record rows, the drawing style, the test
---

## Which Components rows have a drawing?
- Every one: Grid, Bootstrap, HTML elements, Content, Form, Lists in a record and Regions. Each row's entry in `_sdPalInfo()` names a drawing key `v`, drawn by `_sdPalDrawing(v)` above the name, the markup chip and the description `d` in the tip card (`_sdPalTipHTML()`).
- Rows may share a drawing: Content's Carousel, Divider and Breadcrumb use the component drawings; the Menu region uses the `nav` element's.
- Ready blocks are drawn from their tree instead ([[palette-block-sketch]]); a row with its own `v` keeps it.

## How is a Lists in a record row keyed?
- All 27 rows are `type: 'loop_area'`, told apart by `extra.listSource`; `_sdPalKey()` gives them `l:<listSource>` (`l:cart.items`), so each has its own card and search words.
- A new list source in the palette's Lists in a record group needs its `l:` entry with `v` and `d` (Turkish through `tr_add.php`), or the test fails.

## How is a drawing made?
- One SVG, viewBox 200×110, in Bootstrap's own colours on a light page; text is grey bars, so nothing is translated (only symbols: `H1`, `</>`, `<?php`, `/`, `16:9`, `09:30`, `PDF`, language codes).
- An HTML element or a region is the dashed blue frame, the page around it grey. A form field is a label bar over its control (`Fld()`). A list draws its row three times, the first framed dashed blue as the repeated template, the copies faded (`Rows()`, `Cols()` side by side).
- Other helpers inside `_sdPalDrawing()`: `R` box, `L` text line, `Hd` heading bar, `C` circle, `Tx` symbol, `Pa` path, `Btn` button, `Chev` caret, `Pic` picture with a mountain, `Person`, `Tag`. A symbol with `<` or `&` is written escaped.

## How is it tested?
- `tests/designer_pal_drawing_test.php` cuts `_sdPalInfo()`, `_sdPalDrawing()` and `_sdPalKey()` out of the core and runs them under Node: every entry has a `v` and a `d`, every `v` draws a whole SVG with at least three marks, every list source of the palette's Lists in a record group has its `l:` entry; an unknown key draws nothing.
- HTML elements and Content added in #8833, Form fields, Regions and the lists in #8857.
