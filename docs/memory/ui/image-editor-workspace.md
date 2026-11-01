---
name: image-editor-workspace
description: Workspace canvas (E16, #4762): New canvas in a channel's Insert menu and a note's toolbar, saved through workspace_file_save.php with its document in file_versions, opened again with its layers
---

## Where does "New canvas" start?
- A channel's composer, Insert menu (`newCanvas()` in `workspace.js`): a 1080 white artboard, the New image dialog first; "Send the canvas" posts it as the person's own message (`?channel=<id>`), the picture showing in the message as any image does.
- A note's toolbar, beside the paperclip (`drawCanvas()`): the note is saved first, the canvas kept as the note's file (`?note=<id>`, `ws_note_file_store_bytes()`), linked where the caret was once the editor has closed (the open() promise: while the editor is open the caret is in its dialog and the link was lost).
- The notes screen prints the editor's loader too (`ws_screen_assets()`, modes channels and notes).

## How is a canvas saved?
- `save.url` with an artboard sends the PNG the editor draws (`picture`, `drawArtboard()`) in place of the overlay, and `name` (core `send()`). `ws_canvas_picture()` checks it against the artboard size (`pg_image_editor_take_png()`) and recompresses it.
- The document is kept beside the file written as a `file_versions` row, source `new` (`ws_canvas_keep()`): the canvas's file, or the new file a replace or a sent copy wrote. One that may not be kept (a pasted picture, over 1 MB: `pg_file_versions_doc_storable()`) leaves only the picture.

## How does it open again?
- "Edit the picture" on a message first asks `workspace_file_save.php?message=<id>` `{action: 'canvas'}`: `ws_canvas_open_doc()` answers the newest version's document while the file still holds its pixels and it is a canvas (`ws_canvas_is_doc()`: v 1, artboard, no source), else null.
- With a document the editor opens `{blank: doc.artboard, doc}`: `M.canvasOpenDoc()` gives the editor's document, texts and shapes editable, and the New image dialog is not asked. Save with an artboard document on `?message=` replaces or sends the canvas as a picture does.

## What is left?
- A note's canvas opens again only through its file today: no edit action on a note's link yet.

## Where is it tested?
- `tests/workspace_canvas_test.php`: `ws_canvas_is_doc()`, `ws_canvas_doc_json()`, `ws_canvas_file_name()`, the labels and `canvasOpenDoc()` under Node.
