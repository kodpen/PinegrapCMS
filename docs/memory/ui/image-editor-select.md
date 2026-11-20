---
name: image-editor-select
description: How the Design Canvas picks layers: Select's click, Shift, box, Select all, groups, touch; Ctrl or Cmd selecting from any tool, the Text tool and Animate's click keeping the playhead
---

## How are several layers selected?
- A click selects one layer, Shift and a click adds or removes one; Ctrl+A, or Select all above the layers list (two layers or more), selects every layer (`selectAll()`, `pg_image_editor_layers.js`). Select all is there for touch screens, which have no Shift or Ctrl+A.
- A drag from an empty spot draws a box (`.pgie-marquee`) that selects the visible, unlocked layers it touches, a turned layer by its drawn upright bounds (`M.layersInBox()`); Shift keeps the selection and adds them. A box under 3 screen px is a click: it clears the selection. A second finger (the pan) gives back the selection from before the box. The box works with one finger on a phone (#6613).
- A press on a grouped layer picks its outermost group, a click on the picked group the item one level in; Shift and the box take whole groups (`image-editor-groups.md`).
- On a photo with its picture unlocked, a press on the picture selects and moves the picture (`image-editor-picture-layer.md`): a box starts outside it.

## Why does a press inside an outline shape take it?
- A closed shape or path without a fill was hit only on its line, so shapes drawn with Draw (no fill by default) were hard to select, one by one or several. A press inside one takes it where no other layer lies: a second `M.hitLayer()` pass tests `M.insideRings()` on `M.layerRings()` (#6613).

## How is a layer selected from another tool?
- Ctrl held (Cmd on a Mac, where Ctrl and a click is the context menu) makes a press Select's press in every tool: it selects, Shift adds, a drag moves or draws the box; the tool and its panel stay (`M.pressSelects()`, `Editor.selectPresses`, `pressOn()` in `pg_image_editor.js`; #9965).
- Refused, the press staying the tool's: cropping, typing a text, placing a path (Pen) or a polygon (Select area), editing a path's points, recording a move.
- While the key is held a tool that does not show the selection draws its box and handles (`selectShown()`).
- The Layers list selects in every tool and the tool stays; before, a tool outside `Editor.selectTools` became Select.

## What does a plain click on a layer do in each tool?
- Select, Sticker, Warp, Free deformation (off its pins and outline): Select's press. Draw and Redact: a click selects, a drag draws. Text: a text or any other layer is selected (the panel then has nothing to set for a non-text); an empty spot or the picture starts a new text (#9965).
- Pen, Scissors, Shape builder, Select area: a click is the tool's own work (a point, a cut, a union, a shape); Ctrl or Cmd selects there. Crop, Adjust, Frame, Canvas, Export: a plain click does nothing.
- Animate: a click on a layer where it shows at the playhead selects it (Shift adds) and keeps the playhead, so the panel shows its motion at that moment; a drag past 3 px drops the playhead and moves the layer at rest. A handle or an empty spot edits at rest; the keyframe press and Record keep their rules (`motionDown()`; `image-editor-keyframes.md`; #9965).
- A finger keeps a selected layer as far as 22 px outside it in Select, Text and Animate (`M.hitLayer()`'s `picked` option from `pickedReach()`), so a small layer moves by a drag anywhere on it; a layer above still takes the press where it lies (`image-editor-touch.md`, #10500).
- How it is checked: `tests/image_editor_select_press_test.php` (the pure rule per tool and state, and presses through `onDown` on an editor under Node).

## Where is the selection used?
- The layer keys, the panel's arrange, align, opacity, duplicate and delete: `image-editor-layers.md`; the combine buttons and the builder's click: `image-editor-builder.md`.
- The selection bar (`image-editor-actions.md`): over the selected object on the desktop wherever the box shows, so also while Ctrl or Cmd is held in another tool; at the stage's foot on a small screen.
