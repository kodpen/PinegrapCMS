---
name: office-pptx-canvas-mapper
description: The design file mapper pg_pptx_canvas_slide() (DF2): a .pptx slide as an Artifact Canvas document; units, font lookup, paragraph breaks, pictures for the caller, the report
module: office_pptx_canvas
---

## What is it?
- Step DF2 of `plans/artifact-canvas-design-file.md`: `pg_pptx_canvas_slide($design, $slide, $options)` in `includes/fn/office_pptx_canvas.php` turns one slide of `pg_pptx_design_read()` (`office-pptx-design-reader.md`) into a canvas document (`ui/image-editor-overview.md`). Pure. Loaded where used, not by `functions.php`.
- It answers `doc` (an artboard, layers `l0`, `l1`… in paint order), `pictures` (layer id => part, ext, crop, flips) and `report`.

## Which sums turn the file's units into pixels?
- 9525 EMU a pixel (`PG_PPTX_CANVAS_EMU`): Canva's 18288000 EMU slide is 1920 px; the board's sides are kept to 16-8000.
- Text size px = `sz` / 100 × 4 / 3; line height = `spcPts` / `sz`, a percent spacing × 1.2, none 1.2; letter spacing em = `spc` / `sz` (`plans/artifact-canvas-canva-census.md`).

## How is a text box mapped?
- One text layer: the first run's style, the first paragraph's alignment (centre, right, else left); a paragraph is a new line, and a paragraph wider than its box's inner width is broken between words (the canvas's text has no wrap). A box with `wrap="none"` is not broken.
- Widths come from `$options['measure']` (text, layer) => px; the default `pg_pptx_canvas_measure()` estimates by letter classes, a little short so a line that fits in the file is not broken.
- Placed inside the insets and by the anchor, the offset turned with the box. Upside down adds 180°; a sideways flip is ignored (letters are not mirrored). The colour's alpha is the layer's opacity.
- Simplified (once a box): differing runs or alignments, an underline.

## How is a font looked up?
- `pg_pptx_canvas_font_name()` cuts style words from the end of the family ("Aileron Bold", "Cormorant Garamond Italics", "Extra Bold" as one word) into weight and italic; a run's bold raises the weight to 700; weights stay 300-900.
- The family is found case-blind in `$options['fonts']`, by default the Google Fonts catalogue read from `assets/js/pg_google_fonts.js` (`pg_pptx_canvas_catalogue()`, `ui/image-editor-fonts.md`), and set as `g:<family>`.
- A missing family is replaced (`pg_pptx_canvas_font_stand_in()`): Roboto Mono, Caveat (script), Noto Serif (serif-like names), else Roboto. The report names it (`fonts`: family => stand-in). No font is taken from the package.

## How are pictures, lines and shapes mapped?
- A picture is an `image` layer with `src` `pptx:<part>` and `fileName`; the caller turns the part into a file and points the layer at it (DF3). A positive crop is kept in `pictures` for the caller to cut from the pixels; the room of a negative crop is taken off the layer's box. Flips go to the caller too: a picture layer has none. A picture with a non-rectangle mask is counted as `mask`.
- A picture background is a locked bottom `image` layer over the board; a colour one is the board's colour.
- A line is a `line`, or an `arrow` with its head at the second end (ends swapped when only the first has one); an oval head is `dot`, arrow/stealth `open`, else `fill`; dot dashes `dotted`, others `dashed`.
- A shape is a drawn layer: roundRect a `rect` with radius, diamond and polygons a `polygon` of their sides (a hexagon turned a quarter round), stars a `star` with the file's inner radius, callouts a `bubble`, a right triangle an even `triangle` (simplified). One layer filled in the fill's colour; an outline of another colour is a second, unfilled layer. A flip mirrors (`mirror`); upside down is mirrored and turned 180°.

## What does the report hold?
- `texts`, `pictures`, `shapes` (elements, not layers), `fonts`, `left` (the reader's kinds plus `mask`), `simplified`. `pg_pptx_canvas_report()` adds up several pages' reports.

## How is it tested?
- `tests/office_pptx_canvas_test.php`: the parts on arrays, and a presentation the test writes read and mapped end to end.
