---
name: designer-grid-child-place
description: Advanced › Layout's Span columns, Span rows and Start at on a grid box's child (DO5): the CSS each writes, per screen size, typed values kept, the part 'grid'
---

## Where do the rows sit?
- Style tab › Advanced › Layout, on an element whose parent's Display is Grid or Inline Grid at any size (context key `gridItem` of `_sdOptCtx()`; several elements: all of them). Row `gridItem` of `_SD_LAY_ROWS`, the first of the element's own rows. Plan `designer-options-census-steps.md` DO5 (#11654).
- Rows: "Screen size" (left out when the element is a grid box too: its Columns and Rows already show it), "Span columns", "Span rows", "Start at" with two number fields, Column and Row. No button in a bar or a head. Named areas and the row "Area" above these: `designer-grid-areas.md`.

## What does each field write?
- `grid-column` and `grid-row`: a start alone `3`, a span alone `span 2`, both `3 / span 2`. Each field 1 to 12; an empty field is the browser's own placement (placeholder "Auto"); both empty writes nothing at that size.
- Read back too: `auto`, `<start> / <end>` (end after start, as a span), `span n / auto`, `auto / span n`. Anything else (names, negative lines, an end before the start) shows as one text field "Column (Custom)" / "Row (Custom)", kept as typed.
- Pure code in `assets/js/pg_grid_tracks.js`: `PLACE`, `parsePlace`, `buildPlace`, `editPlace`; refused input marks the field red and writes nothing.

## How is it per screen size?
- Like Columns and Rows (`designer-grid-columns-rows.md`): through PGSizeStyle on the element's `pgs-` class, XS the base rule, a larger size an `@media` rule; a size with a value is marked with a dot.
- A field changed at a size starts from the value in force there, so a span set at XS is kept when MD sets a start. A value from a smaller size shows as the field's placeholder ("2 (from XS)").
- Emptying every field at a size clears that size, so the smaller size's value applies again; there is no "auto over a smaller size's value" without typing it.

## How is it loaded and tested?
- Same part `grid` (`drawItem`), drawn into `<div data-sd-griditem hidden>` once Layout is shown; core grew 362 bytes minified, the grid part 4.2 KB (1.1 KB gzipped) (#11654).
- `tests/designer_grid_tracks_test.php`: parsePlace, buildPlace / editPlace, Layout's plan, the wiring. Not unit-tested: the fields in a browser.
