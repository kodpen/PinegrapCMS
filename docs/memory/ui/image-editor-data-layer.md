---
name: image-editor-data-layer
description: The Artifact Canvas's data layer (DV1a, #10752): a Workspace table as a chart or a plain table, the description it holds, its check, grouping, the marks the browser draws, the shared fixture
module: image_editor
---

## What is a data layer?
- A layer `{type: 'data', x, y, w, h, rot, opacity, data}` whose `data` describes a Workspace table drawn as a chart or a plain table. It holds the description and never the rows; the rows are read when it is drawn. Plan: `plans/canvas-data-views.md`; owner record: `workflow/decisions-made-workspace-41.md`.
- It keeps upright when the picture turns (`UPRIGHT` in the model), as a text does. It writes nothing to its table.

## What does the description hold?
- `M.dataCheck(d)` fills the defaults or refuses with a code: `table` (id, `data_table`), `show` chart | table, `kind` bar, stacked_bar, line, area, donut (a plain table's kind is `table`), `label` (a column id), `values` (up to 5 column ids; none for count; a donut one), `group` none | sum | count | average, `sort` none | label | value with `dir`, `format` en | tr, `title` (120 letters, one line), `colours` (up to 5 `#rrggbb`), `theme` light | dark, `font` (`g:Inter` by default), `legend`.

## How are the rows shown?
- `M.dataSeries(d, columns, rows, words)`: 5000 rows at most; grouping gathers one label's rows in the order they first come; average is over the cells that are numbers; a cell that is no number counts 0 in a chart and its words in a plain table.
- A chart keeps 24 labels, a donut five slices and Other, a plain table 50 rows; `cut` says how many were left out ("%d more rows" under a table).

## How is it drawn?
- `M.dataMarks(d, w, h, read, words)`: the chart guide's look (`workspace/agent-canvas-chart.md`, `DATA_GUIDE` held to `ws_canvas_guide_values()` by the test), laid out at 675 on the shorter side and scaled to the box, as marks: `poly` (filled), `line` (round caps), `text` (baseline, anchor). Numbers rounded to hundredths, curves cut into pieces of three degrees, so a port draws the same list.
- `read` null (not read yet) or no rows: "No rows to show."; `{state: 'refused'}`: "You cannot read this table." with the title only.
- A plain table's numbers are written in full; a chart's by `ws_canvas_chart_number()`'s rule (12.3K / 12,3 B).
- Browser: `pg_image_editor_data.js` draws the marks as SVG on the stage and on the save's canvas; `editor.dataRead(table, read)` hands it the rows (`{columns, rows}` or `{state: 'refused'}`). Labels in `pg_image_editor_strings_data()`.

## What is not built yet?
- DV1b: the server's draw of a data layer (`platform/image-server-draw.md`; until then a document with one waits for a browser) and the one row read with the reader's rights. The Data menu and panel are DV2.

## How is it checked?
- `tests/image_editor_data_test.php` under Node; `tests/fixtures/canvas_data/layers.json` holds descriptions, rows and the marks every kind must give, for the model and the server's port alike.
