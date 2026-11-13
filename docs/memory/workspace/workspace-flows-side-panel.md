---
name: workspace-flows-side-panel
description: The flow window's right panel for a chosen box, arrow or thread (FW3, #9196): head, Settings / Connections / Try tabs, foot line, Duplicate, Close, Esc, F6, phone sheet
---

## What does a chosen box's panel hold? (sideNode() in workspace_flow.js)
- A head (`sideHead()`): the box's tile (`pg-icon-tile-lg` in its group tone), its kind in small capitals, its name (updated as the title is typed), ⋯ with Duplicate and Remove the box (editors), Close.
- Tabs (`pg-seg`, `role="tablist"`): Settings (the kind's fields, `nodeForm(node, true)`), Connections with the count of arrows in and out, Try (the flow's owner editing it, as before). Left / Right step round, Home / End go to the ends (`sideTabKey()`).
- The foot line (`sideFoot()`): "Before <box>" (the first by arrow order) and "After <box>" or "After N boxes", each a button that chooses that box.
- The List view keeps one form per box with its connections, Try and Remove under the settings (`nodeForm(node)` without `settingsOnly`).

## Which tab opens?
- Settings at first; while boxes are chosen one after another in the same flow, the tab last chosen (`state.sideTab`, `sideTabFlow`). Enter on a canvas box shows Settings and focuses its first field.

## What does Connections show?
- Before: each box an arrow comes from, a row (tile, name) that chooses it. After: each arrow's box, its outcome select (a box with more than one outcome) and ×. "Add an arrow" with the Connect to… list under them; C still opens the dialog. A reader sees the lists only.

## What do Duplicate, Close and Esc do?
- Duplicate (`duplicateNode()`, `copiedNode()`): the box with its title and a deep copy of its settings, no arrows, a free id of its kind, right of it and moved down past any box in the way; chosen; one undo step. A start box has none.
- Close (`closeSide()`): nothing chosen, the focus on the box on the canvas without choosing it again (`state.closing` stops the canvas's focus-selects-the-box). Esc in the panel gives its box the focus and leaves it chosen; a menu open in the panel takes its own Esc.

## And an arrow or a thread?
- The same head with an arrow or thread icon: "Arrow" over "<from> › <to>", "Data thread" over its value; ⋯ › Remove; no tabs, the old form under it. Delete still works on the canvas.

## How do the keys move between the areas?
- F6 / Shift+F6 (`areaKey()`): the bar, the left panel, the canvas (the chosen box, else the canvas) and the right panel in turn; a folded left panel (its rail) and an area with nothing to focus are passed over. Listed in Keyboard (`fl_key_areas`). The four are named regions (the bar's `nav`, the two `aside`s, `.wsf-main` as "Flow view"). Architect (2026-11-13, agent channel): F6 as proposed in FW0.

## And on a phone?
- 768-1279 px: the right panel is 300 px beside the 48 px rail (architect 2026-11-13: the narrow and phone drawing approved as drawn).
- Under 768 px a box chosen on the canvas opens the panel as a sheet from the bottom, at most 85 % high, over a scrim (`wsf-side-chosen`, `.wsf-scrim`); Close, Esc or a tap on the scrim closes it. The List view is unchanged.

## Tests
- `test_workspace_flows_canvas_side_panel_helpers` (Node): the tabs, the tab keys, the arrows in and out, the copy.
- Harness (`workspace-flows-canvas-harness.md`): the head, tabs and keys, foot line, Duplicate, Esc, F6, Close; Connections adds and removes arrows; the phone sheet.
