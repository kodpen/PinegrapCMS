---
name: image-editor-groups-look
description: Design Canvas group's own size, turn, opacity and Animate keys (GC1b): l.gfx on members, the group box, keys over a member's own, the bars, server draw
module: image_editor
---

## Where does a group's own look live?
- On each member as `l.gfx[gid] = { opacity, keys }`, the same on every one (`M.groupFxSet()` writes all, `M.groupFx()` reads); nothing for opacity 1 without keys. A layer carries all it needs, so the server's draw and a scene read it alone. No schema.
- `M.groupTidy()` drops the entry of an ended or broken group; `M.groupCopy()` moves it to the copy's new id; `M.groupMake()` starts a group with none.

## How are a group's size and turn set?
- A picked group (`M.groupPicked()`: the selection is exactly one group's layers) gets its own box round its members (`.pgie-grpsel`, 3 px outside): four corners and the turning handle (`groupSelection()`, `data-gh` gnw…grot), while no member is locked or fixed.
- A corner scales every member round the opposite corner, proportions always kept (`M.groupScale()`; a text's own resize scales its letters). The turning handle turns their centres round the box's centre and adds to each own turn (`M.groupTurn()`, Shift: 15° steps). Nothing is stored for the group: the members' own values change. One undo step each ('drag').

## How does a group's opacity work?
- With a group picked, Select's slider reads "Group opacity" and sets `gfx[gid].opacity` (`setOpacity()`); the members keep their own. Drawn opacity = own × every group's (`M.groupAlpha()`, `M.groupLook()` gives the drawn copy).
- It multiplies per layer: overlapping members show through each other, as at their own opacity. Used by the stage, the still and animated export (`paintLayers()`), the SVG save and the scene thumbnails.

## How do Animate's keys work on a group?
- With a group picked, the Path tab keys the group (`keyTarget()`, `M.groupKeyLayer()`, id `group:<gid>`; `setKey()` and `editKeys()` take it); a drag on a member at a moment sets the group's key. The diamonds on the tracks show layers' own keys only.
- `M.groupMotionAt()` composes the group's keys over a member's own motion, the innermost group first: move, turn and scale round the group's centre at rest, opacity multiplied; the result is a state round the member's centre, so the stage, export, SVG motion and a press's `restPoint()` read it unchanged. At rest nothing moves. `M.hasMotion()` counts group keys.

## Where are Group and Ungroup on a phone?
- The bar over the object and the phone's bar (`M.actionList()` with `s.group`, `s.ungroup` from `groupState()`): Group for several layers, Ungroup for a picked group, before Duplicate; the phone's bar scrolls, so each stays reachable.

## What does the server draw?
- `pg_image_draw_group_opacity()` multiplies the layer's opacity by its groups' (`gfx` a known field); an entry of a group not in `grp` is ignored. Keys only move in an animation, never at rest.

## How is it checked?
- `tests/image_editor_groups_fx_test.php`: every model command under Node, the handles, slider and keys on an editor, the bars, the server's plan against the same opacities ungrouped. Not run in a browser: the stage's handles, the phone bar's scroll and an animated export's frames.
