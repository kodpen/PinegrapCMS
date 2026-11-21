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

## What did the owner not find, and what does DV2b change? (2026-11-21)
- Owner (agent channel message 11699), with development on dev: "tablo eklerken tuvalde chart dondurme ve tuvalde veri sekmesi şu anda ... yok". Both were built, in places he did not look: "Data" as an entry inside the canvas's Import menu, "Show on a canvas" as a quiet small button on an opened table, drawn only for a table of a channel. The architect's line above ("one entry in the canvas's add menu") misread his "tuvale bir de veri adında bir menü" and "en tepesinde seçenek olarak".
- DV2b (S), one PR, before DV4:
  - The canvas's top bar gets its own "Data" button beside Import (icon and word; the icon alone under 576 px), opening the picker; the Import menu's entry goes. Shown wherever the editor has its API.
  - An opened table's head: "Show on a canvas" is a button with its word in the head's first row, not a quiet small one at the bar's end; its menu keeps "As a chart" and "As a table".
  - A table of no channel gets the button too: the canvas opens the same way and "Send the canvas" asks for the channel (the channels the person may post in, a search above); the binding is written for the chosen channel.
  - Where a table comes into being: the Data screen's table created just now opens with the button in place, and a table's card in a channel carries "Show on a canvas" in its menu with the same two entries.
- `ui/image-editor-data-menu.md` and `ui/image-editor-data-show.md` are corrected in the step; its Screen check names the three places at 1440 and 390 px.

## What is asked of the architect first?
- A part the canvas has no model for (the sample card, the segments inside a panel group): a design example, before it is built (`workflow/screen-described-in-plan.md`).
