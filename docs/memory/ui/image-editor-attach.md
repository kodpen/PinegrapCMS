---
name: image-editor-attach
description: Artifact Canvas Attach to and Detach (GC2): l.att on the follower, the target's motion composed under its own keys, the rest carry in change(), circles refused, a gone target let go, menu and Path tab
module: image_editor
---

## Where does an attachment live?
- On the follower: `l.att` is the target layer's id or `group:<gid>`. Nothing elsewhere, no schema; a document without `att` opens and draws as before. The server's draw knows the field (`pg_image_draw_known()`) and draws the follower where it stands.
- Code: the ATTACH section of `pg_image_editor_model.js`; the editor side in `pg_image_editor_layers.js` (`attachOffer()`, `attachLabel()`, `attachLayer()`), the menu, the Path tab (`attachField()` in keyframes), the core's `change()`. Labels in `pg_image_editor_strings_layers()`. Built in #10593.
- Owner (2026-11-20): "bir vektör diğerini takip edebilsin ... gruplandırılmış mantığı manuel olabilir nesne takibinde": a manual follow, nothing searched in pixels (`workflow/decisions-made-product-80.md`).

## What does a follower do while the animation plays?
- `M.groupMotionAt()` composes, for every moment: its own motion, then its target's move, turn and size round the target's centre (`attachOver()`), then its own groups' keys. The target's entrance, loop, exit, keys and its groups' keys all count; its opacity, wipe and letters never.
- A group as target moves the follower by the group's own keys round the group's box centre. Groups the follower shares with its target count once. Chains compose; a circle in a hand-made document stops.
- At rest the target stands still, so attaching moves nothing: the follower keeps its own place (the offset) and its own keys on top. The stage, the export, the SVG save (`svgMotion()` with `M.attachMoves()`) and a press's `restPoint()` all read the same state.

## What happens when the target is edited at rest?
- `M.attachKeep(before, doc, fit)` runs in every `change()` (one undo step with the change): a follower the change left alone goes with its target's move, turn and proportional size; a size out of proportion only moves it. Followers of followers go too. A follower moved by the same change keeps its own place. A text's letters are rescaled by its type's resize (`attachFit()`).
- A group target carries only when every member moved as one (a group drag, size or turn); one member moved alone carries nothing.
- A deleted target, an ended group, a follower put into its target group or a circle: the follower lets go where it stands.

## Which commands are there?
- Attach to (the context menu's submenu of targets; the Path tab's select, Not attached detaches) for one free layer: not a picked group, a locked or a fixed layer. Targets in the layers list's order, named as there (`attachLabel()`); refused: itself, a group it is in, a gone target, a circle (`M.attachCan()`, `M.attachTargets()`).
- Detach (the menu, or Not attached) drops `att`; the layer stays at its own place.
- One undo step each; no keyboard shortcut. A phone reaches both through the long-press menu and the Path tab in the sheet.
- Duplicate, copy and paste: a target copied with its follower stays its target (`M.attachRemap()`); a follower copied alone keeps the original target.

## How is it checked?
- `tests/image_editor_attach_test.php`: every model command under Node (attach, detach, circle refused, deleted target, group as target, keys on top of the target's, the rest carry), the editor's menu, commands, copies and `change()`, and the server's plan equal to the one without `att`. Not run in a browser: the menu's submenu and the Path tab's select on a stage, a playing preview and an animated export.
