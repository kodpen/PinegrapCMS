---
name: image-editor-attach
description: Artifact Canvas Attach to and Detach: l.att on the follower, the target's motion under its own keys, the rest carry, circles refused; menu, More, Path tab first, layers list mark
module: image_editor
---

## Where does an attachment live?
- On the follower: `l.att` is the target layer's id or `group:<gid>`. No schema; a document without `att` opens and draws as before. The server's draw knows the field (`pg_image_draw_known()`) and draws the follower where it stands.
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

## Where is it offered?
- Attach to for one free layer (not a picked group, a locked or a fixed layer): the context menu's submenu, the same menu the More button of the bar over one object opens, and first on the Path tab (`keysTop()`, the pane's `top`, above Record), a select whose Not attached detaches. Targets in the layers list's order, named as there (`attachLabel()`); refused: itself, a group it is in, a gone target, a circle (`M.attachTargets()`).
- A picked group's Path tab: one line on how a layer follows it (`attachGroupNote`). The layers list: a follower's row shows a link and its target's name, titled "Attached to …" (`followMark()`). More, first place, line and mark: #10970.
- Detach (the menu, or Not attached) drops `att` where the layer stands. One undo step each; no shortcut. A phone uses the long-press menu and the sheet's Path tab.
- Copies: a target copied with its follower stays its target (`M.attachRemap()`); a follower copied alone keeps the original target.

## How is it checked?
- `tests/image_editor_attach_test.php`: every model command under Node (attach, detach, circle refused, deleted target, group as target, keys on top of the target's, the rest carry), the editor's menu, commands, copies and `change()`, and the server's plan equal to the one without `att`. Where it is offered: `tests/image_editor_attach_place_test.php`. Not run in a browser: the menu's submenu and the Path tab's select on a stage, a playing preview and an animated export.
