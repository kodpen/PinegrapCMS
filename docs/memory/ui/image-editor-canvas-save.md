---
name: image-editor-canvas-save
description: How a photo's grown or trimmed canvas and an outward frame are saved (extend step), pictures from the computer as files rows, a canvas file keeping and reopening its document
module: image_editor
---

## How is it saved?
- `M.saveLayout()`: the canvas in output pixels (`w`, `h`), the picture's place on it (`x`, `y`) and the fill (the canvas's, else the outward frame's colour), sent as `extend` by a save hook for every mode but new. The overlay and frame are drawn at `M.saveSize()` over `M.saveBox()`.
- Server: `pg_image_edit_extend()` checks it (whole numbers, 1-8000, the picture touching the canvas, `#rrggbb` or transparent); the engine's `extend` step comes after redaction and before the overlay (Imagick `extentImage()`, GD `pg_image_edit_gd_extend()`); a transparent fill is white for a format without alpha; the crop shape cuts the whole canvas.
- The vignette covers the picture only, in the preview (`placeVignette()`) and the save.

## What happens to pictures from this computer?
- `addPicture()` keeps `fileName` and turns any type but PNG or JPEG into PNG. A save sends each image layer with a data: src and no `fileId` as `pictures` [{id, data, name}] for replace, new and a canvas's copy; a photo's copy sends none and keeps none (it keeps no document: the pictures are in its pixels).
- After the file is written `pg_image_editor_pictures_saved()` keeps each as its own `files` row in the target folder, through the site's upload cap (`pg_image_upload_cap_apply()`, as a File Manager upload), points the document's layer at it (`src`, `fileId`) before the version keeps it, and answers `pictures` (layer id => {id, url}); `takePictures()` updates the editor. Nothing is sent before Save, so a cancelled edit leaves no file.

## How does a canvas keep its document in Files? (holder's review on #4741)
- `pg_image_editor_save_canvas()` writes an artboard's PNG as new (`folder_id`), over the canvas (replace, a PNG only) or beside it (copy), then `pg_image_editor_canvas_keep()` keeps the document as the newest version (source `new`, `saved` for a replace), after the pictures became files.
- `pg_image_editor_open()` answers `canvas: true` with the document while the file still holds those pixels (`pg_image_editor_canvas_open_doc()`); the artboard part's `openFile()` opens it with `M.canvasOpenDoc()`, the canvas tools and no New image dialog. The Workspace's `ws_canvas_*` call the same `pg_image_editor_canvas_*` functions.

## Where is it tested?
- `tests/image_editor_artboard_test.php`: `pg_image_edit_extend()` and its box, `pg_image_editor_picture_list()`, GD's extend step (`pg_image_edit_gd_extend()`), a canvas document kept and opened again with its text layer, the save order.
