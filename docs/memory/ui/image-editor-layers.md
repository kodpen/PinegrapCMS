---
name: image-editor-layers
description: The image editor's layers and the Select tool: the layer box model, selection and handles, snapping, align, distribute, order, the layers list, keys, pasting a picture, and the overlay a save draws
module: image_editor
---

## What is a layer?
- An object in `doc.layers` (bottom first) in the rotated space (after quarter turns and flips, before straightening and the crop): `id` (`newId()`), `type`, a box `x, y, w, h`, `rot` (degrees clockwise around its centre), `opacity`, `hidden`, `locked`. A crop never moves a layer; the frame clips what lies outside it.
- `image` is the first type: a picture pasted with Ctrl+V (a data: URL, kept at most 2000 px), placed in the middle at most 60 % of the canvas. Shapes, text and stickers add their types with their tools (`Editor.layerTypes`, `image-editor-parts.md`); `layerNode()` draws a type as SVG, `paintLayers()` on a canvas.
- Under a turn or a flip of the picture, `mapLayer()` moves an upright type (text, sticker, image) by its middle and keeps its shape and angle, so a pasted logo stays readable; another box type keeps its size, takes the turn into its angle and, under a mirror, turns its angle the other way and toggles `mirror` (`image-editor-draw.md`).

## How does the Select tool work?
- A press hits the topmost visible, unlocked layer (`hitLayer()`, 4 screen px of slack): it is selected and moves with the rest of the selection; Shift adds or removes; the empty stage clears the selection; Escape clears it before leaving full screen. Locked layers are chosen in the list only.
- One selected, unlocked layer gets eight handles and a turning handle above it (`.pgie-sh`, 44 px targets on touch), plus its type's own (`.pgie-shx`). `resizeBox()` keeps the opposite edge or corner in the layer's own turned frame; a picture keeps its ratio from a corner (Shift does the other); `angleTo()` turns it, Shift in steps of 15°.
- A move snaps an edge or the middle to the canvas's edges and middle and to the other visible layers' (`snapBox()`, 6 screen px), with pink guides; Alt moves freely. A drag is one undo step.
- Keys with a selection: arrows nudge 1 (Shift 10), Delete removes, Ctrl+D duplicates, Ctrl+C copies (a paste without a picture on the clipboard brings the copies back), Ctrl+A selects all. A focused slider keeps these keys; text fields keep their own.

## What does the panel offer?
- With a selection: Arrange (to the front, forward, backward, to the back: `reorder()`), Align (left, centre, right, top, middle, bottom: to the canvas for one layer, to the selection's box for more; `align()` on the upright boxes), distribute horizontally or vertically (three or more, `distribute()`), layer opacity, duplicate, delete. Then every layer, the top one first, with show/hide and lock.

## How is a layer saved?
- `save()` first waits for what the visible layers need (`layersReady()`: pictures, later fonts), then `drawOverlay()` draws the visible layers at the output size, the crop box scaled to it, into a clear PNG the server lays over the pixels (`platform/image-editor-save.md`); an artboard's PNG gets its layers too. The document keeps the layers for the versions to come.
