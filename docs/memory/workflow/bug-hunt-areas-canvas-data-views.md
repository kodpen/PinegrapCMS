---
name: bug-hunt-areas-canvas-data-views
description: The bug hunt of the Artifact Canvas data views (DV1-DV6, #11353, 2026-11-24): the four faults fixed, what was read clean, what was not run
---

## Which area was hunted?
- Architect (2026-11-24, area 7): the Artifact Canvas data views, closed out after DV1-DV6. Asked: a column renamed or deleted, a table the reader may not read, an empty and a very long table, one view opened by two people, the API and MCP against the canvas's and the table's rights, the Data picker on a phone.
- Read: `includes/fn/image_editor_data.php`, `includes/workspace/canvas_tables.php` (row read), `canvas_data_write.php`, `canvas_edit.php` and `api_canvas.php` (agent writes), `workspace_file_save.php` (`canvas_data`), `assets/js/pg_image_editor_data.js`, the model's DATA section, `workspace_canvas_picture.js`, the data CSS. Feature: `workspace/canvas-data-views.md`.

## What did #11353 fix?
- A money column was drawn in minor units, so a chart of 12.50 read 1250; a status drew its key and a person a user id. The row read now hands the cells as the table's screen shows them (`platform/image-server-draw-data.md`).
- A deleted label column jammed Label column (the first column could not be picked) and a deleted value column hid in the description; a table with no column left broke "Show on a canvas" with a script error (`ui/image-editor-data-column-gone.md`).
- On a phone a table or channel name with no space widened its picker row past the screen: the row's words now break inside it.

## What was read clean?
- A renamed column: layers hold column ids, names come with each read. A table the reader may not read: `data_rows`, `canvas_data` and the agent's resolver all go through `ws_wtable_for(…, 'view')`; the file the Workspace keeps is bare. Deleted columns' leftover cells never reach a read (`ws_wtable_cells_merge()`).
- An empty table: Table alone fits, "No rows to show." A long one: tables stop at 5000 rows, the read takes 5000, a chart 24 labels, a table 50 rows.
- Two readers: each draws the card from their own reads; nothing is written. The agent route and the assistants check the table with the application's owner or the asker, with the same `workspace:write` scope as the other canvas edits.

## What was not run?
- No database or browser: the cell shape, the panel and the picker are tried under PHP and Node; the CSS rule by its text.
