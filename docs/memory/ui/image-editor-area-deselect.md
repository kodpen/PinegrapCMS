---
name: image-editor-area-deselect
description: How the image editor's selected area (marching ants) is dropped outside Select area: a click outside it with Select, Escape's order; the ants that stayed after Adjust inside (#7994)
---

## How is the selected area dropped?
- Select area: a click without a drag (New mode), Deselect in the panel, the selection bar or the context menu (`areaClear()`, one undo step).
- Select: a click outside the ants deselects them, whatever it selects: an empty spot (the layers clear with it), a layer, the unlocked picture. A click is a press without Shift, Alt, Ctrl or Cmd that stays within 3 screen px (`CLICK_PX`) and ends in a `pointerup`: `pressClick()` keeps the press on the drag (`click`), `dragAway()` drops it once the pointer goes further, even if it comes back, and `endDrag(e)` then calls `areaClickOut(d.at)` (`insideRings()` on `areaRings()`). Only the Select tool: Text, Animate and the other tools that press as Select keep the area.
- Kept: a click inside the ants, a drag (a selection box, a layer or the picture moved), a modifier, a cancelled pointer, a long press (the menu ends the press with its own `pointerdown`), a right click (the menu's press calls `endDrag()` with no event).
- Escape (`onEscape()` in `pg_image_editor.js`), after a tool's own state, the question and a crop: the layers the tool outlines (`selectTools`), then the area, then layers the tool holds without outlining them (Select area keeps the ones chosen before it), then full screen, then close. A selection with no area left is passed over.

## Why?
- Erase inside, Adjust inside and Copy to a layer keep the selection, so Select area's commands can use it again, and switch to Select with the new layer (`areaShow()`). Select has no Deselect command; before #7994 neither a click outside, Escape nor locking the new layer dropped the ants there, only going back to Select area did (reported 2026-11-08 on an Adjust inside of a logo).

## How is it checked?
- `tests/image_editor_deselect_test.php` (Node, a bare editor): clicks outside, beside the picture, inside, with each modifier, as a box and as a drag that comes back, on a layer inside and outside, on the unlocked picture; a cancelled pointer, a long press, the menu's press, the Animate tool; Escape's order with Select and Select area.
- Browser (sandbox, 2026-11-08): 1280×800, the wand, Adjust inside, a click inside and outside, undo, Escape twice, a Shift click, a box, a right click, Escape in Select area; 390×844 touch: Adjust inside from the bar, a tap outside, a long press (menu, area kept).
