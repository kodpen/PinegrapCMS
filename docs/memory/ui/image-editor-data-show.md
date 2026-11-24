---
name: image-editor-data-show
description: "Show on a canvas" on a Data table's head (DV3, DV2b): As a chart / As a table, a new canvas in the table's channel or one asked at the send, the table bound read-only, who may
module: image_editor_data
---

## Where is it?
- On an opened table of the Data screen (`workspace_wtables.php?id=N`), first in the head's button bar: "Show on a canvas" as a `pg-button pg-button-ghost` with the Design Canvas's one-colour icon and its word, opening a `.menu` with "As a chart" and "As a table" (Menus pattern of the Design examples screen). The feature: `workspace/canvas-data-views.md`.
- DV3 drew it quiet and small after Run from table, only for a table of a channel; the owner did not find it (2026-11-21), so DV2b moved it first, gave it the plain look and offered it for every table.
- Shown to someone who may use it (`pg_image_editor_data_show_check()`); the screen prints the editor's loader and the JSON it hands the editor (`pg_image_editor_data_show_assets()`) after the bar. A table made just now on the Data screen (no channel) opens with it in place.

## What does an entry do?
- Opens the Design Canvas on a blank 1920×1080 white board, no New image dialog, with `dataShow {table, show}`: the Data picker opens at step 2 ("Pick a view") on that table, no Back and no list of tables (`openDataPicker(null, start)`, `pg_image_editor_data.js`). The layer is made by DV2's own code (`dataPlace()`).
- Cards: `M.dataShowKinds()`. As a table: Table alone. As a chart: the chart kinds the columns fit; none fits: Table alone and the line "Only a table fits: this table has no number column."
- "Send the canvas" posts it to the table's channel (`workspace_file_save.php?table=<id>&channel=<home>`); the screen then opens the channel on the new message.

## And a table of no channel?
- `pg_image_editor_data_show_config()` hands `channel: 0` and `channels` (`ws_library_channels()`: id and name of the channels the person may post in); the editor opens with `save {mode: 'url', ask: true}` and `send {channels, url}`.
- `save.ask` (core `pg_image_editor.js`, `asksChannel()` / `askChannel()`): the main button opens the send list under it, a find field always above; a channel chosen becomes `save.url` and the save goes there. The menu then offers no "Send to another channel". With no channel the list says so.

## Who may, and what is refused?
- Someone who reads the table (`ws_wtable_for(…, 'view')`) and may post in the channel the canvas goes to (`ws_can_post_channel()`): the table's own channel; for a table of no channel the one asked (before the send it is still to be asked, so the screen shows the button).
- Pure rule `pg_image_editor_data_show_refusal()`: `table` "You cannot read this table.", `channel` "That channel could not be found." (a channel not found, or another than the table's own), `post` "You cannot post in that channel." The save checks it before anything is posted.
- Unlike Bind a table (`workspace/canvas-tables.md`), writing the table is not asked: the binding is read only and the layer reads with each reader's rights.

## What is bound?
- After the save, when the document holds a data layer of that table (`pg_image_editor_data_doc_shows()`): one `ws_canvas_tables` row for the new canvas in the channel it went to, access `read`, bound by the person (`pg_image_editor_data_show_binding()`, `INSERT IGNORE`). The card follows the rows: `workspace/canvas-data-follow.md`.

## How is it checked?
- `tests/image_editor_data_show_test.php`: who may and the words, the save and open addresses with and without a channel, the read-only binding, the menu's markup, the kinds, the picker opened on the table, the channel asked at the send under Node.
