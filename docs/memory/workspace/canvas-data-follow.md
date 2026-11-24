---
name: canvas-data-follow
description: A channel's canvas card with data layers follows its tables (DV4, #11120): drawn again in the reader's browser from the rows they may read, nothing kept; the canvas_data action, the marks, the viewer
module: image_editor_data
---

## What does a card with data layers show?
- The canvas drawn again in the browser of whoever looks at it, its data layers from the rows that reader may read, in place of the stored picture; the image viewer opened from the card shows that drawing too. The feature: `canvas-data-views.md`.
- Nothing is written: no picture, no version, no stamp. A changed description is a version as any save; changed rows are not. A table the reader may not read draws "You cannot read this table." in its layer.
- Why the browser and not the server's draw again: one stored picture is seen by every reader, the rows follow each reader's rights; the owner asked for no drawing cost per data change (channel message 9848).

## How does the conversation know?
- `ws_canvas_message_state()` reads `data` (the newest version's `doc_json` has `"type":"data"`, one LIKE). The message's file carries `canvas_data`; a joined card's `drawing.data` (`canvas_card.php`).
- `assets/js/workspace_canvas_picture.js` `follow(message, img, h)`: once the picture comes into view (IntersectionObserver), loads the image editor, the document (action `canvas`) and the reads (action `canvas_data`), draws with `render(doc, reads)` and sets `img.src` and `file.drawn`. The same drawing is reused for 60 s by message, part and address, so a conversation redrawn reads nothing again. The stored picture stays when it cannot be drawn.
- `readsFor()` gives the painter a read for every table its data layers name (null draws "No rows to show."), so it never calls the editor's `data_rows`. A pending agent canvas with data layers is drawn with the reads too.

## What does canvas_data answer?
- `workspace_file_save.php?message=<id>[&part=drawing]` `{token, action: 'canvas_data'}` -> `{status, reads: {table id: read}}` for anyone who reads the channel (`ws_file_edit_target()`).
- `pg_image_editor_data_card_reads()`: the tables of the open scene's data layers (`pg_image_editor_data_doc_tables()`, once each, 20 at most, `PG_IMAGE_EDITOR_DATA_CARD_TABLES`), each by `ws_canvas_data_rows()` with the reader's rights, shaped by `pg_image_editor_data_read_json()`.
- No `ws_canvas_tables` binding is asked: the editor's `data_rows` already gives a person every table they may read, and Data menu canvases bind none.

## What does the stored file show?
- Its data layers with no rows (DV5, `ui/image-editor-data-still.md`): Download from the card, the Files tab's tile, a guest's page and a message's thumbnail are seen by readers who may not read the table. A pending canvas's picture is drawn bare too, reading no rows.

## How is it checked?
- `tests/image_editor_data_follow_test.php`: the tables a document names, the JSON shape, the marks and the action wired, `readsFor()` under Node. Not run in a browser: the drawing is the pending canvas's renderer with reads handed in.
