---
name: image-editor-artboard
description: The image editor canvas (E12): New image dialog on a blank open, Canvas tool (drag, anchor, fit, presets, background), Image tool, the ARTBOARD model section
module: image_editor
---

## When does the New image dialog open?
- On every `pgImageEditor.open({ blank })` outside the mini bar, over the editor once the empty canvas is ready (`openNewImage()` in `pg_image_editor_artboard.js`). Presets square 1080, portrait 1080×1350, story 1080×1920, share 1200×630, banner 1920×600 (`M.BOARD_PRESETS`), custom (width and height); backgrounds white, transparent, black, light blue (`M.BOARD_BACKGROUNDS`).
- It starts on the caller's size (its preset, else custom with those numbers) and background. Create sets `doc.artboard` and makes that the opening document: nothing to save, nothing to undo. Cancel or Escape keeps the canvas as the caller opened it.
- Its keys stay in it (a `keydown` listener stops them reaching the editor: no shortcut, no Ctrl+S behind it); Tab goes round, Enter in a size field creates; a paste is stopped too.

## What does the Canvas tool do?
- Eight handles on a dashed board over the stage (`renderBoard()`, a view hook), its size above. A drag is `M.boardDrag()`: an edge moves its side, a corner two; Shift keeps the ratio (from an edge the other side grows around its middle), Alt moves the opposite side too (from the centre). Sides 16-8000.
- The board moves, the artwork stays: `M.applyBoard()` gives the new size in whole pixels and moves every layer the other way (`M.mapLayer()`), one undo step (`setBoard()`). A zoomed or moved view keeps the artwork where it is on screen; a fitted view fits the new board.
- Panel: canvas size (placed by the nine-point anchor, `M.boardAnchored()`, default centre, kept per editor), Fit to content (`M.boardAround()` of the visible layers), Fit to the selection (with a selection), preset chips, background swatches (`X.swatches` with transparent). The background redraws through the same view hook, so undo brings it back.
- Select with nothing selected on a canvas shows the canvas size and background above the layers, titled Canvas (one line in `renderPanel()` of the core).

## What does the Image tool do?
- "Choose an image from this computer" (a file input, several at once) and "Picture from Files" (the sticker part's `stickerFilesSection()`; its refresh also runs for the Image tool), each through `addPicture()` / `addFilePicture()` of the layers and sticker parts. Pictures dropped on the stage become layers with any tool (`bindDrop()`); a paste already did (`onPaste()`).

## What is still to come?
- On a photo the same tool extends or trims the canvas with a fill, and a frame may grow it outward: the second part of E12, through the save path.
