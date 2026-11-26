---
name: designer-grid-areas
description: Advanced › Layout's "Areas" on a grid box and "Area" on its child (DO6): the small grid, what each change writes, the lines, per screen size, the canvas tint, the part 'grid'
---

## Where do the rows sit?
- "Areas": on a grid box (`gridBox`), drawn by the part `grid` after Columns and Rows and before Gap, one element only. "Area": on a grid's child, after "Screen size" and before "Span columns", when the parent has areas at any size. Options census DO6 (#11734) (`options-panel-census.md`), drawing `designer-options-grid-drawing.md`.
- No bar or head button. The row folds: open by itself when the box has areas, a press folds it; the choice is kept per element for the session.
- Off with the line "Areas need a set number of columns and rows…" unless Columns and Rows are both Equal or Fixed sizes at the size (`PGGridAreas.count()`).

## What does a change write?
- `grid-template-areas` on the box's `pgs-` class at the chosen size through PGSizeStyle, a cell in no area a dot. Removing the last area over a smaller size's areas writes `none`; "Reset this size" clears the size (and the children's `grid-area` set there).
- A child's "Area" writes `grid-area: <name>` and clears its `grid-column` / `grid-row` at that size in the same step; its place rows stay, dimmed and disabled, with "Placed by its area…". "None" over an area from a smaller size writes `grid-area: auto`.
- Remove and rename walk the children from the size up, until a larger size has its own areas: a removed name is cleared (or set to `auto` when it came from a smaller size), a renamed one follows. A changed Columns or Rows count cuts the areas with `PGGridAreas.fit()` in the same step.
- Each change is one undo step and leaves one line under the grid ("“filters” is removed. 1 element is placed by the grid again.") until the next change.

## How is it drawn and used?
- Pointer: drag across free cells makes an area (name `areaN` typed over at once: Enter keeps, Escape removes it); a press chooses an area (ring, corner handle to resize, bin on its list line); a drag moves it. A rectangle over another area is refused with the overlap line; nothing is written.
- Keys on the grid (one Tab stop): arrows (an area counts as one), Shift+arrows mark or resize, Enter makes or names, Delete removes, Escape drops the mark then leaves. The grid stops these keys from reaching the editor's shortcuts. A live region reads each cell and line.
- Areas from a smaller size show grey with "Taken from XS…"; drawn for another count, a warn line names both counts.
- Canvas: while the row is open (or a child's "Area" is drawn) the areas are tinted over the box from its computed tracks (`#sd-ga-overlay` in the canvas document, pointer-events none, never saved); a 500 ms check takes it away once the row is gone, so nothing runs while it is closed.

## How is it loaded and tested?
- `assets/js/pg_grid_areas.js` (window.PGGridAreas: parse, build, count, nameError, nextName, at, free, rect, fit, item) joins the part `grid`; the size host gives `parentOf(n)`. Core grew 64 bytes minified; the grid part 14.0 to 35.9 KB minified (5.1 to 12.7 KB gzipped) (#11734).
- `tests/designer_grid_areas_test.php`: parse, build, names, overlaps, fit, the wiring. The panel was run in Chromium on a harness page of the part (make, name, refuse, rename, remove, child Area, a column taken away, the canvas tint); not run in a full editor.
