---
name: image-editor-sticker
description: The image editor's Sticker and Frame tools: the sticker layer, its label, a picture from Files, the four frames on the saved picture's edge, an artboard's background fill, how a save draws them
module: image_editor
---

## What does the Sticker tool offer?
- Eight built-in stickers (`STICKERS` in the model: burst, pill, tag, stamp, speech bubble, check, star, heart), placed in the middle of the visible stage at a size in hundredths of the picture's shorter side, selected. The tool is a select tool: the selection's handles, moves and keys work in it (`Editor.selectTools`).
- With stickers selected: sticker colour, label colour (not for a star or heart), the label of one sticker (a double click with Select opens the sheet and focuses it), then duplicate, forward, backward, delete. Select's panel shows the same colours and label (`layerTypes.sticker.panel`).
- "Picture from Files" opens on demand: the 60 newest pictures (jpg, png, gif, webp) or those whose name holds the search, from the API action `sticker_files`; none in the Recycle Bin, and a role-3 user's folders filtered in the query before its LIMIT (`pg_image_editor_sticker_where()`). A press adds an `image` layer with the file's address (same origin, so the save's canvas stays clean) and `fileId`. Not offered in a callback save or without the API.

## What is a sticker layer?
- `{ type: 'sticker', kind, label, color, ink, x, y, w, h, rot, opacity }`. `stickerShape(l)` gives its outline in its box (`d`, a second path `hd`, `outline` for the stamp's two rings, `tick`, the label box `lb`); the stage (`node`) and the save (`paint`, `Path2D`) draw the same paths.
- The label is written in system fonts (Impact family, the bubble in the system sans bold), sized by `stickerFont()` from the browser's measured width per character: the box's height bounds a short label, its width a long one. No web font, so a save waits for nothing.
- Upright: a turn or flip of the picture moves only its middle (`mapLayer()`); a corner keeps its shape (`ratio`).

## How do frames work?
- `doc.frame` is `{ kind: line|thick|polaroid|corners, color, width 1-10 }` or `null` (None, Reset). `frameShape(frame, box)` gives an even-odd ring over the saved box (`M.saveBox()`: the crop box or a photo's canvas, an artboard's whole board); the stage draws it in its own SVG over the layers (hidden while cropping).
- A frame lies on the edge of the saved box, the polaroid's wider foot too. On a photo a line, thick line or polaroid may lie outside it (`frame.outside`), growing the canvas; Polaroid starts outside there (screen 08, `ui/image-editor-artboard.md`). Choosing Polaroid sets white.
- A save with a frame redraws the overlay (layers, then frame) in a save hook; a new artboard's PNG gets fill, layers and frame. The server lays the overlay before the crop shape, so a circle crop cuts the frame too.
- Until versions keep the document (E11), a reopened file shows the frame as pixels: adding one again draws a second.

## What is the background fill?
- On an artboard (a blank canvas) the Frame panel shows Background swatches with Transparent; it sets `doc.artboard.background`, drawn by the stage each redraw (so undo follows) and by the save's PNG. A photo's canvas fill is the Canvas tool's (`doc.canvas.background`, `ui/image-editor-artboard.md`).

## Where is it tested?
- `tests/image_editor_sticker_test.php`: the set and its labels, outlines and label boxes inside the box, label sizes, frame rings, the Files listing's filter (`pg_image_editor_sticker_rows()`).
