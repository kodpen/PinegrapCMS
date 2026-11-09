---
name: agent-canvas-picture
description: How an agent's pending canvas gets its picture: the channel's browser draws it with the image editor's renderer and posts it once (action canvas_picture), the take's checks, the versioned address
---

## Who draws an agent's canvas?
- The server never rasterises SVG: an agent's canvas is posted with a blank picture and its version marked `picture: pending` (`agent-canvas-svg.md`). The first browser of a member who may write in the channel (`can_post`) that shows the message draws it (#8254).
- The message payload's file carries `canvas_pending` (the version number to draw, 0 otherwise) from `ws_canvas_message_state()`: one query on the newest `file_versions` row, only for a PNG picture.
- `assets/js/workspace_canvas_picture.js` (in `workspace.min.js`), called from `filePreview()`: loads the editor (`pgImageEditor.load()`), asks the document (`action: 'canvas'`), draws it with a painter on the editor's prototype that opens no dialog (`M.canvasOpenDoc()`, `layersReady()`, `drawArtboard()`; it sets `doc`, `pictures`, `textFonts`, a no-op `renderView`) and posts `{action: 'canvas_picture', version, picture}` once per message and version per page. Nothing is shown either way.

## What does the server take?
- `ws_canvas_picture_take()` (`includes/workspace/canvas.php`), from `workspace_file_save.php?message=<id>`: the member may post in the channel (403), the newest version is `version`, an agent's, still pending, and the file still holds that version's pixels (409 otherwise); the PNG is the artboard's size (`ws_canvas_picture()`, 422).
- Once: a conditional `UPDATE file_versions ... WHERE doc_hash = <old>` claims it; a second browser gets 409. The PNG is written over the message's file in place (same file and address, local directory as the editor's restore does) and over the version's copy, the document loses `picture`, so `ws_canvas_open_doc()` still opens the layers and the read says drawn. `ws_message_touch()` makes other screens fetch the message again.

## Why the `?v=` on a canvas's address?
- The file keeps its address when drawn, so a browser that kept the blank picture would show it on. A picture with a kept document is shown under `ws_canvas_picture_url()`: `?v=<version>` plus `p` while pending.

## Pending only for an agent's version?
- A person who opens a pending canvas in the editor saves the whole document, the mark included; that version (source `new`/`saved`) is drawn by their browser, so `ws_canvas_doc_pending()` needs source `agent`. The read (`ws_canvas_read()`) uses it too.

## Tests?
- `tests/workspace_canvas_picture_test.php`: the pending rule, the address, the read. The drawing needs a browser: run once with Chromium on a document from `ws_canvas_from_svg()` (gradient, shape, fill drawn; one post). The take itself needs a database and was not run.
