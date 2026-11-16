---
name: design-canvas-quick-reach-features-2
description: Plan design-canvas-quick-reach, features R13 to R24: the bar over the selected object on the desktop, its commands, where it sits and when it hides, the Motion popover, a track row's Motion button
---

## The bar over the object (QR3)
- R13 Where the panel is not a sheet, the selection's bar is drawn over the selected object in every tool that shows the selection, and with R8's press in the others. The phone keeps its bar at the stage's bottom and its list.
- R14 Its list comes from `M.actionList(state)` with the place named in the state, so the phone's list does not change. In this order: the type's own command, then Motion (R19), Duplicate, Delete, Lock or Unlock, More.
- R15 The type's own command: one text, Edit text (types into it); one video in Animate, Split (R6's method); nothing for the others. Follow joins here from `plans/design-canvas-follow.md`.
- R16 More opens the context menu's list (`M.menuList()`, `ui/image-editor-menu.md`) at the bar: order, align, flip and the rest are reached there, not copied into the bar. Several layers selected: Duplicate, Delete, Lock, More.
- R17 Place: centred above the selection's bounds, 8 px clear of the box and of the turning handle; below the box where there is no room above; kept inside the stage and clear of the play bar and the tracks. It follows a zoom, a pan and a move, drawn where the selection box is drawn.
- R18 Hidden while a layer is dragged, sized or turned, while typing, cropping, recording, playing, and for the picture and an area (their commands stay in the panel on the desktop). A locked layer shows Unlock and More only. Only the bar takes presses; a press on it never reaches the tool. Keys: the stage's Tab order reaches it, arrows move inside it, Escape goes back to the stage; each button has its name and title.

## Motion from the bar (QR3)
- R19 In Animate the bar of one layer that may move (never a redaction) has Motion. It opens a popover at the bar: three rows named In, Loop and Out, each with None and that phase's kinds as the panel's own tiles, scrolling sideways; the kind in use is marked.
- R20 A pick sets the phase through the panel's own call (`setMotion()`), plays its preview (`motionPreview()`), keeps the popover open and is one undo step. The direction, length, delay and ease stay in the panel.
- R21 "More options" at the popover's foot opens the panel on that phase: Animate's panel for a layer, the clip panel's Motion tab for a video. Escape or a press outside closes it; the focus goes back to Motion.
- R22 The popover never covers the object's centre: it opens above or below the bar on the side with room.

## From the tracks (QR4)
- R23 A track row's name cell has a small Motion button (the Animate icon): a press selects the row's layer and opens R19's popover at the button. Not on a redaction's row, not on the phone.
- R24 The bar's and the popover's labels are the owning parts' keys where one exists (`clipSplit`, the motion kinds); new ones go into the actions part's strings with `php tools/tr_add.php`.

## What do QR3 and QR4 test?
- `tests/image_editor_actions_test.php`: the object list for one text, one video in and out of Animate, several layers, a locked layer, a redaction; the phone's lists unchanged, red first.
- A pure function for the bar's place (above, below, kept inside), tested with boxes at each edge of the stage.
- The stylesheet's rule: the bar shown where the panel is not a sheet, the phone's rule kept.
