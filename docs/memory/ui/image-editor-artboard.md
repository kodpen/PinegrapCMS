---
name: image-editor-artboard
description: The image editor canvas (E12): New image dialog, Canvas tool on a board and on a photo (grow or trim with a fill), outward frames, Image tool, the ARTBOARD model section
module: image_editor
---

## When does the New image dialog open?
- On every `pgImageEditor.open({ blank })` outside the mini bar, over the editor once the empty canvas is ready (`openNewImage()` in `pg_image_editor_artboard.js`). Presets square 1080, portrait 1080×1350, story 1080×1920, share 1200×630, banner 1920×600 (`M.BOARD_PRESETS`), custom (width and height); backgrounds white, transparent, black #000000, light blue (`M.BOARD_BACKGROUNDS`). The File Manager's New image opens at the square preset, 1080 (`BLANK_IMAGE_SIZE` in `view_folders.php`).
- It starts on the caller's size (its preset, else custom with those numbers) and background. Create sets `doc.artboard` and makes that the opening document: nothing to save, nothing to undo. Cancel or Escape keeps the canvas as the caller opened it.
- Its keys stay in it (a `keydown` listener stops them reaching the editor: no shortcut, no Ctrl+S behind it); Tab goes round, Enter in a size field creates; a paste is stopped too.

## What does the Canvas tool do?
- Eight handles on a dashed board over the stage (`renderBoard()`, a view hook), its size above. A drag is `M.boardDrag()`: an edge moves its side, a corner two; Shift keeps the ratio (from an edge the other side grows around its middle), Alt moves the opposite side too (from the centre). Sides 16-8000.
- The board moves, the artwork stays: `M.applyBoard()` gives the new size in whole pixels and moves every layer the other way (`M.mapLayer()`), one undo step (`setBoard()`). A zoomed or moved view keeps the artwork where it is on screen; a fitted view fits the new board.
- Panel: canvas size (placed by the nine-point anchor, `M.boardAnchored()`, default centre, kept per editor), Fit to content (`M.boardAround()` of the visible layers), Fit to the selection (with a selection), preset chips, background swatches (`X.swatches` with transparent). The background redraws through the same view hook, so undo brings it back.
- Select with nothing selected on a canvas shows the canvas size and background above the layers, titled Canvas (one line in `renderPanel()` of the core).

## What does the Image tool do?
- "Choose an image from this computer" (a file input, several at once) and "Picture from Files" (the sticker part's `stickerFilesSection()`; its refresh also runs for the Image tool), each through `addPicture()` / `addFilePicture()` of the layers and sticker parts. Pictures dropped on the stage become layers with any tool (`bindDrop()`); a paste already did (`onPaste()`).

## What does the Canvas tool do on a photo? (#4741)
- The photo profile has it after Crop. `doc.canvas` = `{ l, t, r, b, background }`: how far the canvas reaches past each side of the crop box in the rotated space (below 0 trims), `null` when it is the crop box with white (`M.applyCanvas()`). Same handles, sizes, anchor and presets; Fit to content takes the crop box and the visible layers. The layers stay put; the view follows the shown box (`followBoard()`).
- The stage shows `M.saveBox()` (the core's `viewRect()` outside cropping); the fill is an SVG mat under the layers over the canvas less the crop box (`renderMat()`, a checker for transparent). A crop shape rounds the whole canvas, as the save does, the vignette only the crop box (`placeVignette()`).

## How does a frame grow the canvas?
- `frame.outside` (line, thick, polaroid; `M.FRAME_OUT`): the save box grows by `M.framePads()`, worked out so the inward ring `frameShape()` draws on the grown box has the canvas as its hole. Polaroid on a photo starts outside (screen 08); a checkbox in the Frame panel toggles it. On an artboard frames stay inward.

## Where is it tested?
- `tests/image_editor_artboard_test.php`: presets, drag, anchor, fit, applyBoard; the photo canvas, outward frame hole, layout and output scale (Node); the save side in `image-editor-canvas-save.md`.
