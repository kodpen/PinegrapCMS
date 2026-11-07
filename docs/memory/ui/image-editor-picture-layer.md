---
name: image-editor-picture-layer
description: A photo's picture as the bottom layer (E30, #6470): doc.canvas.picture, unlocking, move, resize and delete like a layer, the extend step's pw and ph
module: image_editor
---

## What is stored?
- `doc.canvas.picture = { x, y, k, locked }`: the crop box scaled by `k` and moved by `x, y`, in the layers' units (`M.picturePlace()`, `M.pictureBox()`, `M.placeMatrix()`). Without it the picture lies on its crop box and is locked. `doc.canvas`'s margins are measured from the picture's box (`canvasBox()`), so a canvas that holds a picture field is never reset to `null` (`canvasAround()`).
- Unlocking (`M.lockPicture()`) makes a canvas on the picture when the photo had none; locking again keeps the place.

## What keeps the canvas and the layers in place?
- `M.placePicture(doc, box)`: the new place from the box's width (ratio kept), some of it left on the canvas; the margins are worked out again so the canvas box stays (`M.keepCanvas()`). Redaction, erased and adjusted areas belong to the picture's pixels and go with it (moved and scaled, brush size and feather too).
- `change()` of the core calls `M.keepCanvas(before, doc)`: a crop of a placed picture keeps the canvas, what stays of the picture stays in place. `reorient()` turns or flips a placed picture around its middle without the layers (the redaction areas turn with it).
- While cropping the frame works in the picture's own space: the stage draws it with `pictureMatrix()` and the layers' viewBox is carried by the place; otherwise `M.placedMatrix()` (stage, Animate's base move, the redaction preview).

## How is it used?
- Layers list (rail button and Select's panel with nothing selected): a last row "Arka plan" (`boardBackground`) with its lock; a press selects it when unlocked. The Canvas panel on a photo has the checkbox `pictureFree`.
- Select, picture unlocked: a press on the picture where no layer lies selects and moves it (snapping as a layer, Alt free); four corner handles resize it, the ratio kept; arrows nudge, Delete deletes, Escape drops the selection. Its panel is titled Arka plan as its row and shows the file's name, then lock and delete (before #6613 it read Katmanlar › Görsel, nameless). Each drag, nudge, lock or delete is one undo step.
- Delete: `M.dropPicture()` turns the document into an artboard of the canvas (its fill, the layers moved onto it, no redaction, erased or adjusted areas, adjustments or outward frame); `syncKind()` hides the picture, gives the canvas profile's rail, and gives the photo's back on undo (`restore()` calls it).

## How is it saved?
- `M.saveLayout()` adds `pw, ph` (the picture's size in output pixels) when `k` is not 1; the canvas keeps its own scale (output size over crop box). `pg_image_edit_extend()` checks them (1-8000, whole), and the extend step resizes the picture to them before laying it (Imagick `resizeImage()`, GD a resampled copy); `pg_image_edit_work_size()` counts them.
- A deleted picture saves as a canvas (`pg_image_editor_save_canvas()`): over the file only when it is a PNG, else as a copy.
- Tests: `tests/image_editor_picture_test.php`.
