---
name: image-editor-actions
description: The image editor's selection bar on a small screen: which commands show (M.actionList()), where it sits, when it hides, why it exists, how it was checked
module: image_editor
---

## What is it, and why?
- Part `actions` (`pg_image_editor_actions.js`, bundled before the context menu `ui/image-editor-menu.md`, which starts from the same list; labels `pg_image_editor_strings_actions()`; rules under its line at the end of `pg_image_editor.src.css`): the selection's main commands in a bar over the stage, only where the panel is a sheet (below 768 px wide or 500 px tall). On the desktop the panel shows the same buttons, so the stylesheet hides it there.
- Owner (2026-11-04, a phone test): a selected sticker or picture could not be deleted (no Delete key, Select's sheet closed), and after a lasso the area's commands sat at the foot of a long sheet.

## Which commands show?
- `M.actionList(state)` (the model's ACTIONS section) from `actionState()`. Layers selected with a tool that selects layers: Remove pin first while Free deformation has a pin chosen (`state.pin`), Options (while the sheet is closed), Split for one clip in the Animate tool (`state.split`, `image-editor-phone-rows.md`), Duplicate, Delete (off when all are locked).
- The picture selected (`image-editor-picture-layer.md`): Options, Lock, Delete. An area with Select area: Options, then Erase inside, Keep only, Copy to a layer, Cut to a layer, Adjust inside where the area part has them (`areaErase()` …), Redact the selection, Invert, Deselect. Layers come before the picture, the picture before an area.
- Options opens the sheet (and closes the layers list): arrange, align, opacity, colours, the area's shapes and feather are there. Arrange in the bar did not fit a 390 px phone: Delete was scrolled out of sight.
- The commands call the parts' own methods (`addLayers()`, `removeLayers()`, `lockPicture()`, `deletePicture()`, `areaRedact()`, `areaInvert()`, `areaClear()`), so undo and the panel stay in step. Labels are the owning parts' keys; the bar's own are `actBar` (its aria-label) and `actOptions`.

## Where does it sit, and when does it hide?
- In the stage, centred, above what covers the stage's bottom (an open sheet's 12 px overlap on a phone held upright, the Animate bar's `stageInset`) and left of a sheet over the stage's right (`panelCover()`), through `--pgie-act-bottom` and `--pgie-act-right`. With the mini bar on a phone held upright (the mini bar at the bottom) it takes the top.
- Only the bar takes presses (its wrapper has `pointer-events: none`); pointerdown, pointerup, dblclick and wheel stop on it, so a tap never reaches the tool and a swipe scrolls a long bar.
- Hidden while dragging (`this.drag`), typing a text (`textEdit`), placing a polygon (`areaPoly`), cropping, after a failed open. Rebuilt only when its list changes.

## How was it checked?
- `tests/image_editor_actions_test.php`: the lists for layers, locked layers, the picture, an area with and without the pixel commands; the bundle, the labels, the small-screen rule.
- Browser (sandbox, 2026-11-04), 390×844 touch: a sticker selected with Select, Duplicate and Delete from the bar, Options opening its sheet; a lasso with the sheet open and closed, Invert, Deselect; the picture unlocked; 844×390 with and without the sheet; the mini bar (arrow, text) on top; hidden at 1280×800. With the area's pixel commands merged locally: eight commands scroll by a swipe that keeps the selection, and Keep only works from the bar.
