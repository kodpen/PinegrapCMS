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
- One selected, unlocked layer gets eight handles and a turning handle above it (`.pgie-sh`, 44 px targets on touch), plus its type's own (`.pgie-shx`). A press on a box handle more than 10 screen px inside the box moves the layer (`insideBox()`): on touch the 44 px targets cover a small sticker's middle. `resizeBox()` keeps the opposite edge or corner in the layer's own turned frame; a picture keeps its ratio from a corner (Shift does the other); `angleTo()` turns it, Shift in steps of 15°.
- A move snaps an edge or the middle to the canvas's edges and middle and to the other visible layers' (`snapBox()`, 6 screen px), with pink guides; Alt moves freely. A drag is one undo step.
- Keys with a selection: arrows nudge 1 (Shift 10), Delete removes, Ctrl+D duplicates, Ctrl+C copies (a paste without a picture on the clipboard brings the copies back), Ctrl+A selects all. A focused slider keeps these keys; text fields keep their own.

## What does the panel offer?
- Its title names what it shows (`M.panelTitle()`): with a selection the layers' one type ("Metin", "Etiket"; "Seçim" for mixed types), its own controls first (`layerTypes[type].panel`), then Arrange (`reorder()`), Align (to the canvas for one layer, to the selection's box for more; `align()`), distribute (three or more, `distribute()`), layer opacity, duplicate, delete. No list there.
- The list (every layer, the top one first, its type's icon, show/hide, lock; a press selects, Shift adds) is Select's panel with nothing selected (on a canvas after the canvas) and the rail's Layers button's (`toggleLayers()`, at the rail's foot, at the end of a phone's bottom bar), from any tool, as screens 01 and 05 show: a second press, another tool or Escape closes it; a crop being drawn is kept first. Before (until the owner's report of 2026-11-02) the selection's controls stood under "Layers" with the list below them and the rail had no button.

## How is a layer saved?
- `save()` first waits for what the visible layers need (`layersReady()`: pictures, later fonts), then `drawOverlay()` draws the visible layers at the output size, the crop box scaled to it, into a clear PNG the server lays over the pixels (`platform/image-editor-save.md`); an artboard's PNG gets its layers too. The document keeps the layers for the versions to come.
