---
name: image-editor-data-still
description: A canvas with data layers saved out of the Artifact Canvas (DV5): the Workspace's file drawn without rows, Files keeps the layers, an export is a still that says so
module: image_editor_data
---

## What does the Workspace's file of a canvas with data layers show?
- Its data layers as frames with no rows and the line "The rows show where the canvas is opened." (`dataBare`). That file is what Download from the card, the Files tab's tile, a guest's page and a message's thumbnail show, to every reader of the channel whatever table they may read. The feature: `workspace/canvas-data-views.md`.
- Why: before DV5 it held the rows of whoever saved it, so a reader who may not read the table (a guest above all) saw them. DV4 (`workspace/canvas-data-follow.md`) named this; the card draws the rows again for each reader, so the file needs none.
- Which saves: `M.dataSendBare(mode, to)` is true for the caller's `save.url` (a channel's or a note's canvas, new or edited in place) and a copy sent to a channel (`to.url`); `sendBody()` then draws with `drawArtboardBare()`. A pending agent canvas's picture drawn by a member's browser (`workspace_canvas_picture.js` `draw()`) is bare too and reads no rows.

## What stays a still with the rows of now?
- Save to Files as a picture or a video from a channel (`to.folder`), a save in the File Manager, Download from the editor's menu and Save animation: the person saving chose to keep the picture, drawn from the rows they may read.
- Each says so once: the line "A saved picture keeps the rows as they are now and does not follow the table. The canvas opened again reads them anew." (`dataStill`, `dataStillLine()`) in the Save to Files window, the Export settings panel and the Save animation dialog, shown only when a scene holds a data layer (`M.dataTablesOf()`); after a Download, as a toast once per opened editor (`dataStillSay()`).
- A translated language's picture (`drawLanguage()`, a painter made without the editor's inits) holds no reads and draws bare, asking nothing: it is drawn in the background by any panel user's browser and published on the site.

## Does a canvas in Files keep its data layers?
- Yes: the document is kept as the file's newest version as any canvas (`ui/image-editor-canvas-save.md`), the layers holding only their description. Opened from the File Manager, each layer reads its table once with the opener's rights (action `data_rows`, `ws_canvas_data_rows()`), as DV4's card does; a table they may not read says "You cannot read this table."

## How is it checked?
- `tests/image_editor_data_still_test.php`: `M.dataTablesOf()`, `M.dataSendBare()`, the bare drawing and the painter with no reads under Node, the line and the toast, the wiring. Not run in a browser: the save, the pending picture and the three dialogs.
