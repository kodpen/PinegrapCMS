---
name: canvas-data-views
description: Plan (owner 2026-11-20): a Data table as a chart or table layer of the Design Canvas's document: a Data menu, edited on the canvas, following its table where the document is shown; DV1-DV6
---

## What did the owner ask?
- Owner (2026-11-20, agent channel message 9848): a table or a calculation shown on a canvas as a chart or a table, the kind chosen from samples of the data, following the data; a saved picture does not follow.
- Owner (message 9853): the editing is on the canvas, as what opens there is the same editable structure and not pixels; the canvas gets a "Veri" menu to add data as a plain table or a chart kind; site-wide, saved with its data in the File Manager; other places may keep a still ("karar senin").
- Record: `workflow/decisions-made-workspace-41.md`.

## What is the shape?
- Architect (2026-11-20): a data layer of the Design Canvas's document. It holds a description (the table, chart or table, the kind, label and value columns, grouping with sum, count or average, sort, number format, title, colours, type) and never the rows; the canvas draws it as vector shapes from the rows it reads.
- Where it follows the data: a Workspace canvas's card, and the document whenever it is opened, from the Workspace or the File Manager, with the reader's rights; rows the reader may not read give an empty layer that says so.
- Where it is a still: an exported picture or video, and so every place of the site that shows one (a page, a product, a campaign). A public picture never changes by itself with a table inside the panel; it is opened and saved again.

## What is built?
- DV1, the layer in the model and its drawing: the description's check, the kinds (bar, stacked bar, line, area, donut, plain table) with the chart guide's look (`workspace/agent-canvas-chart.md`), drawn alike by the browser and by the server's draw of a document (`platform/image-server-draw.md`); rows through one read function with the reader's rights and the 5000-row bound.
- DV1a (done, #10752): the model, the browser's drawing. DV1b: the server's draw, the row read.
- DV2, the Data menu in the Design Canvas: pick a table the person may read, then "Table" or a chart kind from samples drawn with its first rows (only kinds its columns fit); the layer lands on the stage. Its panel changes columns, grouping, title, legend, colours (the guide's palette, the channel's tone, chosen ones) and type; it moves, sizes, groups and animates as any layer.
- DV3, the Data screen: a table's head gets "Show on a canvas" with "As a chart" and "As a table": a new canvas in the table's channel holding that one layer, the table bound read-only.
- DV4, following in the channel. The step's first commit is a census choosing how a card with data layers shows the current rows without a model call and without a version per change: the server's draw made again when a bound table's change stamp is newer than the picture, or the layers drawn in the card. A changed description is a version; changed rows are not.
- DV5, the File Manager and export: a document saved to the site's files keeps its data layers and reads the rows when opened; export writes a still and says so once; "Save as picture" in a channel does the same.
- DV6, the API and MCP: an agent adds or changes a data layer by the description, in place of drawing a chart's picture; phone width for the menu and the panel.
- Order DV1 to DV6, one PR each; built on the layer groups (`ui/image-editor-groups.md`), as both change the layer model.

## Which rules does every step keep?
- No schema foreseen: the description lives in the document, the binding in `ws_canvas_tables`; a step that needs a column stops and asks the architect.
- A data layer writes nothing to its table. No library in the Design Canvas (`ui/image-editor-overview.md`). Texts through `lang()`; unit tests in Node on the model and in PHP on the server's draw, from one fixture.

## How does the plan close?
- A `ui/image-editor-data-layer.md` and `workspace/canvas-overview.md` say the state; this plan is deleted.
