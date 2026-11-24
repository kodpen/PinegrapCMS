---
name: canvas-data-views
description: A Data table on a canvas as a chart or table (data layer, DV1-DV6): owner decisions, where it follows its rows or is a still, its rules, which file holds each part
module: image_editor_data
---

## What is it?
- A data layer of the Design Canvas's document: it holds a description (table, chart or plain table, kind, label and value columns, grouping, sort, format, title, colours, type) and never the rows; the canvas draws it as vector shapes from the rows the person looking may read (`ui/image-editor-data-layer.md`).
- Built in DV1a #10752, DV1b #10823, DV2 #10892, DV2b #11035, DV3 #10926, DV4 #11120, DV5 #11150, DV6 #11228.

## Which decisions bind it?
- Owner (2026-11-20): a table shown on a canvas as a chart or a table, the kind chosen from samples, following the data; a picture saved as a picture does not follow; data changes cost no new canvas drawing; edited on the canvas through a Data menu; saved with its data in the File Manager (channel messages 9848, 9853; `workflow/decisions-made-workspace-41.md`).
- Architect (2026-11-20): a layer of the canvas document, not a picture or an HTML page; it follows on a channel's card and wherever the document is opened, with the reader's rights; an exported picture or video is a still.
- Owner (2026-11-21, agent channel message 11699): the Data menu and "Show on a canvas" must be found where he looks. So Data is its own top-bar button beside Import, not an entry inside it, and "Show on a canvas" is a worded button first in every opened table's head, also for a table of no channel (`ui/image-editor-data-menu.md`, `ui/image-editor-data-show.md`).

## Where does it follow its rows, and where is it a still?
- Follows: a channel's canvas card, drawn again in each reader's browser, nothing kept (`canvas-data-follow.md`); the document opened in the editor from the Workspace or the File Manager. A table the reader may not read draws "You cannot read this table." in its layer.
- Bare (no rows): the Workspace's stored file, so Download from the card, the Files tab's tile, a guest's page and a thumbnail show no one's rows (`ui/image-editor-data-still.md`).
- A still with the saver's rows: a picture or video saved to Files, a Download or animation from the editor, so every site place showing a picture (a page, a product, a campaign). It never follows by itself: it is opened and saved again; the editor says so once.
- A changed description is a version as any save; changed rows are not.

## Which rules does it keep?
- No schema: the description lives in the document. The only binding is the read-only `ws_canvas_tables` row "Show on a canvas" writes; the Data menu and an agent write none, and the card does not ask for one.
- A data layer writes nothing to its table. Rows come through one read with the reader's rights, `ws_canvas_data_rows()`, 5000 at most.
- No chart library in the Design Canvas; the browser's model and the server's port draw the same marks from one fixture (`tests/fixtures/canvas_data/layers.json`).

## Which file holds each part?
- The layer, its description and marks: `ui/image-editor-data-layer.md`. The server's draw and row read: `platform/image-server-draw-data.md`.
- The top bar's Data button, picker and panel: `ui/image-editor-data-menu.md`. "Show on a canvas" on a Data table: `ui/image-editor-data-show.md`.
- The card following its rows: `canvas-data-follow.md`. Saves, stills and the bare file: `ui/image-editor-data-still.md`.
- An agent's add and change over the API and MCP: `agent-canvas-data-layers.md`.

## How is a new chart kind added?
- In the model (`M.dataCheck()`, `M.dataFits()`, `M.dataMarks()`), with its marks in the shared fixture so the PHP port is held to the same list; the picker and the panel's Look cards take it from `M.dataFits()`; its refusal code joins `ws_canvas_data_write_error_text()` for agents.
