---
name: image-editor-frames
description: The Artifact Canvas's photo frames (AC10): the Frames chip in Elements, the frame layer type and its fields, a picture dropped or picked into it, the photo moved and zoomed inside, how it saves
module: image_editor
---

## What does a person see?
- In the Sticker tool's Elements, a last chip "Frames": circle, rounded square, arch, blob (own shapes), then the element set's filled badges but its circle. A press puts an empty frame (a grey dashed shape saying "Your picture") in the stage's middle, 45 % of the visible board, selected; one undo step.
- A picture dropped on a frame fills it instead of becoming a layer. Its panel (Select's, and the Sticker tool's after Elements) has "Choose a picture", which opens the Image tool with Picture from Files and the note "Pick a picture to fill the frame."; a picture from Files or from this computer then fills it. A double press on an empty frame does the same.
- Filled: "Move the photo" (or a double press) starts the moving: the rest of the photo shows faint round the frame, a drag inside it moves the photo (one undo step a drag), Escape, "Done", a press outside the frame or another tool ends it. Zoom 100-400 %, "Replace picture", "Remove picture".

## What is a frame layer?
- The one layer type the content plan adds: `{ type: 'frame', shape, d, vw, vh, x, y, w, h, rot, opacity, name }`; the shape's path `d` in its own `vw` x `vh` box is kept in the layer, so the document needs no shapes file.
- Filled it adds `src`, `pw`, `ph` (the picture's size), `zoom` (1-4), `fx`, `fy` and `fileId`, or `fileName` for a picture from this computer (a data picture, at most 2000 px, PNG or JPEG).
- The photo covers the frame at zoom 1; `fx`, `fy` are -1 to 1 of the room the photo has past the frame on each axis (0 centred), so it never shows the frame's ground, and a resized (AC9) or turned frame keeps the place. A drag is taken along the frame's own axes.

## How does it save, animate, export and translate?
- As any layer: `node()` draws the photo clipped to the shape on the stage, `paint()` the same on the save's canvas (Path2D clip). An empty frame saves as drawn, its words in the panel's language.
- A save into Files sends a frame's data picture with the picture layers' (`M.animPictures()` and the artboard save hook take `frame`), and the server points it at its new files row by layer id, as a picture layer's.
- Motion, export, scenes and the browser's draw of a translated picture go through `paintLayers()`; a frame has no text. The server's own drawing (`platform/image-server-draw.md`) does not know the type, so a canvas with a frame waits for a browser there.

## Where is the code?
- `assets/js/pg_image_editor_frames.js` (part `frames`, bundled after `resize`): the layer type, `addFrame()`, `framePick()`, `frameFillFrom()`, `frameEditStart()` / `frameEditEnd()`, `frameZoomTo()`, `frameRemove()`, `framePanel()`. It wraps `addFilePicture`, `addPicture`, `bindDrop` (a capture listener notes the frame under a drop), Select's pointers (shared by Sticker), `elementTiles`, `elementsSection` and the Sticker and Image panels.
- Hook in the elements part: `X.elementList()` gives the checked elements read so far.
- Model section `FRAMES`: `frameShapes()`, `frameLayer()`, `frameFill()`, `frameClear()`, `frameFit()`, `frameMove()`, `frameZoom()`, `FRAME_ZOOM`. Labels `pg_image_editor_strings_frames()`; no own stylesheet rules (tiles are the elements' `.pgie-elb`).
- Tests: `tests/image_editor_frames_test.php` (shapes, layer, cover, move, turned move, zoom, emptied, the save's pictures, the part, an editor: place, pick from Files, move, zoom, Escape, a drop). The stage and save drawings and a real drop were not run in a browser.
- Licence: the four own shapes are the product's own; the badges are the element set's own (`image-editor-elements.md`).

Related: docs/memory/ui/image-editor-elements.md, docs/memory/ui/image-editor-starts.md
