---
name: image-editor-scenes
description: A canvas's scenes (AN7a): doc.scenes and the open scene, the strip, the scene panel, transitions, frames across scenes, the save's cap, a canvas animation tied to its canvas
module: image_editor
---

## What does the document hold?
- Only a canvas (`doc.artboard`) has scenes: a picture's base is one server render. `doc.scenes` lists `{ id, name, transition }`, `doc.scene` names the open one. Its layers, background and duration are the document's own (`doc.layers`, `doc.artboard.background`, `doc.motion.duration`), so no tool changed; a closed scene keeps them in its entry (`layers`, `background`, `duration`).
- No `doc.scenes` is one scene; deleting down to one scene drops `scenes` and `scene`. `M.canvasOpenDoc()` drops them unless there are two or more and the open one is among them.
- The model's `SCENES` section: `sceneList()` (the open one filled from the document), `sceneOpen()`, `sceneAdd(doc, copy)`, `sceneDelete()`, `sceneMove()`, `sceneSet()` (duration, transition), `sceneTotal()`, `sceneStartAt()`, `sceneSlot()`, `sceneAt()`, `sceneView()`, `sceneTransitionAt()`. At most 50 scenes (`SCENE_MAX`); a new scene lasts 3 s.

## How do transitions play?
- A scene's `transition` `{ kind, ms }` leads into the next one: none, fade, slide, zoom, wipe; 0.1-2 s in steps of 0.1 (default 0.6). It runs over the first `ms` of the next scene (never longer than it), so the whole length is the scenes' durations added up.
- `sceneAt(doc, t)`: the scene, its own time and, during a transition, the previous scene (drawn at its end), the kind and its eased progress. The last scene's transition never plays: the panel leaves it out.

## Where is it edited?
- Part `scenes` (`pg_image_editor_scenes.js`, labels `pg_image_editor_strings_scenes()`), a row of `Editor.motionDocks` after the tracks, only in the Animate tool on a canvas: a card per scene (number, thumbnail, duration), the transition's button between two, + (Add a scene). On a phone the strip comes first in the sheet.
- A card or a transition opens that scene's panel (`sceneFocus`) until a layer is selected or the stage is pressed: duration, transition and its time, Duplicate, Delete, Move earlier, Move later. Every scene change is one undo step and clears the selection; a closed scene's thumbnail is kept by its layers array.
- The strip is drawn again on every whole render (`sceneStamp`), not on a drag's moves.
- Play over every scene and a card dragged to reorder: `image-editor-scenes-play.md`.

## How are frames drawn?
- The part wraps `renderFrame()`: with two scenes or more a time is the whole animation's; each scene is drawn by `renderFrame(time, w, h, canvas, doc)` with `M.sceneView()` (the render part swaps `this.doc` only while it paints), and a transition lays the two frames as `sceneTransitionAt()` places them. `layersReady()` waits for every scene's pictures.
- Save animation's length, estimate and preview use `M.sceneTotal()`; `animMoves()` counts two scenes as moving.

## What does the server check?
- `pg_image_edit_validate()`: `scenes` only with an artboard, at most 50, each an object whose `layers` (when set) are at most 500. Action `animation` runs the same check on its document.

## How is a canvas animation tied to its canvas? (#6229 review)
- Action `animation` from a saved canvas records the canvas's file id as `source` (an animation opened as its own canvas keeps the id it names), so a replace names only an animation of that canvas; a canvas not saved records 0 as before.
- `pg_image_editor_animation_open()` opens a canvas animation as that canvas (its id, name and versions; a still save is a copy, the notice names both); with the canvas gone or refused, or source 0, as a canvas of its own as before.

## Where is it tested?
- `tests/image_editor_scenes_test.php`: the swap on open, add, duplicate, delete, move, the timeline and transitions, scene starts and a dragged card's slot, a reopened canvas, the validator's cap, the labels.
