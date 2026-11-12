---
name: agent-canvas-picture
description: How an agent's canvas gets its picture: the server draws it where it can (mark server), else a member's browser posts it once (canvas_picture); the takes, the ?v= address
---

## Who draws an agent's canvas?
- An agent's canvas is kept with a blank picture and its version marked `picture: pending` (`agent-canvas-svg.md`).
- The server draws it right after the version is kept, where its Imagick draws every part of the document (`ws_canvas_draw_now()` in `includes/workspace/canvas_draw.php`, renderer `platform/image-server-draw.md`): API create and edit, the assistants' `draw_canvas` and `edit_canvas`. The mark becomes `picture: server` (CV6).
- A document the server does not draw stays pending. Either way the first browser of a member who may write in the channel (`can_post`) that shows the message draws it with the editor's own renderer (#8254); for a server picture that browser's picture replaces it.
- The message payload's file carries `canvas_pending` (the version number to draw, 0 otherwise) from `ws_canvas_message_state()`, for pending and server versions: one query on the newest `file_versions` row, only for a PNG picture.
- `assets/js/workspace_canvas_picture.js` (in `workspace.min.js`), called from `filePreview()`: loads the editor (`pgImageEditor.load()`), asks the document (`action: 'canvas'`), draws it with a painter that opens no dialog (`M.canvasOpenDoc()`, `layersReady()`, `drawArtboard()`) and posts `{action: 'canvas_picture', version, picture}` once per message and version per page. Nothing is shown. Its header comment still says the server never draws (a comment edit changes the bundle).

## How does the server's picture go in?
- Only while the newest version is an agent's, still pending, and the file still holds that version's pixels. The PNG is made smaller by `pg_process_image_file()` only when that keeps every pixel.
- The same claim as a browser's: `UPDATE file_versions ... WHERE doc_hash = <old>`; then the version's copy and the file are written in the file directory. A failed write puts the row and the old bytes back. Any failure leaves the canvas pending.
- A picture layer is drawn only from a file every reader may see (`ws_canvas_draw_picture_path()`): one a message of the channel carries, a public file, or one in the canvas's folder. Any other address waits for a browser, which loads it with its reader's rights.

## What does the server take from a browser?
- `ws_canvas_picture_take()` (`includes/workspace/canvas.php`), from `workspace_file_save.php?message=<id>`: the member may post in the channel (403), the newest version is `version`, an agent's, pending or server (`ws_canvas_doc_mark()`), and the file still holds that version's pixels (409); the PNG is the artboard's size (422).
- Once: the conditional `UPDATE` claims it; a second browser gets 409. The PNG goes over the message's file in place and over the version's copy, the document loses `picture`, so `ws_canvas_open_doc()` still opens the layers. `ws_message_touch()` makes other screens fetch the message again.

## Why the `?v=` on a canvas's address?
- The file keeps its address when drawn. A picture with a kept document is shown under `ws_canvas_picture_url()`: `?v=<version>` plus `p` while pending, `s` with the server's picture, nothing once a browser drew it.

## A mark only on an agent's version?
- A person who opens a marked canvas in the editor saves the whole document, the mark included; that version (source `new`/`saved`) is drawn by their browser, so `ws_canvas_doc_mark()` needs source `agent`. The read (`ws_canvas_read()`) says pending only for `pending`.

## Tests?
- `tests/workspace_canvas_picture_test.php`: the marks, the address, the read; `tests/image_draw_test.php` for the renderer. The take and `ws_canvas_draw_now()` need a database and were not run.
