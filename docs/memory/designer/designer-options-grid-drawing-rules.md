---
name: designer-options-grid-drawing-rules
description: How a grid box's named areas behave as drawn for DO6: when "Areas" is offered, making, naming, refusals, what a change takes with it, screen sizes
---

## When is "Areas" offered?
- On an element whose Display is Grid, a row of Advanced › Layout after Rows and before Gap. It reads "None" or "4 areas" with the areas' tints; a press opens the small grid under it, a second folds it, and it opens by itself on a grid that has areas.
- Areas need a set number of columns and rows: "Equal" or "Fixed sizes" on both. Otherwise the row is off with the line "Areas need a set number of columns and rows. Choose “Equal” or “Fixed sizes” for both."
- The grid has one cell a column and row of the chosen screen size, numbered, all the same size, up to 12 by 12. It writes `grid-template-areas` on the element's own class through the per size writer of Columns and Rows; a cell in no area is a dot.

## How is an area made and changed?
- A drag across free cells marks a rectangle with its size ("2 × 1"); a press on one cell makes an area of it. On release the area gets the next tint and a list line whose name is ready to type over ("area2"). Enter keeps the name, Escape removes the area.
- A press on an area or its line chooses it: a ring, a handle on its lower right corner dragged to resize, the bin on its line (tip "Remove area"). A press on the name edits it; a drag of the area moves it to free cells.
- An area is always one rectangle. A rectangle that would cover another area's cell turns red, nothing is written, and the line reads "Areas cannot overlap. An area is one rectangle of free cells."
- A name is letters, digits, "-" and "_" and starts with a letter: "Use letters, digits, - and _, starting with a letter." A name another area has: "This name is taken."

## What does a change take with it?
- Each change is one undo step and leaves one line under the grid until the next change.
- Removed: the children placed in it lose their "Area" at this size. "“filters” is removed. 1 element is placed by the grid again."
- Renamed: the children's "Area" is renamed with it. "“filters” is now “sidebar”. 1 element follows it."
- A column or row added under Columns or Rows arrives as free cells; one taken away cuts the areas that reached into it, and an area left with no cell is removed as above. "Columns went from 3 to 2: “header”, “classes” and “footer” are narrower."

## How do screen sizes work?
- As Columns and Rows: "Screen size" is their shared row. A size with no areas of its own shows the nearest smaller size's in grey, "4 areas (from XS)", with "Taken from XS. A change here is kept for MD and up."; the first change there copies them to this size.
- "Reset this size" stands only on a size with its own areas above a smaller size that has some; it clears this size's areas and the children's "Area" set here.
- Areas taken from a size with another count are drawn where they fall with a warn line: "These areas were drawn for 1 column at XS. LG has 3: draw them here for LG and up."

Related: docs/memory/designer/designer-options-grid-drawing.md, docs/memory/designer/designer-options-grid-drawing-child.md, docs/memory/designer/designer-grid-columns-rows.md
