---
name: office-pptx-design-reader
description: The design file reader pg_pptx_design_read() (PR #11840): a .pptx read as slides of placed elements for the Artifact Canvas; units, what each element gives, what is left out and counted, limits
module: office_pptx_design
---

## What is it?
- Since PR #11840 (step DF1 of `plans/artifact-canvas-design-file.md`): `pg_pptx_design_read($path, $limits)` in `includes/fn/office_pptx_design.php` reads a .pptx as a design, without a library, for the Artifact Canvas to open as layers. Pure: no database, nothing written, nothing taken out of the package. Its slides become canvas documents through `pg_pptx_canvas_slide()` (`office-pptx-canvas-mapper.md`); no screen or door uses them yet (DF3, DF4).
- It opens the package through the preview's gates and finds the slides with `pg_office_pptx_slides()` (`office-files.md`, `office-pptx-preview.md`).

## What does it give?
- `ok`, `error`, `width` and `height` (the slide size `p:sldSz` in EMU; none written: 9144000 by 6858000), `slides`, `left` (kind => count over the file), `simplified`, `truncated`.
- A slide: `n`, `hidden`, `background`, `elements` in paint order, `left`, `simplified`. The background is the slide's own `p:bg`, else its layout's, else its master's: `{type: color, color, opacity}`, `{type: picture, part, ext, crop, opacity}` or null.
- Units stay the file's: places in EMU, text sizes and spacing in hundredths of a point; the mapper turns them into pixels (`office-pptx-canvas-mapper.md`). Colours are `#rrggbb` and an opacity; theme colours resolve through the master's colour map and theme.
- Every element has `type`, `name`, `x`, `y`, `w`, `h`, `rot` (degrees 0-360), `flip_h`, `flip_v`, with its groups resolved: a group scales its children's frame (`chOff`/`chExt`) to its place, mirrors them across its middle, then turns them about it. A placeholder with no place takes its layout's or master's.

## Which element types?
- `text`, one per body with any text: `paragraphs` (`text`, a break a new line, empty ones kept; `align`, `level`, `line`/`before`/`after` as `{type: pts|pct, value}` or null), `style` of the first run with text (`font`, `size`, `bold`, `italic`, `underline`, `color`, `spacing`; theme fonts resolved), `simplified` when runs differ (counted), `inset`, `anchor`, `wrap`, `autofit` (shape, text, none).
- A filled shape with text gives the shape, then the text in its place.
- `picture`: a `p:pic` or a shape filled with a picture (Canva's form): `part` (resolved part name), `ext`, `crop` (shares cut on each side; negative is room), `opacity` (alphaModFix), `geometry` (its preset, `rect` otherwise). Source and fill rectangles become one crop; the caller reads the part (DF3).
- `line`: `line`, `straightConnector1` or any `p:cxnSp`: `x1..y2` from the box's corners, flipped and turned, and `stroke` (`color`, `opacity`, `width` EMU default 12700, `dash`, `head_start`, `head_end`).
- `shape`: presets the canvas draws (rect, roundRect, ellipse, triangles, diamond, polygons, stars, wedge callouts; a four-point custom path is `rect`): `geometry`, `adjust` (guide name => value), `fill`, `stroke`. Fills and outlines fall back to the shape's style references (fillRef, lnRef idx > 0).

## What is left out and counted?
- Kinds in `left`: `table`, `chart`, `diagram`, `object` (OLE), `other` (another graphic frame, groups deeper than 32), `ink`, `media` (video, audio), `shape` (a preset or path the canvas cannot draw), `picture` (linked or missing part), `mask` (a picture's custom path, kept as a rectangle), `effect` (shadow, 3D: the element kept), `gradient` (first colour kept), `pattern` (foreground kept).
- Limits: 500 slides, 2000 elements a slide (`PG_PPTX_DESIGN_MAX_ELEMENTS`, `$limits['elements']` in tests), the text of `PG_OFFICE_MAX_HTML`; past one the rest is cut and `truncated` is set. A part too large or damaged refuses the file with the preview's sentences.

## How is it tested?
- `tests/office_pptx_design_test.php`, on presentations the test writes (never the owner's Canva files).
