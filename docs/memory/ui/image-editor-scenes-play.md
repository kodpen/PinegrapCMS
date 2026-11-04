---
name: image-editor-scenes-play
description: Play, the scrub and the clock over every scene of a canvas (AN7b): the whole timeline's hooks, the stage showing the playing scene and its transition, a card dragged to reorder
module: image_editor
---

## How does Play run over the scenes? (#6491)
- The playhead `this.time` is on the play bar's whole timeline: `motionTotal()` (every scene back to back) and `motionStart()` (where the open scene starts, `M.sceneStartAt()`) are hooks in the motion part the scenes part overrides; `motionNow()` is the open scene's own time, which the layers, keyframes, previews and tracks use. A seek from the tracks or a key adds `motionStart()`.
- The stage shows the scene the playhead is in (`sceneShow()`): a closed scene's layers are drawn from `M.sceneView()` (the document is swapped only during the call), with its background; the selection and the key path hide meanwhile. Over a transition a canvas (`.pgie-sfx`, at most 1600 px wide) over the frame shows `renderFrame()` at the whole time, a frame still drawing letting moments pass.
- A press on the stage, another tool, a scene change, or the end of a play that does not repeat goes back to the open scene at rest (`stopMotion()`). Play first readies every scene's pictures.
- The scrub shows each scene's span under it, the open one marked; the tracks stay the open scene's, their playhead dimmed while the playhead is in another scene.

## How is a scene moved by dragging?
- A card dragged along the strip with a mouse or a pen moves its scene where it is let go (`M.sceneSlot()` over the other cards' middles; one undo step). A finger scrolls the strip: on touch the panel's Move earlier and Move later do it.

## Where is it tested?
- `tests/image_editor_scenes_test.php`: `M.sceneStartAt()` and `M.sceneSlot()`. The stage, the transition canvas and the drag were tried in a browser (#6491).
