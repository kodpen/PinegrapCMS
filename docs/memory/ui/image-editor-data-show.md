---
name: image-editor-data-show
description: "Show on a canvas" on a Data table's head (DV3): As a chart / As a table, the picker's step two on the table, a new canvas in the table's channel, the table bound read-only, who may
module: image_editor_data
---

## Where is it?
- On an opened table of the Data screen (`workspace_wtables.php?id=N`), in the head's button bar after Run from table: a quiet small "Show on a canvas" button (`pg-button-quiet`) (the Design Canvas's one-colour icon) opening a `.menu` with "As a chart" and "As a table" (Menus pattern of the Design examples screen). Plan: `plans/canvas-data-views.md`, screens `plans/canvas-data-views-screens.md`.
- Shown only to someone who may use it (`pg_image_editor_data_show_check()`); the screen prints the editor's loader and the JSON it hands the editor (`pg_image_editor_data_show_assets()`) after the bar.

## What does an entry do?
- Opens the Design Canvas on a blank 1920×1080 white board, no New image dialog, with `dataShow {table, show}`: the Data picker opens at step 2 ("Pick a view") on that table, no Back and no list of tables (`openDataPicker(null, start)`, `pg_image_editor_data.js`). The layer is made by DV2's own code (`dataPlace()`).
- Cards: `M.dataShowKinds()`. As a table: Table alone. As a chart: the chart kinds the columns fit; none fits: Table alone and the line "Only a table fits: this table has no number column."
- "Send the canvas" posts it to the table's channel (`workspace_file_save.php?channel=<home>&table=<id>`); the screen then opens the channel on the new message.

## Who may, and what is refused?
- Someone who reads the table (`ws_wtable_for(…, 'view')`), whose table belongs to a channel, and who may post in it (`ws_can_post_channel()`). Pure rule `pg_image_editor_data_show_refusal()`: `table` "You cannot read this table.", `channel` "That channel could not be found." (a table of no channel, or a save to another channel), `post` "You cannot post in that channel." The save checks it before anything is posted.
- Unlike Bind a table (`workspace/canvas-tables.md`), writing the table is not asked: the binding is read only and the layer reads with each reader's rights.

## What is bound?
- After the save, when the document holds a data layer of that table (`pg_image_editor_data_doc_shows()`): one `ws_canvas_tables` row, access `read`, bound by the person (`pg_image_editor_data_show_binding()`, `INSERT IGNORE`). Following the rows in the channel is DV4.

## How is it checked?
- `tests/image_editor_data_show_test.php`: who may and the words, the channel and save address, the read-only binding and when it is written, the menu's markup, the kinds, the picker opened on the table under Node.
