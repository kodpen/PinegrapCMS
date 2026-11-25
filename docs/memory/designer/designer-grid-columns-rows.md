---
name: designer-grid-columns-rows
description: Advanced › Layout's Columns and Rows on a grid box (DO4): modes (Default, Equal, Fixed sizes, As many as fit, Custom), the CSS each writes, per screen size, the part 'grid', Gap kept
---

## Where do the rows sit?
- Style tab › Advanced › Layout, on an element whose Display is Grid or Inline Grid at any size (`_sdGridBox()`, context key `gridBox` of `_sdOptCtx()`; several elements: all of them). Plan `designer-options-census-steps.md` DO4.
- They stand before Gap: "Screen size", "Columns" with its field, "Rows" with its field. Gap is the same Gap row (gap-* classes); on a grid box it shows without being held (`also: 'gridBox'` in `_SD_LAY_ROWS`), so the note no longer names gap as hidden there.
- No button in a bar or a head. A flex box gets no grid rows.

## What does each mode write?
- Default writes nothing at that size (clears the size's value, so a larger size takes the smaller one's).
- Equal: a Count, 1 to 12: `repeat(n, minmax(0, 1fr))` (minmax keeps a long word from widening one track). Picked with no value: 3 columns, 2 rows.
- Fixed sizes: sizes typed with spaces (commas read as spaces), each px, %, rem, em, vw, vh, ch, fr, auto, min-content, max-content, minmax(), fit-content(); a bare number is px. Picked from Equal n: n times 1fr (rows: auto).
- As many as fit (columns only): a Smallest width: `repeat(auto-fit, minmax(min(<w>, 100%), 1fr))`, 200px when picked. `auto-fill` reads back as this mode.
- Any other value (named lines, repeat with several tracks, more than 12) reads as Custom and stays as typed; its field edits it as text.
- Pure code in `assets/js/pg_grid_tracks.js` (window.PGGridTracks: parse, build, pick, edit); refused input marks the field red and writes nothing.

## How is it per screen size?
- `grid-template-columns` / `-rows` go through DO2's writer (`designer-size-per-screen.md`): XS is the base rule of the element's `pgs-` class (the same rule "This element" types into), a larger size an `@media (min-width: …)` rule. An element without a class gets one on its first value.
- The "Screen size" row is the chosen size shared with the Spacing box (`_sdSpSize`); choosing one moves the canvas to it. A size holding a value is marked with a dot in the list. A value taken from a smaller size shows as the Default option's text ("Equal: 3 (from XS)").
- Where a custom style is off (system widget, shared component, component placement) the rows are not drawn: there is no class to write to.

## How is it loaded?
- Part `grid` (`style_designer_grid.min.js`: pg_grid_tracks.js, style_designer_grid.js), handed to every editor. A source sits in one bundle only, so PGSizeStyle comes from the part `spacing`: `_sdSpBoxBind()` loads `spacing`, then runs `_sdPartCall('grid', 'draw', [_sdSzHost(…)])` into Layout's `<div data-sd-gridbox hidden>` once the section is shown. Core grew about 0.4 KB (#PR).

## Where is it tested?
- `tests/designer_grid_tracks_test.php`: parse, build, pick, edit, the per size rule, Layout's plan, the wiring. Not unit-tested: the fields in a browser.
