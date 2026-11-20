---
name: canvas-data-views-screens
description: Plan canvas-data-views, how the Data menu and the data layer's panel are put together in the Design Canvas (DV2) and the Data screen's "Show on a canvas" (DV3)
---

## Which screens, and by which rules?
- DV2: the canvas's "Data" menu, its picker and the data layer's panel. DV3: "Show on a canvas" on a table's head in the Data screen.
- The canvas's own menus, dialogs and panels are the model: the Data menu and panel use the classes the other layer menus and panels use, nothing of their own. `ui/screen-composition-rules.md` holds for the picker and the panel.

## How is the Data menu laid out?
- One entry "Data" in the canvas's add menu, beside the other layer kinds. It opens a picker in two steps, as the starting-point sheet:
  1. "Pick a table": the tables the person may read as rows with name, channel and row count; a search field above. A table the person may not read is not listed.
  2. "Pick a view": cards, each a small sample drawn from the table's first rows with its name under it: Table, Bar, Stacked bar, Line, Area, Donut. A kind the columns do not fit is not shown; when none fits but Table, one line says why.
- A click on a card puts the layer on the stage and closes the picker; no further "Add" button.

## How is the layer's panel laid out?
- The layer panel of the canvas, with the groups folded as its other layers' groups are, in this order:
  1. "Data": the table (with "Change"), label column, value columns, grouping (Sum, Count, Average as segments), sort, row limit.
  2. "Look": the kind as small sample cards, colours (the guide's palette, the channel's tone, chosen ones), legend on or off and its side.
  3. "Text": title, number format, type size.
- A group shows only what the kind uses (a donut has no sort axis).
- A layer whose rows the reader may not read draws an empty frame with one line saying so; the panel shows the same line in "Data".

## How is "Show on a canvas" laid out? (DV3)
- A menu on the table's head with two entries, "As a chart" and "As a table"; each opens step 2 of the picker with the table already chosen.

## What is asked of the architect first?
- A part the canvas has no model for (the sample card, the segments inside a panel group): a design example, before it is built (`workflow/screen-described-in-plan.md`).
