---
name: image-editor-canvas-save
description: How a photo's grown or trimmed canvas and an outward frame are saved (extend step), and how pictures added from the computer become files rows on save
module: image_editor
---

## How is it saved?
- `M.saveLayout()`: the canvas in output pixels (`w`, `h`), the picture's place on it (`x`, `y`) and the fill (the canvas's, else the outward frame's colour), sent as `extend` by a save hook for every mode but new. The overlay and frame are drawn at `M.saveSize()` over `M.saveBox()`.
- Server: `pg_image_edit_extend()` checks it (whole numbers, 1-8000, the picture touching the canvas, `#rrggbb` or transparent); the engine's `extend` step comes after redaction and before the overlay (Imagick `extentImage()`, GD `pg_image_edit_gd_extend()`); a transparent fill is white for a format without alpha; the crop shape cuts the whole canvas.
- The preview's vignette covers the canvas, the server's only the picture.

## What happens to pictures from this computer?
- `addPicture()` keeps `fileName` and turns any type but PNG or JPEG into PNG. A save (replace, copy, new) sends each image layer with a data: src and no `fileId` as `pictures` [{id, data, name}]; after the file is written `pg_image_editor_pictures_saved()` keeps each as its own `files` row in the target folder (the file's folder, or `folder_id`), points the document's layer at it (`src`, `fileId`) before the version keeps it, and answers `pictures` (layer id => {id, url}); `takePictures()` updates the editor's document. Nothing is sent before Save, so a cancelled edit leaves no file. A picture that cannot be kept stays in the document as data (the version then keeps the drawn pixels).

## Where is it tested?
- `tests/image_editor_artboard_test.php`: `pg_image_edit_extend()` and its box, `pg_image_editor_picture_list()`, GD's extend step (`pg_image_edit_gd_extend()`).
