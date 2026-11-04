---
name: image-editor-select
description: How the image editor's Select tool picks layers: a click, Shift, a press inside an unfilled shape, the selection box, Select all, touch
---

## How are several layers selected?
- A click selects one layer, Shift and a click adds or removes one; Ctrl+A, or Select all above the layers list (two layers or more), selects every layer (`selectAll()`, `pg_image_editor_layers.js`). Select all is there for touch screens, which have no Shift or Ctrl+A.
- A drag from an empty spot draws a box (`.pgie-marquee`) that selects the visible, unlocked layers it touches, a turned layer by its drawn upright bounds (`M.layersInBox()`); Shift keeps the selection and adds them. A box under 3 screen px is a click: it clears the selection. A second finger (the pan) gives back the selection from before the box. The box works with one finger on a phone (#6613).
- On a photo with its picture unlocked, a press on the picture selects and moves the picture (`image-editor-picture-layer.md`): a box starts outside it.

## Why does a press inside an outline shape take it?
- A closed shape or path without a fill was hit only on its line, so shapes drawn with Draw (no fill by default) were hard to select, one by one or several. A press inside one takes it where no other layer lies: a second `M.hitLayer()` pass tests `M.insideRings()` on `M.layerRings()` (#6613).

## Where is the selection used?
- The layer keys, the panel's arrange, align, opacity, duplicate and delete: `image-editor-layers.md`; on a small screen Duplicate and Delete in the selection bar: `image-editor-actions.md`; the combine buttons and the builder's click: `image-editor-builder.md`.
