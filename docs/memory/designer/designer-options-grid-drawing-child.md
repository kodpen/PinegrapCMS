---
name: designer-options-grid-drawing-child
description: A grid box's named areas as drawn for DO6, the rest: the row "Area" on a child of the grid, what the canvas shows, the keys and what is read out, the 240 px panel
---

## What does a child of the grid get?
- A row "Area", first of its own rows in Advanced › Layout, drawn only when the parent has areas at any size: a list of "None" and the parent's areas at the chosen size with their tints and how many elements each holds. Several elements may share an area.
- Choosing one writes `grid-area` and clears "Span columns", "Span rows" and "Start at" at that size; the three rows stay dimmed, showing where the area puts the element, with "Placed by its area. Choose “None” to place it by column and row."

## What does the canvas show?
- Only while "Areas" is open on the grid box or "Area" on a child: each area in its tint over the box at its real size with its name at its top right, an empty area as a dashed box, the one under the pointer in the panel stronger. Never saved, never on the site or in the Preview; nothing is dragged on the canvas.

## Which keys, and what is read out?
- Tab enters the grid as one stop, then the list, then Gap. Arrows move among cells, an area counting as one; Shift with arrows marks a rectangle from a free cell or resizes a chosen area; Enter makes the area and types its name, or edits a name; Delete removes; Escape gives up the mark or the name, then leaves.
- The grid is named "Areas, 3 columns, 3 rows"; a free cell "column 2, row 2, free"; an area "classes, columns 2 to 3, row 2, 1 element". A refusal and each line after a change are announced.
- At 240 px the cells share the width (24 px high from seven columns on); a name that does not fit is cut with "…" and stays whole in the list.

Related: docs/memory/designer/designer-options-grid-drawing.md, docs/memory/designer/designer-options-grid-drawing-rules.md, docs/memory/designer/designer-grid-child-place.md
