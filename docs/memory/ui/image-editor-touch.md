---
name: image-editor-touch
description: The Design Canvas on touch: two-finger twist, 44 px targets, the finger's reach (M.touchReach()) for points and a selected layer, the phone's nudge arrows, a typed text Animate did not pick
module: image_editor
---

## How does the two-finger twist work?
- On touch, a finger moving one unlocked layer (Select's `move` drag, also from Draw, Text, Redact and Vector) plus a second finger turns that layer with the line between the fingers, instead of zooming the view: `twistAngle()` adds the turn, keeps -180..180 and holds on multiples of 45° within 4°. Lifting either finger ends it; the move and the turn are one undo step. Two fingers elsewhere still pan and pinch.

## How big is a touch target on a phone?
- A handle drawn as `.pgie-sh` (crop, box handles, warp mesh points, deformation pins) or `.pgie-h` gets a 44 px `::after` target under `(pointer: coarse)`, its drawn size kept.
- A point or edge found by distance takes `M.touchReach(e.pointerType, px)`: 22 px (half of 44) for a finger, the tool's own reach for a mouse or a pen. Used by the polygon's first corner (Select area, 10 px with a mouse), a pin added on the shape's edge (Free deformation, 6 px) and a scissors click on an outline (8 px); a finger's scissors tap may wander 10 px before it counts as a cutting line (4 px with a mouse) (E29).

## How far does a finger keep a selected layer?
- 22 px outside its box (`touchReach()`), in Select, Text and Animate: `M.hitLayer()`'s `picked` option (`pickedReach()`, also in the picture's press), so a small text moves by a drag anywhere on it. A mouse keeps the 4 px slack; a layer above still takes the press where it lies (#10500).
- Animate at a moment (`keyHit()`): the press goes back through the layer's move, scale and turn there (`M.restPoint()`), so a text that grows or turns is hit where it shows. A finger takes any layer up to its box's edge, a selected one 22 px beyond; a mouse 4 px inside, as before.

## How does a phone nudge a layer?
- The bar at the stage's foot (`image-editor-actions.md`) adds four arrows, icons only, 44 px, after Delete when a selected layer moves (neither locked nor fixed): `M.actionList()` with `s.move`.
- A click moves 1 px (`M.nudgeStep()`); held 400 ms, 10 px, again every 120 ms until it is let go (`nudgeHold()`). `nudge()` is also the arrow keys' (Shift: 10 px); nudges in a row are one undo step (`'nudge'` key).

## Why did a tap on a typed text not pick it on a phone?
- Owner (2026-11-20): in Animate and with Select a tap on a text did not pick it; only the Text tool did. Cause: typing in place outlived the tool. Its `<textarea>` lay over the text and stopped the presses, and the layer is not drawn while typed (`textNode()`).
- Only the box's blur ended the typing; a tool button that takes no focus (a phone) or a panel press (`textKeep`) let it stay. Now another tool (`pickTool()`) and a stage press in any tool but Text end it first, and `endTextEdit()` clears `textKeep` (#10500).
- `tests/image_editor_select_press_test.php` holds the fault, the reach, Animate's hit and the nudge's undo step; `tests/image_editor_actions_test.php` the arrows. A real phone's tap is not reached by the tests.
