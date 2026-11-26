---
name: designer-options-grid-drawing
description: The drawing of a grid box's named areas (plan designer-options-census, DO6): the sheet, its seven boards, the rows "Areas" and "Area", what is new and where it differs from the plan's words
---

## Where is the drawing?
- `designer/designer-options-grid-drawing/`: `options-grid.html` with a `-dark.jpg` and a `-light.jpg` picture; `drawing.css` sits on top of `ui/look-sheet/sheet.css`. The designer's own parts are dark in both pictures.
- The sample is the box that lays out the page "Timetable", design "Still Water Yoga": three columns and three rows at LG with the areas header, filters, classes and footer, one column and four rows at XS. Names and counts are examples; the labels are the step's.
- How the areas behave: `designer-options-grid-drawing-rules.md` (making, names, refusals, screen sizes) and `-child.md` (the child, the canvas, the keys).

## Which board shows what?
- All boards belong to step DO6. Columns, Rows and Gap (DO4) and "Span columns", "Span rows", "Start at" (DO5) are drawn as they are built, around the new rows.
- A1 the row "Areas" in Advanced › Layout: none yet, opened and empty, four areas with the list, folded again, and a grid whose columns or rows have no count.
- A2 making and changing: a drag across free cells, the name typed in the list, an area chosen with its corner handle and bin, a drag over a taken cell, a name that cannot be kept.
- A3 the one line after a change: an area removed while an element stands in it, an area renamed, a column taken away.
- A4 each screen size: XS with its own, MD showing XS's in grey, LG with its own and "Reset this size", a size whose count differs from the areas it takes.
- A5 the row "Area" on a child: "None", the list, an area chosen with the three place rows dimmed, a parent with no areas, a value taken from a smaller size.
- A6 the canvas: the grid box with its areas tinted and named, a child with its area shown, XS.
- Z1 a cell, an area and its handle enlarged, the keys, six columns in a 240 px panel.

## What does the drawing add to the designer's bars?
- Nothing: no button in the Options panel's head, the page bar or the canvas bar (`designer-light-and-simple.md`). One row "Areas" on a grid box between Rows and Gap, which opens in place, and one row "Area" first among a child's own rows.

## Which parts are drawn from what exists, and which are new?
- As today: the identity row, the tabs, the search, the section card with its count, a row with its changed dot, a list, a field, a value in grey with "(from XS)", the info, warn and error lines, the quiet small button, the bin, the selection's outline and tag.
- New: the fold field of "Areas" with the areas' tint squares and count; the small grid with its numbered cells; an area as a tinted block with its name, its ring and corner handle when chosen, dashed blue while dragged and dashed red when refused; the list line of an area (square, name, place, elements); the foot line with "Reset this size"; a tint square in a list and a field; the tinted overlay and name tag on the canvas.
- The six tints are the look's in turn: blue, teal, amber, violet, rose, grey; a seventh area starts again at blue.

## Where does the drawing differ from the plan's words?
- The plan says "a child given its area from a list": the drawing adds that the three place rows stay, dimmed, with a line saying the element is placed by its area.
- The plan does not say where areas cannot be offered: the drawing turns the row off, with a line, when Columns or Rows has no set count ("Equal" or "Fixed sizes" on both).
- The plan names no canvas part: the drawing shows the areas there only while one of the two rows is open; nothing is dragged there.
- The drawing wins where the two differ (`workflow/architect-draws-new-part.md`); what neither says the worker asks of the architect.

Related: docs/memory/designer/options-panel-census.md, docs/memory/designer/designer-grid-columns-rows.md, docs/memory/designer/designer-grid-child-place.md
