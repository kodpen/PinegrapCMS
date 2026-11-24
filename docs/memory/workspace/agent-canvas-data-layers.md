---
name: agent-canvas-data-layers
description: An agent adds or changes a canvas's data layer by its description (DV6 #11228, canvas_data_write.php): add type data, the data operation, the caller's read of the table, the box, the refusals, the read
---

## What can an agent do with a data layer?
- In a canvas edit (POST messages/{id}/canvas, its MCP tool, the assistants' `edit_canvas`): `add` with `type: 'data'` and `data` (the layer's description, `ui/image-editor-data-layer.md`) puts a data layer on top; `{op: 'data', layer, data}` lays the sent fields over a data layer's description. Built in DV6 (#11228).
- The route's text tells agents to add a data layer in place of a drawn `chart` when the numbers live in a Workspace table: the canvas then follows its rows (`workspace/canvas-data-follow.md`), a drawn chart does not.

## How is the description checked?
- `ws_canvas_data_write_check()`: `pg_image_draw_data_check()` (the model's codes and defaults, `platform/image-server-draw-data.md`), then the table must be one the caller may read and `label` and `values` must be its column ids. A change is checked whole after the merge, so a donut asked with two values is refused.
- The read comes from `ws_canvas_data_table_resolver($viewer)` (`canvas_tables.php`): `ws_wtable_for(…, 'view')`, the column ids or null. The route passes the application's owner, the assistants the person who asked. `ws_canvas_edit_apply()` without it (templates, layouts) refuses every data layer.
- No binding (`ws_canvas_tables`) is written, as the Data menu writes none; the rows are never kept in the document.

## Where does a new layer go?
- As the Data menu's: 60 % of the board wide at 16:9, in the middle (`dataPlace()`), unless x, y, w, h are sent; `rot` 0, `opacity` 1. `name` and `lock` as any add. `box`, `style` (opacity), `order`, `name`, `lock`, `remove` work on it as on any layer; a locked one is not changed.

## What is refused?
- 422 on operations, "Operation n:" first: `data` (no object, or the change no object of fields), `data_read` (a table not read), `not_data` (data on another layer), and the model's `data_table`, `data_show`, `data_kind`, `data_group`, `data_columns`, `data_sort`, `data_format`, `data_title`, `data_colours`, `data_theme`, `data_font`, `data_legend`, `data_limit`, `data_size`, words in `ws_canvas_data_write_error_text()`.

## How does an agent read one back?
- GET messages/{id}/canvas (and `read_canvas`) gives a data layer's `data`, the checked description; null on any other layer. Column ids come from GET /workspace/tables/{id}.
- The picture is pending after the edit; the server's draw waits for a browser on a data layer (no reader), which draws it without rows in the stored file (`ui/image-editor-data-still.md`).

## How is it checked?
- `tests/workspace_agent_canvas_data_test.php`: add with and without a box, the refusals, the change, the read and the route's field.
